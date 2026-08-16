<?php

if ( !class_exists('TimeMachine_Widget') ) {

class TimeMachine_Widget extends WP_Widget {

	private $defaults;

	function __construct() {

		$this->defaults = TimeMachine::defaults();

		parent::__construct(
			'time-machine', // Base ID
			__( 'Time Machine', 'tm' ), // Name
			array( 'description' => __( 'List posts published in past relative to current date', 'tm' ), ) // Args
		);

	} // eom __construct

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance )
	{

		// get globals
		global $wpdb;

		// get WP timezone and set that timezone in PHP environment for calculations
		// fallback to UTC
		date_default_timezone_set( get_option( "timezone_string", "UTC" ) );

		// some constants
		$this_year = $last_year = date( "Y" );
		$range = $or = $widget_title_prefix = $widget_title_suffix = "";

		// Start SQL QUERY
		$sql = "
			SELECT ID, post_title, post_date, post_excerpt, comment_count
			FROM $wpdb->posts
			WHERE post_status='publish'
		";

		// include pages?
		if ( $instance['exclude_pages'] ) {
			$sql .= " AND post_type = 'post'";
		} else {
			$sql .= " AND post_type IN( 'post', 'page' )";
		}

		// include private pages?
		if ( !$instance['private'] ) {
			$sql .= " AND `post_password` = ''";
		}

		// exclude current year?
		if ( $instance['exclude_current'] ) {
			$sql .= " AND CAST(`post_date` AS char) NOT LIKE '${this_year}-%'";
		} else {
			// increase last year for query
			++$last_year;
		}

		// offset
		$sql .= " AND ";
		if ( $instance['range'] != 'none' ) {

			// get through years since 2002 to current (or last) year
			for ( $year = 2002; $year < $last_year; ++$year ) {

				// define base day (current date and time except year)
				$date_base = $year . "-" . date( "m-d H:i:s" );

				// define theoretical before and after date range
				$date_before = date( "Y-m-d H:i:s", strtotime($date_base. ' -' . $instance['rangenum'] . ' ' . $instance['range']) );
				$date_after  = date( "Y-m-d H:i:s", strtotime($date_base. ' +' . $instance['rangenum'] . ' ' . $instance['range']) );

				// decide requested before and after date range
				if ( $instance['rangetype'] == 'before' ) {
					// range only before
					$date_start = $date_before;
					$date_end   = $date_base;
				} else if ( $instance['rangetype'] == 'after' ) {
					// range only after
					$date_start = $date_base;
					$date_end   = $date_after;
				} else {
					// range before till after
					$date_start = $date_before;
					$date_end   = $date_after;
				}

				// append new range
				$range .= $or . "`post_date` BETWEEN '$date_start' AND '$date_end'";
				$or = " OR ";

			} // for $year

			$sql .= "( $range )";

			// compose widget title tooltip
			$widget_title_prefix = "<span style=\"cursor:help\" title=\"" . __("Articles published", "tm") . " " . $instance['rangenum'] . " ";
			switch ( $instance['range'] ) {
				case "hours":
					$widget_title_prefix .= __("hours", "tm");
					break;
				case "days":
					$widget_title_prefix .= __("days", "tm");
					break;
				case "weeks":
					$widget_title_prefix .= __("weeks", "tm");
					break;
				case "months":
					$widget_title_prefix .= __("months", "tm");
					break;
			}

			$widget_title_prefix .= " ";

			switch ( $instance['rangetype'] ) {
				case "before":
					$widget_title_prefix .= __("before", "tm");
					break;
				case "after":
					$widget_title_prefix .= __("after", "tm");
					break;
				default:
					$widget_title_prefix .= __("before and after", "tm");
					break;
			}
			$widget_title_prefix .= " " . __("today's day in past years", "tm") . "\">";
			$widget_title_suffix = "</span>";

		} else {
			// single range
			$sql .= "CAST(`post_date` AS char) LIKE '%-" . date( "m-d" ) . " %'";
			$widget_title_prefix = "<span style=\"cursor:help\" title=\"" . __("Articles published on today's day in past years", "tm") . "\">";
			$widget_title_suffix = "</span>";
		}

		// number of posts
		$sql .= " LIMIT " .$instance['posts'];

		// execute SQL QUERY
		$r = $wpdb->get_results( $sql, OBJECT );

		// flush SQL query
		$wpdb->flush();

		// display widget if no articles?
		if ( empty($r) && ! $instance['showifno'] )
			return;

		$out = '';
		$out .= $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {

			$out .= $args['before_title'];
			$out .= $widget_title_prefix;
			$out .= apply_filters( 'widget_title', $instance['title'] );
			$out .= $widget_title_suffix;
			$out .= $args['after_title'];
		}

		// start real widget content here

		$out .= "<ul>";

		// show message if no articles
		if ( empty( $r ) ) {

			$out .= $instance['message'];

		} else {

			// we have articles, so compose content
			foreach ( $r as $p ) {

				$out .= "<li>";
				$out .= "<span class=\"meta-date\">" . substr( $p->post_date, 0, 4 ) . "</span>: "; // post date (make it optional)
				$out .= "<a href=\"" . get_permalink( $p->ID ) . "\" rel=\"nofollow\" title=\"" . __( "Published at", "tm" ) . " " . $p->post_date . "\" class=\"article-title\">" . $p->post_title . "</a>";

				// what about comments?
				if ( $instance['display_commentnum'] ) {

					$out .= " (<span title=\"" . __("Number of comments", "tm") . "\">";
					$out .= $p->comment_count;
					$out .= "</span>)";

				}

				// do we need excerpt?
				if ( $instance['excerpt'] && ! empty( $p->post_excerpt ) ) {

					$out .= html_entity_decode($instance['excerpt_before']);

					if ( $instance['excerpt_length'] && mb_strlen($p->post_excerpt) > ($instance['excerpt_length']+1) ) {
						$out .= $this->substr_utf8($p->post_excerpt, 0, $instance['excerpt_length'])."&hellip;";
					} else {
						$out .= $p->post_excerpt;
					}

					$out .= html_entity_decode($instance['excerpt_after']);

				}

				$out .= "</li>";

			} // foreach $r

		}

		$out .= "</ul>";

		$out .= $args['after_widget'];

		echo $out;

	} // eom widget

		/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {

		$title              = ! empty( $instance['title'] ) ? $instance['title'] : $this->defaults['title'];
		$message            = ! empty( $instance['message'] ) ? $instance['message'] : $this->defaults['message'];
		$posts              = ! empty( $instance['posts'] ) ? $instance['posts'] : $this->defaults['posts'];

		$showifno           = ! empty( $instance['showifno'] ) ? $instance['showifno'] : '';
		$private            = ! empty( $instance['private'] ) ? $instance['private'] : '';
		$exclude_pages      = ! empty( $instance['exclude_pages'] ) ? $instance['exclude_pages'] : '';
		$exclude_current    = ! empty( $instance['exclude_current'] ) ? $instance['exclude_current'] : '';
		$display_commentnum = ! empty( $instance['display_commentnum'] ) ? $instance['display_commentnum'] : '';

		$range              = ! empty( $instance['range'] ) ? $instance['range'] : $this->defaults['range'];
		$rangenum           = ! empty( $instance['rangenum'] ) ? $instance['rangenum'] : $this->defaults['rangenum'];
		$rangetype          = ! empty( $instance['rangetype'] ) ? $instance['rangetype'] : $this->defaults['rangetype'];

		$excerpt            = ! empty( $instance['excerpt'] ) ? $instance['excerpt'] : '';
		$excerpt_cut        = ! empty( $instance['excerpt_cut'] ) ? $instance['excerpt_cut'] : '';
		$excerpt_before     = ! empty( $instance['excerpt_before'] ) ? $instance['excerpt_before'] : $this->defaults['excerpt_before'];
		$excerpt_after      = ! empty( $instance['excerpt_after'] ) ? $instance['excerpt_after'] : $this->defaults['excerpt_after'];
		$excerpt_length     = ! empty( $instance['excerpt_length'] ) ? $instance['excerpt_length'] : $this->defaults['excerpt_length'];

		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title' ); ?>:</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'message' ); ?>"><?php _e( 'Message when no articles in past', 'tm' ); ?>:</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'message' ); ?>" name="<?php echo $this->get_field_name( 'message' ); ?>" type="text" value="<?php echo esc_attr( $message ); ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'posts' ); ?>"><?php _e( 'Number of posts', 'tm' ); ?>:</label>
			<input class="small-text" id="<?php echo $this->get_field_id( 'posts' ); ?>" name="<?php echo $this->get_field_name( 'posts' ); ?>" type="number" value="<?php echo esc_attr( $posts ); ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'showifno' ); ?>">
			<input class="checkbox" <?php checked( $showifno, true, true ); ?> id="<?php echo $this->get_field_id( 'showifno' ); ?>" name="<?php echo $this->get_field_name( 'showifno' ); ?>" type="checkbox" value="1">
			<?php _e( 'Show widget even if no articles in past', 'tm' ); ?></label><br />

			<label for="<?php echo $this->get_field_id( 'private' ); ?>">
			<input class="checkbox" <?php checked( $private, true, true ); ?> id="<?php echo $this->get_field_id( 'private' ); ?>" name="<?php echo $this->get_field_name( 'private' ); ?>" type="checkbox" value="1">
			<?php _e( 'Include password protected articles', 'tm' ); ?></label><br />

			<label for="<?php echo $this->get_field_id( 'exclude_pages' ); ?>">
			<input class="checkbox" <?php checked( $exclude_pages, true, true ); ?> id="<?php echo $this->get_field_id( 'exclude_pages' ); ?>" name="<?php echo $this->get_field_name( 'exclude_pages' ); ?>" type="checkbox" value="1">
			<?php _e( 'Exclude pages from listing', 'tm' ); ?></label><br />

			<label for="<?php echo $this->get_field_id( 'exclude_current' ); ?>">
			<input class="checkbox" <?php checked( $exclude_current, true, true ); ?> id="<?php echo $this->get_field_id( 'exclude_current' ); ?>" name="<?php echo $this->get_field_name( 'exclude_current' ); ?>" type="checkbox" value="1">
			<?php _e( 'Exclude articles from this year', 'tm' ); ?></label><br />

			<label for="<?php echo $this->get_field_id( 'display_commentnum' ); ?>">
			<input class="checkbox" <?php checked( $display_commentnum, true, true ); ?> id="<?php echo $this->get_field_id( 'display_commentnum' ); ?>" name="<?php echo $this->get_field_name( 'display_commentnum' ); ?>" type="checkbox" value="1">
			<?php _e( 'Show number of comments', 'tm' ); ?></label>
		</p>

		<h3><?php _e( 'Time range', 'tm' ); ?></h3>

		<p>
			<label for="<?php echo $this->get_field_id( 'rangenum' ); ?>"><?php _e( 'Offset', 'tm' ); ?>:</label>
			<input class="small-text" id="<?php echo $this->get_field_id( 'rangenum' ); ?>" name="<?php echo $this->get_field_name( 'rangenum' ); ?>" type="number" value="<?php echo esc_attr( $rangenum ); ?>">
		</p>

		<p>
		<label for="<?php echo $this->get_field_id( 'range' ); ?>"><?php _e( 'Range', 'tm' ); ?>:</label>
		<select class="widefat" id="<?php echo $this->get_field_id( 'range' ); ?>" name="<?php echo $this->get_field_name( 'range' ); ?>">
			<option value="none" <?php selected($range, "none"); ?>><?php _e('Disable offset', 'tm'); ?></option>
			<option value="hours" <?php selected($range, "hours"); ?>><?php _e('Hours', 'tm'); ?></option>
			<option value="days" <?php selected($range, "days"); ?>><?php _e('Days', 'tm'); ?></option>
			<option value="weeks" <?php selected($range, "weeks"); ?>><?php _e('Weeks', 'tm'); ?></option>
			<option value="months" <?php selected($range, "months"); ?>><?php _e('Months', 'tm'); ?></option>
		</select>
		</p>

		<p>
		<label for="<?php echo $this->get_field_id( 'rangetype' ); ?>"><?php _e( 'Range type', 'tm' ); ?>:</label>
		<select class="widefat" id="<?php echo $this->get_field_id( 'rangetype' ); ?>" name="<?php echo $this->get_field_name( 'rangetype' ); ?>">
			<option value="before" <?php selected($rangetype, "before"); ?>><?php _e('Before [-]', 'tm'); ?></option>
			<option value="both" <?php selected($rangetype, "both"); ?>><?php _e('Both [+/-]', 'tm'); ?></option>
			<option value="after" <?php selected($rangetype, "after"); ?>><?php _e('After [+]', 'tm'); ?></option>
		</select>
		</p>

		<h3><?php _e( 'Article excerpt', 'tm'  ); ?></h3>
		<p>
			<label for="<?php echo $this->get_field_id( 'excerpt' ); ?>">
			<input class="checkbox" <?php checked( $excerpt, true, true ); ?> id="<?php echo $this->get_field_id( 'excerpt' ); ?>" name="<?php echo $this->get_field_name( 'excerpt' ); ?>" type="checkbox" value="1">
			<?php _e( 'Show article excerpt?', 'tm' ); ?></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'excerpt_before' ); ?>"><?php _e( sprintf('Content in front of excerpt (eg. <code>%s</code>)', '&lt;br/&gt;&lt;em&gt;'), 'tm' ); ?>:</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'excerpt_before' ); ?>" name="<?php echo $this->get_field_name( 'excerpt_before' ); ?>" type="text" value="<?php echo esc_attr( $excerpt_before ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'excerpt_after' ); ?>"><?php _e( sprintf('Content after excerpt (eg. <code>%s</code>)', '&lt;/em&gt;'), 'tm' ); ?>:</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'excerpt_after' ); ?>" name="<?php echo $this->get_field_name( 'excerpt_after' ); ?>" type="text" value="<?php echo esc_attr( $excerpt_after ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'excerpt_cut' ); ?>">
			<input class="checkbox" <?php checked( $excerpt_cut, true, true ); ?> id="<?php echo $this->get_field_id( 'excerpt_cut' ); ?>" name="<?php echo $this->get_field_name( 'excerpt_cut' ); ?>" type="checkbox" value="1">
			<?php _e( 'Shorten article excerpt', 'tm' ); ?></label><br />
			<label for="<?php echo $this->get_field_id( 'excerpt_length' ); ?>"><?php _e( 'Excerpt length (characters)', 'tm' ); ?>:</label>
			<input class="small-text" id="<?php echo $this->get_field_id( 'excerpt_length' ); ?>" name="<?php echo $this->get_field_name( 'excerpt_length' ); ?>" type="number" value="<?php echo esc_attr( $excerpt_length ); ?>">
		</p>

		<?php
	} // eom form

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = array();

		$instance['title']              = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['message']            = ( ! empty( $new_instance['message'] ) ) ? strip_tags( $new_instance['message'] ) : $this->defaults['message'];
		$instance['posts']              = ( ! empty( $new_instance['posts'] ) ) ? strip_tags( $new_instance['posts'] ) : $this->defaults['posts'];

		$instance['showifno']           = ( ! empty( $new_instance['showifno'] ) ) ? strip_tags( $new_instance['showifno'] ) : '';
		$instance['private']            = ( ! empty( $new_instance['private'] ) ) ? strip_tags( $new_instance['private'] ) : '';
		$instance['exclude_pages']      = ( ! empty( $new_instance['exclude_pages'] ) ) ? strip_tags( $new_instance['exclude_pages'] ) : '';
		$instance['exclude_current']    = ( ! empty( $new_instance['exclude_current'] ) ) ? strip_tags( $new_instance['exclude_current'] ) : '';
		$instance['display_commentnum'] = ( ! empty( $new_instance['display_commentnum'] ) ) ? strip_tags( $new_instance['display_commentnum'] ) : '';

		$instance['range']              = ( ! empty( $new_instance['range'] ) ) ? strip_tags( $new_instance['range'] ) : $this->defaults['range'];
		$instance['rangenum']           = ( ! empty( $new_instance['rangenum'] ) ) ? strip_tags( $new_instance['rangenum'] ) : $this->defaults['rangenum'];
		$instance['rangetype']          = ( ! empty( $new_instance['rangetype'] ) ) ? strip_tags( $new_instance['rangetype'] ) : $this->defaults['rangetype'];

		$instance['excerpt']            = ( ! empty( $new_instance['excerpt'] ) ) ? strip_tags( $new_instance['excerpt'] ) : '';
		$instance['excerpt_cut']        = ( ! empty( $new_instance['excerpt_cut'] ) ) ? strip_tags( $new_instance['excerpt_cut'] ) : '';
		$instance['excerpt_length']     = ( ! empty( $new_instance['excerpt_length'] ) ) ? strip_tags( $new_instance['excerpt_length'] ) : $this->defaults['excerpt_length'];
		$instance['excerpt_before']     = ( ! empty( $new_instance['excerpt_before'] ) ) ? htmlspecialchars( $new_instance['excerpt_before'] ) : '';
		$instance['excerpt_after']      = ( ! empty( $new_instance['excerpt_after'] ) ) ? htmlspecialchars( $new_instance['excerpt_after'] ) : '';

		return $instance;
	} // eom update

	/* ================ HELPERS ================== */
	// unicode substr workaround from http://en.jinzorahelp.com/forums/viewtopic.php?f=18&t=6231
	function substr_utf8($str, $from, $len) {
		# utf8 substr
		# http://www.yeap.lv
		return preg_replace(
			'#^(?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){0,' . $from . '}' .
			'((?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){0,' . $len . '}).*#s',
			'$1',
			$str);
	} // eom substr_utf8

} // eo class

} // eo class check

add_action( 'widgets_init', function(){
	register_widget( 'TimeMachine_Widget' );
});