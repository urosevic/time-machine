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
	 * @return array Array of result objects (ID, post_title, post_date_gmt, post_excerpt, comment_count).
	 */
	public function get_articles() {

		global $wpdb;

		// Query against post_date_gmt (UTC) for a timezone-agnostic comparison.
		$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

		$this_year = (int) $now->format( 'Y' );
		$last_year = $this_year;

		$sql = "
			SELECT ID, post_title, post_date_gmt, post_excerpt, comment_count
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

			// Get through years since 2002 to current (or last) year.
			for ( $year = 2002; $year < $last_year; ++$year ) {

				// Define base day (current date and time except year).
				$date_base = $now->setDate( $year, (int) $now->format( 'm' ), (int) $now->format( 'd' ) );

				// Define theoretical before and after date range.
				$date_before = $date_base->modify( '-' . $this->args['offset'] . ' ' . $this->args['range'] )->format( 'Y-m-d H:i:s' );
				$date_after  = $date_base->modify( '+' . $this->args['offset'] . ' ' . $this->args['range'] )->format( 'Y-m-d H:i:s' );

				// Decide requested before and after date range.
				if ( 'before' === $this->args['direction'] ) {
					// Range only before.
					$date_start = $date_before;
					$date_end   = $date_base->format( 'Y-m-d H:i:s' );
				} elseif ( 'after' === $this->args['direction'] ) {
					// Range only after.
					$date_start = $date_base->format( 'Y-m-d H:i:s' );
					$date_end   = $date_after;
				} else {
					// Range before till after.
					$date_start = $date_before;
					$date_end   = $date_after;
				}

				// Append new range.
				$range_clauses[] = '`post_date_gmt` BETWEEN %s AND %s';
				$values[]        = $date_start;
				$values[]        = $date_end;
			}

			$sql .= '( ' . implode( ' OR ', $range_clauses ) . ' )';

		} else {
			// Single range.
			$sql     .= 'CAST(`post_date_gmt` AS char) LIKE %s';
			$values[] = '%-' . $now->format( 'm-d' ) . ' %';
		}

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
		return (array) $wpdb->get_results( $sql, OBJECT );
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
				'prefix' => '<span style="cursor:help" title="' . esc_attr__( "Articles published on today's day in past years", 'time-machine' ) . '">',
				'suffix' => '</span>',
			);
		}

		$prefix  = '<span style="cursor:help" title="';
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

		$html = '<ul class="time-machine-list">';

		if ( empty( $posts ) ) {

			$html .= esc_html( $this->args['message'] );

		} else {

			$timezone = wp_timezone();

			foreach ( $posts as $post_row ) {
				$html .= $this->get_item_html( $post_row, $timezone );
			}
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * Build the `<li>` markup for a single post.
	 *
	 * @param object       $post_row Post row (ID, post_title, post_date_gmt, post_excerpt, comment_count).
	 * @param \DateTimeZone $timezone Site timezone, used to convert the GMT date for display.
	 *
	 * @return string
	 */
	private function get_item_html( $post_row, \DateTimeZone $timezone ) {

		// Convert the GMT date used for querying into the site's configured timezone for display.
		$post_date_local = ( new \DateTimeImmutable( $post_row->post_date_gmt, new \DateTimeZone( 'UTC' ) ) )->setTimezone( $timezone );

		$html  = '<li class="time-machine-item">';
		$html .= '<time class="time-machine-item__date" datetime="' . esc_attr( $post_date_local->format( 'c' ) ) . '">' . esc_html( $post_date_local->format( 'Y' ) ) . '</time>';
		$html .= '<a href="' . esc_url( get_permalink( $post_row->ID ) ) . '" rel="nofollow" title="' . esc_attr__( 'Published at', 'time-machine' ) . ' ' . esc_attr( $post_date_local->format( 'Y-m-d H:i:s' ) ) . '" class="time-machine-item__title">' . esc_html( $post_row->post_title ) . '</a>';

		// What about comments?
		if ( $this->args['display_commentnum'] ) {
			$html .= '<span class="time-machine-item__comments" title="' . esc_attr__( 'Number of comments', 'time-machine' ) . '">';
			$html .= esc_html( $post_row->comment_count );
			$html .= '</span>';
		}

		// Do we need excerpt?
		if ( $this->args['excerpt'] && ! empty( $post_row->post_excerpt ) ) {

			$html .= '<span class="time-machine-item__excerpt">';

			if ( $this->args['excerpt_length'] && mb_strlen( $post_row->post_excerpt ) > ( $this->args['excerpt_length'] + 1 ) ) {
				$html .= esc_html( self::substr_utf8( $post_row->post_excerpt, 0, $this->args['excerpt_length'] ) ) . '&hellip;';
			} else {
				$html .= esc_html( $post_row->post_excerpt );
			}

			$html .= '</span>';
		}

		$html .= '</li>';

		return $html;
	}

	/**
	 * Multibyte safe substr() fallback.
	 *
	 * @param string $str  Source string.
	 * @param int    $from Start position.
	 * @param int    $len  Length.
	 *
	 * @return string
	 */
	private static function substr_utf8( $str, $from, $len ) {
		return preg_replace(
			'#^(?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){0,' . $from . '}' .
			'((?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){0,' . $len . '}).*#s',
			'$1',
			$str
		);
	}
}
