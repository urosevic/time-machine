<?php
/**
 * Data migrations between plugin versions.
 *
 * @package TimeMachine
 */

namespace TechWebUX\TimeMachine;

defined( 'ABSPATH' ) || exit;

/**
 * Class Updater
 *
 * Runs one-time migrations of stored data when the plugin's saved data
 * format changes between versions, so existing installs keep working
 * (with their existing settings) after an update.
 *
 * The target version is TIME_MACHINE_DB_VERSION (an incrementing integer,
 * defined in the main plugin file). Each version bump gets its own
 * update_{n}() method here; maybe_update() runs any that haven't run yet,
 * in order, and records progress after each one.
 */
class Updater {

	/**
	 * Option name used to track the installed data format version.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'time_machine_db_version';

	/**
	 * Option name under which the Time Machine widget stores its instances.
	 *
	 * @var string
	 */
	const WIDGET_OPTION_NAME = 'widget_time-machine';

	/**
	 * Run any pending migrations, in order, recording progress as it goes.
	 *
	 * @return void
	 */
	public static function maybe_update() {

		$installed_version = (int) get_option( self::OPTION_NAME, 0 );
		$target_version    = TIME_MACHINE_DB_VERSION;

		while ( $installed_version < $target_version ) {

			++$installed_version;

			$method = 'update_' . $installed_version;

			if ( method_exists( __CLASS__, $method ) ) {
				call_user_func( array( __CLASS__, $method ) );
			}

			// Record progress after each step, so a later step failing
			// doesn't force earlier ones to run again.
			update_option( self::OPTION_NAME, $installed_version );
		}
	}

	/**
	 * DB update #1: rename legacy widget setting keys to their current names,
	 * and remap the removed 'hours' offset unit to 'days'.
	 *
	 * - `rangenum` (used since the plugin's original 2009 release) -> `offset`
	 * - `rangetype` -> `direction`
	 * - `range` value `hours` (dropped from the UI) -> `days`
	 *
	 * @return void
	 */
	private static function update_1() {
		self::rename_widget_setting( 'rangenum', 'offset' );
		self::rename_widget_setting( 'rangetype', 'direction' );
		self::remap_widget_value( 'range', 'hours', 'days' );
	}

	/**
	 * DB update #2: drop the `excerpt_before` and `excerpt_after` widget
	 * settings. The excerpt is now always wrapped in a `<span class="excerpt">`
	 * by Content_Generator, so it can be styled through CSS instead.
	 *
	 * @return void
	 */
	private static function update_2() {
		self::remove_widget_setting( 'excerpt_before' );
		self::remove_widget_setting( 'excerpt_after' );
	}

	/**
	 * DB update #3: switch the `excerpt_length` widget setting from a
	 * character count to a word count, now that Content_Generator trims
	 * excerpts with wp_trim_words() instead of a manual character cut.
	 *
	 * Also converts the same setting in the legacy `time_machine` option, in
	 * case an install still carries one from before per-widget settings
	 * existed - it is otherwise no longer written to by the current UI.
	 *
	 * @return void
	 */
	private static function update_3() {

		self::transform_widget_value( 'excerpt_length', array( __CLASS__, 'characters_to_words' ) );

		$legacy_settings = get_option( 'time_machine' );

		if ( is_array( $legacy_settings ) && array_key_exists( 'excerpt_length', $legacy_settings ) ) {
			$legacy_settings['excerpt_length'] = self::characters_to_words( $legacy_settings['excerpt_length'] );
			update_option( 'time_machine', $legacy_settings );
		}
	}

	/**
	 * Approximate a character count as a word count.
	 *
	 * There is no way to recover the exact word count an old `excerpt_length`
	 * characters value used to cut off at - only the stored number, not the
	 * actual post content it used to apply to, survives to run this
	 * migration. Average English word length is around 4.7 characters;
	 * adding the trailing space between words puts a typical running word at
	 * roughly 6 characters, the same rule of thumb typing speed (WPM) tests
	 * use. Dividing by 6 turns the old setting into a comparable word count,
	 * rounded to the nearest word and floored at 1.
	 *
	 * @param int $characters Previous excerpt_length value, in characters.
	 *
	 * @return int Equivalent excerpt_length value, in words.
	 */
	private static function characters_to_words( $characters ) {
		return max( 1, (int) round( absint( $characters ) / 6 ) );
	}

	/**
	 * Remove a setting key from all saved Time Machine widget instances.
	 *
	 * @param string $key Setting key to remove.
	 *
	 * @return void
	 */
	private static function remove_widget_setting( $key ) {

		$widget_instances = get_option( self::WIDGET_OPTION_NAME );

		if ( empty( $widget_instances ) || ! is_array( $widget_instances ) ) {
			return;
		}

		$updated = false;

		foreach ( $widget_instances as $instance_key => $instance ) {

			if ( ! is_array( $instance ) || ! array_key_exists( $key, $instance ) ) {
				continue;
			}

			unset( $widget_instances[ $instance_key ][ $key ] );

			$updated = true;
		}

		if ( $updated ) {
			update_option( self::WIDGET_OPTION_NAME, $widget_instances );
		}
	}

	/**
	 * Rename a setting key in all saved Time Machine widget instances.
	 *
	 * Carries the existing value across to the new key (unless already
	 * present) so nobody's saved settings reset after an update.
	 *
	 * @param string $old_key Previous setting key.
	 * @param string $new_key New setting key.
	 *
	 * @return void
	 */
	private static function rename_widget_setting( $old_key, $new_key ) {

		$widget_instances = get_option( self::WIDGET_OPTION_NAME );

		if ( empty( $widget_instances ) || ! is_array( $widget_instances ) ) {
			return;
		}

		$updated = false;

		foreach ( $widget_instances as $key => $instance ) {

			if ( ! is_array( $instance ) || ! array_key_exists( $old_key, $instance ) ) {
				continue;
			}

			if ( ! array_key_exists( $new_key, $instance ) ) {
				$widget_instances[ $key ][ $new_key ] = $instance[ $old_key ];
			}

			unset( $widget_instances[ $key ][ $old_key ] );

			$updated = true;
		}

		if ( $updated ) {
			update_option( self::WIDGET_OPTION_NAME, $widget_instances );
		}
	}

	/**
	 * Replace one specific stored value with another for a given setting key,
	 * across all saved Time Machine widget instances.
	 *
	 * @param string $key       Setting key to check.
	 * @param string $old_value Value to replace.
	 * @param string $new_value Replacement value.
	 *
	 * @return void
	 */
	private static function remap_widget_value( $key, $old_value, $new_value ) {

		$widget_instances = get_option( self::WIDGET_OPTION_NAME );

		if ( empty( $widget_instances ) || ! is_array( $widget_instances ) ) {
			return;
		}

		$updated = false;

		foreach ( $widget_instances as $instance_key => $instance ) {

			if ( ! is_array( $instance ) || ! array_key_exists( $key, $instance ) ) {
				continue;
			}

			if ( $old_value !== $instance[ $key ] ) {
				continue;
			}

			$widget_instances[ $instance_key ][ $key ] = $new_value;
			$updated                                   = true;
		}

		if ( $updated ) {
			update_option( self::WIDGET_OPTION_NAME, $widget_instances );
		}
	}

	/**
	 * Recalculate a setting's stored value, across all saved Time Machine
	 * widget instances, through a callback.
	 *
	 * @param string   $key      Setting key to recalculate.
	 * @param callable $callback Receives the current value, returns the new one.
	 *
	 * @return void
	 */
	private static function transform_widget_value( $key, callable $callback ) {

		$widget_instances = get_option( self::WIDGET_OPTION_NAME );

		if ( empty( $widget_instances ) || ! is_array( $widget_instances ) ) {
			return;
		}

		$updated = false;

		foreach ( $widget_instances as $instance_key => $instance ) {

			if ( ! is_array( $instance ) || ! array_key_exists( $key, $instance ) ) {
				continue;
			}

			$widget_instances[ $instance_key ][ $key ] = call_user_func( $callback, $instance[ $key ] );
			$updated                                   = true;
		}

		if ( $updated ) {
			update_option( self::WIDGET_OPTION_NAME, $widget_instances );
		}
	}
}
