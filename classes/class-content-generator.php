<?php
/**
 * Shared content generation for the Time Machine post listing.
 *
 * Builds the "published in the past" query and the resulting markup, so the
 * exact same logic can be reused by the widget, the Gutenberg block and
 * the shortcode.
 *
 * @package TimeMachine
 */

namespace TechWebUX\TimeMachine;

defined( 'ABSPATH' ) || exit;

/**
 * Class Content_Generator
 */
class Content_Generator {

	/**
	 * Normalized settings for this instance of the generator.
	 *
	 * @var array
	 */
	private $args;

	/**
	 * Constructor.
	 *
	 * @param array $args Settings, merged over the plugin defaults. Accepts the
	 *                     same keys as the widget instance array: title, message,
	 *                     posts, showifno, private, exclude_pages, exclude_current,
	 *                     display_commentnum, range, offset, direction, excerpt,
	 *                     excerpt_cut, excerpt_length.
	 */
	public function __construct( array $args = array() ) {
		$this->args = wp_parse_args( $args, Plugin::get_defaults() );
	}

	/**
	 * Get the normalized settings used by this generator instance.
	 *
	 * @return array
	 */
	public function get_args() {
		return $this->args;
	}

	/**
	 * Run the query and return the matching posts.
	 *
	 * Named get_articles() rather than get_posts() to avoid confusion with
	 * WordPress core's get_posts(), which this does not wrap or call.
	 *
	 * The query is built and prepared in this single method (rather than split
	 * across helper methods) so that phpcs/Plugin Check can statically verify
	 * that every dynamic value reaches the database through $wpdb->prepare().
	 *
	 * Results are cached for the rest of the day, since the query only ever
	 * changes once the calendar day rolls over (see Cache::key()), and
	 * invalidated early whenever a post or page is saved (see
	 * Cache::maybe_flush()).
	 *
	 * @return array Array of result objects (ID, post_title, post_date_gmt, post_excerpt, post_content, post_password, comment_count).
	 */
	public function get_articles() {

		global $wpdb;

		// Query against post_date_gmt (UTC) for a timezone-agnostic comparison.
		$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

		$cache_key = Cache::key( 'articles', array( $now->format( 'Y-m-d' ), $this->args ) );
		$cached    = Cache::get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$this_year = (int) $now->format( 'Y' );
		$last_year = $this_year;

		// post_content is only needed to build a fallback excerpt when
		// post_excerpt is empty, and post_password only to withhold that
		// fallback for protected posts (see get_item_html()), but the LIMIT
		// clause below keeps the result set small, so fetching both
		// unconditionally for every row is cheaper than a second query.
		$sql = "
			SELECT ID, post_title, post_date_gmt, post_excerpt, post_content, post_password, comment_count
			FROM $wpdb->posts
			WHERE post_status='publish'
		";

		$values = array();

		// Include pages?
		if ( $this->args['exclude_pages'] ) {
			$sql .= " AND post_type = 'post'";
		} else {
			$sql .= " AND post_type IN( 'post', 'page' )";
		}

		// Include private pages?
		if ( ! $this->args['private'] ) {
			$sql .= " AND `post_password` = ''";
		}

		// Exclude current year?
		if ( $this->args['exclude_current'] ) {
			$sql     .= ' AND CAST(`post_date_gmt` AS char) NOT LIKE %s';
			$values[] = $this_year . '-%';
		} else {
			// Increase last year for query.
			++$last_year;
		}

		// Offset.
		$sql .= ' AND ';

		if ( 'none' !== $this->args['range'] ) {

			$range_clauses = array();

			// Get through years since the oldest published post to current (or last) year.
			for ( $year = $this->get_oldest_post_year(); $year < $last_year; ++$year ) {

				// Define base day (current date except year, time dropped to midnight).
				// The offset is expressed in whole days/weeks/months, so the range only
				// ever needs day precision - keeping the current time-of-day out of it
				// means the same range is produced all day long, which keeps the query
				// cache friendly.
				$date_base = $now->setDate( $year, (int) $now->format( 'm' ), (int) $now->format( 'd' ) )->setTime( 0, 0, 0 );

				// Define theoretical before and after date range.
				$date_before = $date_base->modify( '-' . $this->args['offset'] . ' ' . $this->args['range'] );
				$date_after  = $date_base->modify( '+' . $this->args['offset'] . ' ' . $this->args['range'] );

				// Decide requested before and after date range.
				if ( 'before' === $this->args['direction'] ) {
					// Range only before.
					$date_start = $date_before->format( 'Y-m-d' );
					$date_end   = $date_base->format( 'Y-m-d' );
				} elseif ( 'after' === $this->args['direction'] ) {
					// Range only after.
					$date_start = $date_base->format( 'Y-m-d' );
					$date_end   = $date_after->format( 'Y-m-d' );
				} else {
					// Range before till after.
					$date_start = $date_before->format( 'Y-m-d' );
					$date_end   = $date_after->format( 'Y-m-d' );
				}

				// Append new range.
				$range_clauses[] = '`post_date_gmt` BETWEEN %s AND %s';
				$values[]        = $date_start . ' 00:00:00';
				$values[]        = $date_end . ' 23:59:59';
			}

			// No prior year to compare against (e.g. a brand new site, or one whose
			// oldest post is this year) - nothing can match, skip the query entirely.
			if ( empty( $range_clauses ) ) {
				Cache::set( $cache_key, array(), (int) apply_filters( 'time_machine_cache_ttl', DAY_IN_SECONDS ) );
				return array();
			}

			$sql .= '( ' . implode( ' OR ', $range_clauses ) . ' )';

		} else {
			// Single range.
			$sql     .= 'CAST(`post_date_gmt` AS char) LIKE %s';
			$values[] = '%-' . $now->format( 'm-d' ) . ' %';
		}

		// Most recent past year first, then further back - the WHERE clause
		// above can match several years at once (or, with an OR'd range
		// per year, scan them in whatever order the query planner picks),
		// so without this the result order is whatever MySQL finds
		// convenient rather than anything chronological.
		$sql .= ' ORDER BY `post_date_gmt` DESC';

		// Number of posts.
		$sql     .= ' LIMIT %d';
		$values[] = absint( $this->args['posts'] );

		// The WHERE clause above is assembled across a loop (variable number of
		// year ranges), which phpcs's static analysis cannot trace token by token.
		// Every dynamic value is nonetheless inserted only through %s/%d
		// placeholders and passed to $wpdb->prepare() here, before any query runs.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $sql, ...$values );

		// $sql is the string returned by $wpdb->prepare() directly above.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = (array) $wpdb->get_results( $sql, OBJECT );

		Cache::set( $cache_key, $posts, (int) apply_filters( 'time_machine_cache_ttl', DAY_IN_SECONDS ) );

		return $posts;
	}

	/**
	 * Get the year of the oldest published post, used as the lower bound for
	 * the offset year loop in get_articles() instead of a hardcoded year.
	 *
	 * Looking this up is one indexed-ish query, but there is no reason to run
	 * it on every request, so the result is cached separately from (and for
	 * much longer than) the article listing itself - it only changes if
	 * someone backdates a post before the current oldest one.
	 *
	 * @return int
	 */
	private function get_oldest_post_year() {

		$cache_key = 'oldest_year';
		$cached    = Cache::get( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$oldest_date = $wpdb->get_var(
			"SELECT MIN(post_date_gmt) FROM $wpdb->posts
			WHERE post_status = 'publish'
			AND post_type IN( 'post', 'page' )
			AND post_date_gmt > '0000-00-00 00:00:00'"
		);

		$oldest_year = $oldest_date
			? (int) substr( $oldest_date, 0, 4 )
			: (int) ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->format( 'Y' );

		/**
		 * Filters how long the oldest published post's year is cached for.
		 *
		 * @param int $ttl Cache lifetime in seconds. Default MONTH_IN_SECONDS.
		 */
		$ttl = (int) apply_filters( 'time_machine_oldest_year_cache_ttl', MONTH_IN_SECONDS );

		Cache::set( $cache_key, $oldest_year, $ttl );

		return $oldest_year;
	}

	/**
	 * Build the tooltip wrapping the heading, describing the active time range.
	 *
	 * @return array {
	 *     Tooltip markup, split around the heading text.
	 *
	 *     @type string $prefix Markup opening the tooltip span.
	 *     @type string $suffix Markup closing the tooltip span.
	 * }
	 */
	public function get_title_wrap() {

		if ( 'none' === $this->args['range'] ) {
			return array(
				'prefix' => '<span title="' . esc_attr__( "Articles published on today's day in past years", 'time-machine' ) . '">',
				'suffix' => '</span>',
			);
		}

		$prefix  = '<span title="';
		$prefix .= esc_attr__( 'Articles published', 'time-machine' ) . ' ' . $this->args['offset'] . ' ';

		switch ( $this->args['range'] ) {
			case 'days':
				$prefix .= esc_attr__( 'days', 'time-machine' );
				break;
			case 'weeks':
				$prefix .= esc_attr__( 'weeks', 'time-machine' );
				break;
			case 'months':
				$prefix .= esc_attr__( 'months', 'time-machine' );
				break;
		}

		$prefix .= ' ';

		switch ( $this->args['direction'] ) {
			case 'before':
				$prefix .= esc_attr__( 'before', 'time-machine' );
				break;
			case 'after':
				$prefix .= esc_attr__( 'after', 'time-machine' );
				break;
			default:
				$prefix .= esc_attr__( 'before and after', 'time-machine' );
				break;
		}

		$prefix .= ' ' . esc_attr__( "today's day in past years", 'time-machine' ) . '">';

		return array(
			'prefix' => $prefix,
			'suffix' => '</span>',
		);
	}

	/**
	 * Build the `<ul>` markup listing the matched posts.
	 *
	 * @param array|null $posts Optional. Pre-fetched posts, as returned by get_articles().
	 *                          When omitted, the query runs internally.
	 *
	 * @return string
	 */
	public function get_list_html( $posts = null ) {

		if ( null === $posts ) {
			$posts = $this->get_articles();
		}

		$html = '<ul class="time-machine-list"' . Refresh::get_attribute( $this->args ) . '>';

		if ( empty( $posts ) ) {

			$html .= esc_html( $this->args['message'] );

		} else {

			$timezone    = wp_timezone();
			$date_format = get_option( 'date_format' );

			foreach ( $posts as $post_row ) {
				$html .= $this->get_item_html( $post_row, $timezone, $date_format );
			}
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * Generate a plain-text excerpt from post content.
	 *
	 * Used as a fallback for posts with no manual excerpt, mirroring what
	 * WordPress core's get_the_excerpt()/wp_trim_excerpt() build in that
	 * situation: registered shortcodes are stripped, blocks not suited for
	 * an excerpt are dropped via excerpt_remove_blocks(), and whatever
	 * markup is left over is stripped too.
	 *
	 * This deliberately stops short of what core does next, which is run
	 * the result through the full 'the_content' filter chain (oEmbed
	 * discovery, do_shortcode(), and every other filter other plugins hook
	 * onto it). That is unnecessary work for a short plain-text summary,
	 * and it is not safe to do unconditionally here: this list's own
	 * output can itself be embedded inside another post's content through
	 * the [time_machine] shortcode, so running that post's content back
	 * through the content filters while building this list risks
	 * re-entrant shortcode execution.
	 *
	 * @param string $content Raw post_content.
	 *
	 * @return string
	 */
	private function generate_excerpt( $content ) {

		if ( '' === $content ) {
			return '';
		}

		$content = strip_shortcodes( $content );
		$content = excerpt_remove_blocks( $content );

		return trim( wp_strip_all_tags( $content ) );
	}

	/**
	 * Build the excerpt markup for a single post, or '' when there is
	 * nothing to show (excerpt disabled, or no source text was found).
	 *
	 * Decides where the excerpt text comes from - a manual post_excerpt, a
	 * notice for a password protected post the current visitor has not
	 * unlocked, or one generated from post_content via generate_excerpt() -
	 * and how it needs to be displayed, since each of those three is
	 * handled differently (see the inline comments below).
	 *
	 * @param object $post_row Post row (post_excerpt, post_content, post_password, ID).
	 *
	 * @return string
	 */
	private function get_excerpt_html( $post_row ) {

		if ( ! $this->args['excerpt'] ) {
			return '';
		}

		// Decide where the excerpt text comes from, and remember which of
		// the three ways it was sourced - each is displayed differently
		// below. post_password_required() also accounts for the current
		// visitor already having entered the password (via cookie), same
		// check WordPress core's own get_the_excerpt() relies on.
		if ( '' !== $post_row->post_excerpt ) {

			$excerpt_kind   = 'manual';
			$excerpt_source = $post_row->post_excerpt;

		} elseif ( '' !== $post_row->post_password && post_password_required( $post_row->ID ) ) {

			// Never build a fallback excerpt from post_content for a
			// password protected post the current visitor has not
			// unlocked: unlike a manual excerpt (which whoever wrote it
			// chose to expose), post_content holds the actual protected
			// content. Shows the same wording WordPress core's own
			// get_the_excerpt() uses in this situation, as our own
			// translatable string so it goes through this plugin's text
			// domain like every other string here.
			$excerpt_kind   = 'protected';
			$excerpt_source = __( 'There is no excerpt because this is a protected post.', 'time-machine' );

		} else {

			$excerpt_kind   = 'generated';
			$excerpt_source = $this->generate_excerpt( $post_row->post_content );
		}

		if ( '' === $excerpt_source ) {
			return '';
		}

		switch ( $excerpt_kind ) {

			case 'manual':
				// A manual excerpt is short by nature, so trimming it down
				// further is optional and controlled by the excerpt_cut
				// setting. wp_trim_words() already strips tags and appends
				// '&hellip;' as markup (not text), so its result is
				// inserted as is rather than through esc_html() - doing
				// that on top would turn the entity back into literal
				// "&hellip;" text. The untrimmed branch has no such
				// guarantee, so it still goes through esc_html().
				$excerpt = $this->args['excerpt_cut']
					? wp_trim_words( $excerpt_source, $this->args['excerpt_length'], '&hellip;' )
					: esc_html( $excerpt_source );
				break;

			case 'protected':
				// A static notice, not real content - always shown in
				// full, never subject to the excerpt_cut word count.
				$excerpt = esc_html( $excerpt_source );
				break;

			default:
				// A generated excerpt has no natural length limit -
				// without trimming it would be the entire post as plain
				// text - so it is always trimmed, same as WordPress
				// core's own fallback in wp_trim_excerpt(), which trims a
				// generated excerpt regardless of any "shorten" setting.
				$excerpt = wp_trim_words( $excerpt_source, $this->args['excerpt_length'], '&hellip;' );
				break;
		}

		return sprintf(
			'<br><span class="time-machine-item__excerpt">%s</span>',
			$excerpt
		);
	}

	/**
	 * Build the `<li>` markup for a single post.
	 *
	 * @param object        $post_row    Post row (ID, post_title, post_date_gmt, post_excerpt, post_content, post_password, comment_count).
	 * @param \DateTimeZone $timezone    Site timezone, used to convert the GMT date for display.
	 * @param string        $date_format Date format string, as set on Settings > General, used for the title attribute.
	 *
	 * @return string
	 */
	private function get_item_html( $post_row, \DateTimeZone $timezone, $date_format ) {

		// Convert the GMT date used for querying into the site's configured timezone for display.
		$post_date_local = ( new \DateTimeImmutable( $post_row->post_date_gmt, new \DateTimeZone( 'UTC' ) ) )->setTimezone( $timezone );

		$year = sprintf(
			'<time class="time-machine-item__date" datetime="%1$s">%2$s</time>: ',
			esc_attr( $post_date_local->format( 'c' ) ),
			esc_html( $post_date_local->format( 'Y' ) )
		);

		// Posts can have an empty title; fall back to the same label WordPress core uses
		// in the admin post list, so the item stays visible and clickable.
		$post_title = ( '' !== $post_row->post_title ) ? $post_row->post_title : __( '(no title)', 'time-machine' );

		$title = sprintf(
			'<a href="%1$s" rel="nofollow" title="%2$s" class="time-machine-item__title">%3$s</a>',
			esc_url( get_permalink( $post_row->ID ) ),
			esc_attr__( 'Published on', 'time-machine' ) . ' ' . esc_attr( wp_date( $date_format, $post_date_local->getTimestamp(), $timezone ) ),
			esc_html( $post_title )
		);

		// What about comments?
		if ( $this->args['display_commentnum'] ) {
			$comments = sprintf(
				' <span class="time-machine-item__comments" title="%s">(%d)</span>',
				esc_attr__( 'Number of comments', 'time-machine' ),
				esc_html( $post_row->comment_count )
			);
		}

		return sprintf(
			'<li class="time-machine-item">%1$s%2$s%3$s%4$s</li>',
			$year,
			$title,
			$comments ?? '',
			$this->get_excerpt_html( $post_row )
		);
	}
}
