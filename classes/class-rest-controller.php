<?php
/**
 * REST API endpoint used to refresh the post list on the front end.
 *
 * @package TimeMachine
 */

namespace TechWebUX\TimeMachine;

defined( 'ABSPATH' ) || exit;

/**
 * Class Rest_Controller
 *
 * Exposes a small, public, read only REST route that renders a fresh copy
 * of the `<ul class="time-machine-list">` markup for a given set of
 * settings. Used by assets/js/frontend-refresh.js so pages served from a
 * full page cache plugin still show current content, without touching
 * that cache at all - see Refresh::get_attribute().
 */
class Rest_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'time-machine/v1';

	/**
	 * REST route, relative to REST_NAMESPACE.
	 *
	 * @var string
	 */
	const REST_ROUTE = '/list';

	/**
	 * Hook the route registration.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public static function register_routes() {

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_list' ),
				// Public and read only by design: the response contains the
				// exact same published post titles, excerpts and links a
				// signed out visitor already gets in the page's own HTML,
				// just re-rendered for the current day.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * REST callback: render the post list for the settings passed as query args.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_list( \WP_REST_Request $request ) {

		// Rest_Health probes this exact URL over a loopback request once a
		// day, to find out whether a site wide REST API restriction is
		// turning signed out visitors away (see Rest_Health::run_check()).
		// Answer that probe without running the query or touching the
		// day's cache: all it needs to learn is whether it got this far.
		if ( null !== $request->get_param( Rest_Health::PROBE_ARG ) ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}

		$defaults = Plugin::get_defaults();

		$range     = (string) $request->get_param( 'range' );
		$direction = (string) $request->get_param( 'direction' );

		$settings = array(
			'message'            => sanitize_text_field( (string) $request->get_param( 'message' ) ),
			'posts'              => absint( $request->get_param( 'posts' ) ),
			'private'            => Plugin::string_to_bool( $request->get_param( 'private' ) ),
			'exclude_pages'      => Plugin::string_to_bool( $request->get_param( 'exclude_pages' ) ),
			'exclude_current'    => Plugin::string_to_bool( $request->get_param( 'exclude_current' ) ),
			'display_commentnum' => Plugin::string_to_bool( $request->get_param( 'display_commentnum' ) ),
			'range'              => in_array( $range, Plugin::ALLOWED_RANGES, true ) ? $range : $defaults['range'],
			'offset'             => absint( $request->get_param( 'offset' ) ),
			'direction'          => in_array( $direction, Plugin::ALLOWED_DIRECTIONS, true ) ? $direction : $defaults['direction'],
			'excerpt'            => Plugin::string_to_bool( $request->get_param( 'excerpt' ) ),
			'excerpt_cut'        => Plugin::string_to_bool( $request->get_param( 'excerpt_cut' ) ),
			'excerpt_length'     => absint( $request->get_param( 'excerpt_length' ) ),
		);

		$generator = new Content_Generator( $settings );

		$response = rest_ensure_response( array( 'html' => $generator->get_list_html() ) );

		// This response is publicly cacheable, but only until the list it
		// carries changes, which is the moment the UTC date rolls over.
		$response->header( 'Cache-Control', 'public, max-age=' . self::get_max_age() );

		return $response;
	}

	/**
	 * Seconds left until the response stops being accurate.
	 *
	 * The list is keyed to the current UTC date (see
	 * Content_Generator::get_articles()) and cannot change until that date
	 * does, so the browser and CDN copy should expire exactly then. A flat
	 * day would not: a visitor whose browser stored the response at 23:00
	 * would keep being handed yesterday's list for the next 24 hours -
	 * precisely the staleness the front end refresh exists to undo, caused
	 * by the refresh itself.
	 *
	 * A post saved during the day still cannot be pushed out of a cache
	 * this server does not control. Bounding the response at midnight does
	 * not fix that, it just stops it from lasting a full day beyond it.
	 *
	 * @return int
	 */
	private static function get_max_age() {

		$now      = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$midnight = $now->setTime( 0, 0, 0 )->modify( '+1 day' );

		return max( 0, $midnight->getTimestamp() - $now->getTimestamp() );
	}
}
