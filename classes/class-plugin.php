<?php
/**
 * Main plugin bootstrap class.
 *
 * @package TimeMachine
 */

namespace TechWebUX\TimeMachine;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 *
 * Bootstraps the plugin, exposes default settings, and registers the
 * widget, the shortcode and the block.
 */
class Plugin {

	/**
	 * Allowed values for the `range` setting (widget instance / shortcode attribute).
	 *
	 * @var string[]
	 */
	const ALLOWED_RANGES = array( 'none', 'days', 'weeks', 'months' );

	/**
	 * Allowed values for the `direction` setting (widget instance / shortcode attribute).
	 *
	 * @var string[]
	 */
	const ALLOWED_DIRECTIONS = array( 'before', 'after', 'both' );

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance, creating it on first call.
	 *
	 * @return Plugin
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {

		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_update' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Register the Time Machine widget.
	 *
	 * @return void
	 */
	public function register_widget() {
		register_widget( __NAMESPACE__ . '\\Widget' );
	}

	/**
	 * Register the `[time_machine]` shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode() {
		Shortcode::register();
	}

	/**
	 * Register the `time-machine/time-machine` block.
	 *
	 * @return void
	 */
	public function register_block() {
		Block::register();
	}

	/**
	 * Run any pending data migrations for existing installs.
	 *
	 * @return void
	 */
	public function maybe_update() {
		Updater::maybe_update();
	}

	/**
	 * Register the front-end stylesheet and enqueue it only on requests
	 * where it is actually going to be used.
	 *
	 * This only covers the widget and the shortcode. The block ships and
	 * loads its own copy of the same rules (see
	 * blocks/time-machine/src/style.scss, pulled in via block.json's
	 * `style` field), because that is the mechanism WordPress uses to also
	 * load styles inside the post editor's iframe for ServerSideRender
	 * previews - a plain `wp_enqueue_style()` call here only ever reaches
	 * the parent admin document, never the iframe.
	 *
	 * The stylesheet is registered unconditionally (cheap, no output) and
	 * only enqueued when a Time Machine widget or shortcode is actually
	 * present, which has to be checked here on `wp_enqueue_scripts` since
	 * that is the last point before `wp_head` where styles reliably print.
	 *
	 * @return void
	 */
	public function enqueue_styles() {

		wp_register_style(
			'time-machine',
			TIME_MACHINE_URL . 'assets/css/time-machine.css',
			array(),
			TIME_MACHINE_VERSION
		);

		if ( self::current_request_needs_styles() ) {
			wp_enqueue_style( 'time-machine' );
		}
	}

	/**
	 * Whether the current request is expected to render a Time Machine
	 * widget or shortcode, and therefore needs the stylesheet.
	 *
	 * @return bool
	 */
	private static function current_request_needs_styles() {

		if ( is_active_widget( false, false, 'time-machine', true ) ) {
			return true;
		}

		if ( is_singular() ) {

			$post = get_post();

			if ( $post instanceof \WP_Post && has_shortcode( $post->post_content, Shortcode::TAG ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse a loosely typed value (as received from a form field, a
	 * shortcode attribute, or a REST query arg) into a boolean.
	 *
	 * Accepts 1/0, true/false, yes/no, on/off (case-insensitive), matching
	 * the common conventions used across WordPress shortcode attributes.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return bool
	 */
	public static function string_to_bool( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Convert a boolean into the '1'/'0' string form used as a default by
	 * shortcode_atts() (which only ever works with strings).
	 *
	 * @param bool $value Value to convert.
	 *
	 * @return string
	 */
	public static function bool_to_string( $value ) {
		return $value ? '1' : '0';
	}

	/**
	 * Get default widget settings, merged with the legacy `time_machine` option.
	 *
	 * @return array
	 */
	public static function get_defaults() {

		$defaults = array(
			'title'              => __( 'Time Machine', 'time-machine' ),
			'message'            => __( 'No articles were published on this day in previous years.', 'time-machine' ),
			'posts'              => 10,
			'showifno'           => false,
			'private'            => false,
			'exclude_pages'      => false,
			'exclude_current'    => false,
			'display_commentnum' => false,
			'range'              => 'none',
			'offset'             => '1',
			'direction'          => 'both',
			'excerpt'            => false,
			'excerpt_cut'        => false,
			'excerpt_length'     => 25,
		);

		$options = wp_parse_args( get_option( 'time_machine' ), $defaults );

		return $options;
	}
}
