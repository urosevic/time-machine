<?php
/**
 * Detection of a site wide REST API lockdown, and the admin notice for it.
 *
 * @package TimeMachine
 */

namespace TechWebUX\TimeMachine;

defined( 'ABSPATH' ) || exit;

/**
 * Class Rest_Health
 *
 * A fair number of security plugins and snippets close the whole REST API
 * to signed out visitors through the `rest_authentication_errors` filter
 * (Disable WP REST API, Disable REST API, "restrict REST API" settings in
 * the larger security suites, and so on). That filter runs inside
 * WP_REST_Server::serve_request() well before any route's own
 * `permission_callback`, so Rest_Controller's `__return_true` never gets a
 * say: the request is rejected with a 401 no matter how public the route
 * declares itself to be.
 *
 * Nothing breaks for visitors when that happens - the list is rendered
 * server side and assets/js/frontend-refresh.js keeps the already rendered
 * markup on a failed request - but every page carrying a Time Machine list
 * logs a 401 in the browser console, which is noise nobody asked for.
 *
 * This class does not try to force its way past that lockdown. Overriding a
 * decision the site owner made on purpose is not the plugin's call to make.
 * Instead it probes its own route once a day over a loopback request, and
 * when the answer is a REST level rejection it tells the administrator, in
 * an admin notice and in a Site Health test, how to let this one read only
 * route through - or how to switch the refresh off and be done with it.
 *
 * @since 26.9.0
 */
class Rest_Health {

	/**
	 * Option holding the last probe result.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'time_machine_rest_health';

	/**
	 * Cron hook running the daily probe.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'time_machine_rest_health_check';

	/**
	 * User meta holding the `first_seen` timestamp this user dismissed.
	 *
	 * Storing the timestamp rather than a plain flag means a dismissal
	 * covers the lockdown that was on at the time, and a later one (after
	 * the route worked again in between) still gets announced.
	 *
	 * @var string
	 */
	const USER_META = 'time_machine_rest_health_dismissed';

	/**
	 * `admin_post_{action}` used by the notice's dismiss link.
	 *
	 * @var string
	 */
	const DISMISS_ACTION = 'time_machine_dismiss_rest_notice';

	/**
	 * Query arg that turns a request to the list route into a cheap probe.
	 *
	 * @var string
	 */
	const PROBE_ARG = 'time_machine_probe';

	/**
	 * How long a stored probe result stays fresh enough for the Site Health
	 * test to reuse instead of probing again, in seconds.
	 *
	 * @var int
	 */
	const FRESH_FOR = HOUR_IN_SECONDS;

	/**
	 * Transient holding the lock that keeps concurrent admin requests from
	 * each firing their own first probe.
	 *
	 * @var string
	 */
	const LOCK_TRANSIENT = 'time_machine_rest_health_lock';

	/**
	 * Hook everything this class does.
	 *
	 * @return void
	 */
	public static function register() {

		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_check' ) );

		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', array( __CLASS__, 'maybe_run_first_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_render_notice' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( __CLASS__, 'handle_dismiss' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'register_site_health_test' ) );
	}

	/**
	 * Schedule the daily probe, unless it is already scheduled.
	 *
	 * @return void
	 */
	public static function maybe_schedule() {

		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		// Spread the first run over the coming hour rather than firing it
		// on the very next request, so activating the plugin never costs a
		// loopback request in the middle of a page load.
		wp_schedule_event( time() + wp_rand( 5 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS ), 'daily', self::CRON_HOOK );
	}

	/**
	 * Run the very first probe from the admin, rather than waiting for the
	 * scheduled one.
	 *
	 * The daily event is scheduled for some point in the next hour, and
	 * WP-Cron only fires on an actual request, so a freshly installed or
	 * freshly updated plugin would otherwise have nothing to report for a
	 * while - which reads exactly like the feature not working at all.
	 *
	 * Only ever fills the first result, only for a user who could act on
	 * it, and only one request at a time. Every refresh after that is the
	 * scheduled event's job, or the Site Health test's.
	 *
	 * @return void
	 */
	public static function maybe_run_first_check() {

		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$result = self::get_result();

		if ( (int) $result['checked'] > 0 ) {
			return;
		}

		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return;
		}

		set_transient( self::LOCK_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS );

		self::run_check();
	}

	/**
	 * Drop the scheduled probe. Called on deactivation and on uninstall.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_transient( self::LOCK_TRANSIENT );
	}

	/**
	 * Probe the plugin's own REST route over a loopback request and store
	 * the verdict.
	 *
	 * Three outcomes are possible, and the difference between the last two
	 * matters a great deal:
	 *
	 * - `ok`      - the route answered, everything works.
	 * - `blocked` - the route answered with a REST level 401/403, meaning
	 *               the REST API itself turned the request away. This is
	 *               the only outcome that gets an administrator's attention.
	 * - `unknown` - the request never got a usable answer (loopback
	 *               disabled on the host, a timeout, HTTP auth on a staging
	 *               site, a WAF returning HTML). Plenty of perfectly
	 *               healthy sites cannot make loopback requests at all, so
	 *               this outcome stays silent on purpose.
	 *
	 * @return array Stored result.
	 */
	public static function run_check() {

		$response = wp_remote_get(
			add_query_arg(
				self::PROBE_ARG,
				'1',
				rest_url( Rest_Controller::REST_NAMESPACE . Rest_Controller::REST_ROUTE )
			),
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'headers'     => array( 'Accept' => 'application/json' ),
				// An empty cookie jar is the whole point: the probe has to
				// look exactly like the signed out request the browser
				// makes from assets/js/frontend-refresh.js, which sends
				// `credentials: 'omit'` so the response stays publicly
				// cacheable.
				'cookies'     => array(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::store( 'unknown', 0, '', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code ) {
			return self::store( 'ok', $code, '', '' );
		}

		// A 401 or 403 only counts as a REST API lockdown when the answer
		// is a REST error object. A bare 401 with an HTML body is HTTP auth
		// or a firewall in front of WordPress, which is not something this
		// plugin should be nagging anybody about.
		if ( in_array( $code, array( 401, 403 ), true ) && is_array( $body ) && isset( $body['code'] ) ) {
			return self::store(
				'blocked',
				$code,
				(string) $body['code'],
				isset( $body['message'] ) ? (string) $body['message'] : ''
			);
		}

		return self::store( 'unknown', $code, '', '' );
	}

	/**
	 * Get the stored probe result, optionally probing again first.
	 *
	 * @param bool $refresh_if_stale Re-run the probe when the stored result
	 *                               is older than FRESH_FOR.
	 *
	 * @return array Result, with at least a `status` key.
	 */
	public static function get_result( $refresh_if_stale = false ) {

		$result = get_option( self::OPTION_NAME );

		if ( ! is_array( $result ) || ! isset( $result['status'] ) ) {
			$result = array(
				'status'     => 'unknown',
				'http_code'  => 0,
				'rest_code'  => '',
				'message'    => '',
				'checked'    => 0,
				'first_seen' => 0,
			);
		}

		if ( $refresh_if_stale && ( time() - (int) $result['checked'] ) > self::FRESH_FOR ) {
			$result = self::run_check();
		}

		return $result;
	}

	/**
	 * Store a probe result.
	 *
	 * @param string $status    One of `ok`, `blocked`, `unknown`.
	 * @param int    $http_code HTTP status code the probe got back.
	 * @param string $rest_code REST error code, when the body carried one.
	 * @param string $message   REST error message, or the transport error.
	 *
	 * @return array Stored result.
	 */
	private static function store( $status, $http_code, $rest_code, $message ) {

		$previous = get_option( self::OPTION_NAME );

		$first_seen = 0;

		if ( 'blocked' === $status ) {
			// Keep the timestamp of the moment this lockdown was first
			// noticed, so an administrator's dismissal sticks to it and
			// does not silence a different one months later.
			$first_seen = ( is_array( $previous ) && ! empty( $previous['first_seen'] ) && 'blocked' === $previous['status'] )
				? (int) $previous['first_seen']
				: time();
		}

		$result = array(
			'status'     => $status,
			'http_code'  => (int) $http_code,
			'rest_code'  => $rest_code,
			'message'    => $message,
			'checked'    => time(),
			'first_seen' => $first_seen,
		);

		update_option( self::OPTION_NAME, $result, false );

		return $result;
	}

	/**
	 * Whether the current user should be shown the notice on this screen.
	 *
	 * @return bool
	 */
	private static function should_render_notice() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		// Screens an administrator lands on while actually dealing with
		// this plugin, plus the dashboard. Everywhere else stays quiet.
		if ( ! $screen instanceof \WP_Screen || ! in_array( $screen->id, array( 'dashboard', 'widgets', 'plugins' ), true ) ) {
			return false;
		}

		$result = self::get_result();

		if ( 'blocked' !== $result['status'] ) {
			return false;
		}

		$dismissed = (int) get_user_meta( get_current_user_id(), self::USER_META, true );

		return (int) $result['first_seen'] > $dismissed;
	}

	/**
	 * Render the admin notice.
	 *
	 * @return void
	 */
	public static function maybe_render_notice() {

		if ( ! self::should_render_notice() ) {
			return;
		}

		$result = self::get_result();

		$dismiss_url = wp_nonce_url(
			add_query_arg( 'action', self::DISMISS_ACTION, admin_url( 'admin-post.php' ) ),
			self::DISMISS_ACTION
		);

		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Time Machine:', 'time-machine' ); ?></strong>
				<?php
				printf(
					/* translators: 1: REST route, 2: HTTP status code, 3: REST error code returned by the site. */
					esc_html__( 'The REST API on this site turns away signed out visitors, so the %1$s route answers with HTTP %2$d (%3$s). The lists themselves keep working, but every visit logs a failed request in the browser console.', 'time-machine' ),
					'<code>' . esc_html( Rest_Controller::REST_NAMESPACE . Rest_Controller::REST_ROUTE ) . '</code>',
					(int) $result['http_code'],
					'<code>' . esc_html( $result['rest_code'] ) . '</code>'
				);
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: opening link tag to the Site Health screen, 2: closing link tag. */
					esc_html__( 'Either allow this one read only route through the restriction, or switch the front end refresh off. %1$sSite Health%2$s has both, with the code to copy.', 'time-machine' ),
					'<a href="' . esc_url( admin_url( 'site-health.php' ) ) . '">',
					'</a>'
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss this notice', 'time-machine' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle the notice's dismiss link.
	 *
	 * @return void
	 */
	public static function handle_dismiss() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'time-machine' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::DISMISS_ACTION );

		$result = self::get_result();

		update_user_meta( get_current_user_id(), self::USER_META, (int) $result['first_seen'] );

		$referer = wp_get_referer();

		wp_safe_redirect( $referer ? $referer : admin_url() );

		exit;
	}

	/**
	 * Add the Site Health test.
	 *
	 * @param array $tests Registered Site Health tests.
	 *
	 * @return array
	 */
	public static function register_site_health_test( $tests ) {

		$tests['direct']['time_machine_rest'] = array(
			'label' => __( 'Time Machine front end refresh', 'time-machine' ),
			'test'  => array( __CLASS__, 'site_health_test' ),
		);

		return $tests;
	}

	/**
	 * Site Health test callback.
	 *
	 * @return array
	 */
	public static function site_health_test() {

		$result = self::get_result( true );

		$test = array(
			'label'       => __( 'The Time Machine refresh route is reachable', 'time-machine' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Time Machine', 'time-machine' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Time Machine re-fetches its list after the page has loaded, so a cached page still shows the right day. The route it uses answered as expected.', 'time-machine' ) . '</p>',
			'actions'     => '',
			'test'        => 'time_machine_rest',
		);

		if ( 'unknown' === $result['status'] ) {

			$test['label']       = __( 'The Time Machine refresh route could not be checked', 'time-machine' );
			$test['status']      = 'recommended';
			$test['description'] = '<p>' . esc_html__( 'The check could not reach this site over a loopback request, which is common on hosts that block them. This says nothing about whether the route works for visitors; check the browser console on a page holding a Time Machine list if you want to be sure.', 'time-machine' ) . '</p>';

			return $test;
		}

		if ( 'blocked' !== $result['status'] ) {
			return $test;
		}

		$test['label']  = __( 'The Time Machine refresh route is blocked by a REST API restriction', 'time-machine' );
		$test['status'] = 'recommended';

		$description = '<p>' . sprintf(
			/* translators: 1: REST route, 2: HTTP status code, 3: REST error code returned by the site. */
			esc_html__( 'Something on this site closes the REST API to signed out visitors: the %1$s route answers with HTTP %2$d (%3$s). That filter runs before a route can state that it is public, so Time Machine cannot exempt itself.', 'time-machine' ),
			'<code>' . esc_html( Rest_Controller::REST_NAMESPACE . Rest_Controller::REST_ROUTE ) . '</code>',
			(int) $result['http_code'],
			'<code>' . esc_html( $result['rest_code'] ) . '</code>'
		) . '</p>';

		$description .= '<p>' . esc_html__( 'Nothing is broken for visitors: the list is rendered on the server and stays on screen. The only cost is a failed request logged in the browser console on every page that shows a list.', 'time-machine' ) . '</p>';

		$description .= '<p>' . esc_html__( 'This route is read only and returns the same published titles, excerpts and links the page already shows, so it is safe to let through. Add this to a must-use plugin or to the theme functions.php:', 'time-machine' ) . '</p>';

		$description .= '<pre><code>' . esc_html( self::get_allow_snippet() ) . '</code></pre>';

		$description .= '<p>' . sprintf(
			/* translators: %s: name of a PHP filter hook. */
			esc_html__( 'If you would rather drop the refresh altogether, return false from the %s filter instead. The lists then render once, server side, exactly as they did before this feature existed.', 'time-machine' ),
			'<code>time_machine_frontend_refresh</code>'
		) . '</p>';

		$test['description'] = $description;

		return $test;
	}

	/**
	 * The snippet offered to administrators whose site blocks the REST API.
	 *
	 * Kept as one string so the Site Health test, the readme and the
	 * documentation cannot drift apart.
	 *
	 * @return string
	 */
	public static function get_allow_snippet() {

		$namespace = Rest_Controller::REST_NAMESPACE;

		return "add_filter(
	'rest_authentication_errors',
	function ( \$result ) {

		if ( ! is_wp_error( \$result ) ) {
			return \$result;
		}

		\$route = isset( \$GLOBALS['wp']->query_vars['rest_route'] )
			? ltrim( \$GLOBALS['wp']->query_vars['rest_route'], '/' )
			: '';

		if ( 0 === strpos( \$route, '{$namespace}/' ) ) {
			return null;
		}

		return \$result;
	},
	99
);";
	}
}
