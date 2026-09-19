<?php
/**
 * Front-end auto-refresh attributes for the Time Machine post listing.
 *
 * @package TimeMachine
 */

namespace TechWebUX\TimeMachine;

defined( 'ABSPATH' ) || exit;

/**
 * Class Refresh
 *
 * Builds the `data-time-machine-*` attributes used by
 * assets/js/frontend-refresh.js to fetch a current copy of a Time Machine
 * list from Rest_Controller after a full page cache plugin (WP Rocket, WP
 * Fastest Cache, W3 Total Cache and similar) has served a stale copy of the
 * page, and tracks whether that script is actually needed on the current
 * request.
 */
class Refresh {

	/**
	 * Subset of the settings needed to re-render the list on the front end,
	 * via Rest_Controller. Kept as an explicit list (rather than sending
	 * every setting) so title - which never changes the list itself - is
	 * never round tripped, and so a new private/internal setting added
	 * later does not leak into a `data-time-machine-*` attribute by accident.
	 *
	 * @var string[]
	 */
	const ARGS = array(
		'message',
		'posts',
		'private',
		'exclude_pages',
		'exclude_current',
		'display_commentnum',
		'range',
		'offset',
		'direction',
		'excerpt',
		'excerpt_cut',
		'excerpt_length',
	);

	/**
	 * Keys from ARGS that hold a boolean, and therefore need converting to
	 * the '1'/'0' strings Plugin::string_to_bool() reads back on the REST
	 * side, rather than PHP's "" / "1" (true) versus "" (false, since
	 * (string) false is "").
	 *
	 * @var string[]
	 */
	const BOOLEAN_ARGS = array(
		'private',
		'exclude_pages',
		'exclude_current',
		'display_commentnum',
		'excerpt',
		'excerpt_cut',
	);

	/**
	 * Whether get_attribute() has added its attributes at least once during
	 * the current request, checked by maybe_enqueue() to decide whether the
	 * front-end refresh script is actually needed.
	 *
	 * A flag set as a side effect of rendering, rather than a guess made
	 * ahead of time from post content or is_active_widget(), because a
	 * Time Machine block can just as well come from a block based widget
	 * area or a template part, neither of which shows up in post content.
	 *
	 * @var bool
	 */
	private static $needed = false;

	/**
	 * Hook the front-end auto-refresh script's registration and conditional
	 * enqueue.
	 *
	 * @return void
	 */
	public static function register() {

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_script' ) );

		// Priority 1: early enough that, when needed, the script still
		// reaches the queue wp_print_footer_scripts() reads at the default
		// priority (20) on this same 'wp_footer' hook.
		add_action( 'wp_footer', array( __CLASS__, 'maybe_enqueue' ), 1 );
	}

	/**
	 * Register (but do not yet enqueue) the front-end auto-refresh script.
	 *
	 * Registered unconditionally and cheaply, same as the stylesheet in
	 * Plugin::enqueue_styles(). Whether it actually gets enqueued is decided
	 * in maybe_enqueue(), once it is known whether anything on the page
	 * actually needed it.
	 *
	 * @return void
	 */
	public static function register_script() {

		wp_register_script(
			'time-machine-frontend-refresh',
			TIME_MACHINE_URL . 'assets/js/frontend-refresh.js',
			array(),
			TIME_MACHINE_VERSION,
			true
		);
	}

	/**
	 * Enqueue the front-end auto-refresh script, but only if a Time Machine
	 * widget, block or shortcode actually rendered its refresh attributes
	 * earlier in this request (see needed()).
	 *
	 * Deciding this from the actual render output, rather than guessing
	 * ahead of time from is_active_widget() or post content, is what makes
	 * this work regardless of where the block or widget lives - a classic
	 * sidebar, a block based widget area, a template part, a pattern, or
	 * post content all reach get_attribute() the same way.
	 *
	 * @return void
	 */
	public static function maybe_enqueue() {

		if ( ! self::needed() ) {
			return;
		}

		wp_enqueue_script( 'time-machine-frontend-refresh' );

		wp_localize_script(
			'time-machine-frontend-refresh',
			'timeMachineRefresh',
			array(
				'root' => esc_url_raw( rest_url( Rest_Controller::REST_NAMESPACE . Rest_Controller::REST_ROUTE ) ),
			)
		);
	}

	/**
	 * Build the `data-time-machine-*` attributes added to the `<ul>`.
	 *
	 * Time Machine's own query cache changes once a day (see
	 * Content_Generator::get_articles()), but when a full page cache plugin
	 * caches the whole page, the rendered markup can stay stale for far
	 * longer than that. The attributes carry the settings needed to
	 * re-render just this list, one plain `data-time-machine-<setting>`
	 * attribute per setting rather than a single JSON blob, so
	 * assets/js/frontend-refresh.js can fetch a current copy from
	 * Rest_Controller after the cached page has loaded and swap it in,
	 * without touching the page cache itself.
	 *
	 * @param array $args Settings for the Time Machine instance being rendered.
	 *
	 * @return string Empty string, or a leading-space-prefixed run of HTML attributes.
	 */
	public static function get_attribute( array $args ) {

		/**
		 * Filters whether a Time Machine instance gets the front end
		 * auto-refresh attributes (and, in turn, is refetched after load).
		 *
		 * @param bool  $enabled Whether to add the attributes. Default true.
		 * @param array $args    Settings for this Time Machine instance.
		 */
		if ( ! apply_filters( 'time_machine_frontend_refresh', true, $args ) ) {
			return '';
		}

		self::$needed = true;

		$html = ' data-time-machine-refresh="1"';

		foreach ( self::ARGS as $key ) {

			$value = $args[ $key ];

			if ( in_array( $key, self::BOOLEAN_ARGS, true ) ) {
				$value = $value ? '1' : '0';
			}

			$attribute = 'data-time-machine-' . str_replace( '_', '-', $key );

			$html .= ' ' . $attribute . '="' . esc_attr( $value ) . '"';
		}

		return $html;
	}

	/**
	 * Whether any Time Machine instance has rendered its refresh attributes
	 * during the current request.
	 *
	 * @return bool
	 */
	public static function needed() {
		return self::$needed;
	}
}
