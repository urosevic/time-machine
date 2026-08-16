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
 * Bootstraps the plugin, exposes default widget settings, and registers
 * the Time Machine widget.
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
		add_action( 'plugins_loaded', array( $this, 'maybe_update' ) );
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
	 * Run any pending data migrations for existing installs.
	 *
	 * @return void
	 */
	public function maybe_update() {
		Updater::maybe_update();
	}

	/**
	 * Get default widget settings, merged with the legacy `time_machine` option.
	 *
	 * @return array
	 */
	public static function get_defaults() {

		$defaults = array(
			'title'              => __( 'Time Machine', 'time-machine' ),
			'message'            => __( 'No articles published on same day in past', 'time-machine' ),
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
			'excerpt_length'     => 150,
			'excerpt_before'     => '<br /><small><em>',
			'excerpt_after'      => '</em></small>',
		);

		$options = wp_parse_args( get_option( 'time_machine' ), $defaults );

		return $options;
	}
}
