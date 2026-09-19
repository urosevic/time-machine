<?php
/**
 * Cache wrapper shared by the plugin's content generation logic.
 *
 * @package TimeMachine
 */

namespace TechWebUX\TimeMachine;

defined( 'ABSPATH' ) || exit;

/**
 * Class Cache
 *
 * Wraps the object cache/transient storage used to cache the Time Machine
 * query results, and the version based invalidation scheme built on top of
 * it, so Content_Generator only has to deal with what to cache, not how.
 */
class Cache {

	/**
	 * Cache group used for wp_cache_*() calls, and as part of the transient
	 * name fallback.
	 *
	 * @var string
	 */
	const GROUP = 'time_machine';

	/**
	 * Option name storing the cache-busting version number.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'time_machine_cache_version';

	/**
	 * Build a cache key from a prefix and the values that determine whether
	 * a previously cached entry is still valid.
	 *
	 * The version prefix used by flush() to invalidate the cache in bulk is
	 * added separately, inside get()/set() - $parts here only needs to
	 * cover what distinguishes one cached entry from another within the
	 * same prefix (a given day, a given set of settings, and so on).
	 *
	 * @param string $prefix Key prefix, unique to what is being cached.
	 * @param array  $parts  Values the key should change with.
	 *
	 * @return string
	 */
	public static function key( $prefix, array $parts = array() ) {
		return $prefix . '_' . md5( wp_json_encode( $parts ) );
	}

	/**
	 * Read a value from the cache.
	 *
	 * Uses the object cache when a persistent one is configured. Without a
	 * persistent object cache, wp_cache_*() only lives for the current
	 * request and never survives across page loads, so a transient is used
	 * instead.
	 *
	 * @param string $key Cache key.
	 *
	 * @return mixed|false Cached value, or false on a cache miss.
	 */
	public static function get( $key ) {

		$key = self::get_version() . '_' . $key;

		if ( wp_using_ext_object_cache() ) {
			return wp_cache_get( $key, self::GROUP );
		}

		return get_transient( self::GROUP . '_' . $key );
	}

	/**
	 * Write a value to the cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Cache lifetime in seconds.
	 *
	 * @return void
	 */
	public static function set( $key, $value, $ttl ) {

		$key = self::get_version() . '_' . $key;

		if ( wp_using_ext_object_cache() ) {
			wp_cache_set( $key, $value, self::GROUP, $ttl );
			return;
		}

		set_transient( self::GROUP . '_' . $key, $value, $ttl );
	}

	/**
	 * Get the current cache-busting version number.
	 *
	 * @return int
	 */
	private static function get_version() {
		return (int) get_option( self::VERSION_OPTION, 1 );
	}

	/**
	 * Invalidate every cached entry.
	 *
	 * Bumps the cache-busting version number instead of hunting down and
	 * deleting individual transients/object cache entries: every key built
	 * by get()/set() includes the version, so a bump orphans all of them at
	 * once. Orphaned entries are never read again and are cleared out
	 * naturally once their own TTL elapses.
	 *
	 * @return void
	 */
	public static function flush() {
		update_option( self::VERSION_OPTION, self::get_version() + 1, false );
	}

	/**
	 * Hook callback: invalidate the cache when a post or page is saved.
	 *
	 * Hooked on save_post_post and save_post_page (see Plugin::__construct()),
	 * which covers new posts, edits to existing ones, and status transitions
	 * made through the post editor - including scheduled posts going live
	 * and posts being trashed, both of which go through wp_update_post()
	 * and therefore also fire this hook.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function maybe_flush( $post_id ) {

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::flush();
	}
}
