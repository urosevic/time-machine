<?php
/**
 * Time Machine widget.
 *
 * @package TimeMachine
 */

namespace TechWebUX\TimeMachine;

defined( 'ABSPATH' ) || exit;

/**
 * Class Widget
 *
 * Legacy WP_Widget implementation of Time Machine. Works on the classic
 * Widgets screen, the Customizer, and the block based widgets editor
 * (via the Legacy Widget block).
 */
class Widget extends \WP_Widget {

	/**
	 * Default settings used as fallback values.
	 *
	 * @var array
	 */
	private $defaults;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->defaults = Plugin::get_defaults();

		parent::__construct(
			'time-machine',
			__( 'Time Machine', 'time-machine' ),
			array( 'description' => __( 'List posts published in past relative to current date', 'time-machine' ) )
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {

		$generator = new Content_Generator( $instance );
		$posts     = $generator->get_articles();

		// Display widget if no articles?
		if ( empty( $posts ) && ! $instance['showifno'] ) {
			return;
		}

		$out  = '';
		$out .= $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {

			$title_wrap = $generator->get_title_wrap();

			$out .= $args['before_title'];
			$out .= $title_wrap['prefix'];
			$out .= apply_filters( 'widget_title', $instance['title'] );
			$out .= $title_wrap['suffix'];
			$out .= $args['after_title'];
		}

		$out .= $generator->get_list_html( $posts );

		$out .= $args['after_widget'];

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $out is assembled from already escaped fragments, plus theme controlled $args markup.
		echo $out;
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 *
	 * @return void
	 */
	public function form( $instance ) {

		$title   = ! empty( $instance['title'] ) ? $instance['title'] : $this->defaults['title'];
		$message = ! empty( $instance['message'] ) ? $instance['message'] : $this->defaults['message'];
		$posts   = ! empty( $instance['posts'] ) ? absint( $instance['posts'] ) : absint( $this->defaults['posts'] );

		$showifno           = ! empty( $instance['showifno'] ) ? $instance['showifno'] : '';
		$private            = ! empty( $instance['private'] ) ? $instance['private'] : '';
		$exclude_pages      = ! empty( $instance['exclude_pages'] ) ? $instance['exclude_pages'] : '';
		$exclude_current    = ! empty( $instance['exclude_current'] ) ? $instance['exclude_current'] : '';
		$display_commentnum = ! empty( $instance['display_commentnum'] ) ? $instance['display_commentnum'] : '';

		$range     = ! empty( $instance['range'] ) && in_array( $instance['range'], Plugin::ALLOWED_RANGES, true ) ? $instance['range'] : $this->defaults['range'];
		$offset    = ! empty( $instance['offset'] ) ? absint( $instance['offset'] ) : absint( $this->defaults['offset'] );
		$direction = ! empty( $instance['direction'] ) && in_array( $instance['direction'], Plugin::ALLOWED_DIRECTIONS, true ) ? $instance['direction'] : $this->defaults['direction'];

		$excerpt        = ! empty( $instance['excerpt'] ) ? $instance['excerpt'] : '';
		$excerpt_cut    = ! empty( $instance['excerpt_cut'] ) ? $instance['excerpt_cut'] : '';
		$excerpt_length = ! empty( $instance['excerpt_length'] ) ? absint( $instance['excerpt_length'] ) : absint( $this->defaults['excerpt_length'] );

		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title', 'time-machine' ); ?>:</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'message' ) ); ?>"><?php esc_html_e( 'Message when no articles in past', 'time-machine' ); ?>:</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'message' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'message' ) ); ?>" type="text" value="<?php echo esc_attr( $message ); ?>">
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'posts' ) ); ?>"><?php esc_html_e( 'Number of posts', 'time-machine' ); ?>:</label>
			<input class="small-text" id="<?php echo esc_attr( $this->get_field_id( 'posts' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'posts' ) ); ?>" type="number" value="<?php echo esc_attr( $posts ); ?>">
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'showifno' ) ); ?>">
			<input class="checkbox" <?php checked( $showifno, true, true ); ?> id="<?php echo esc_attr( $this->get_field_id( 'showifno' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'showifno' ) ); ?>" type="checkbox" value="1">
			<?php esc_html_e( 'Show widget even if no articles in past', 'time-machine' ); ?></label><br />

			<label for="<?php echo esc_attr( $this->get_field_id( 'private' ) ); ?>">
			<input class="checkbox" <?php checked( $private, true, true ); ?> id="<?php echo esc_attr( $this->get_field_id( 'private' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'private' ) ); ?>" type="checkbox" value="1">
			<?php esc_html_e( 'Include password protected articles', 'time-machine' ); ?></label><br />

			<label for="<?php echo esc_attr( $this->get_field_id( 'exclude_pages' ) ); ?>">
			<input class="checkbox" <?php checked( $exclude_pages, true, true ); ?> id="<?php echo esc_attr( $this->get_field_id( 'exclude_pages' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'exclude_pages' ) ); ?>" type="checkbox" value="1">
			<?php esc_html_e( 'Exclude pages from listing', 'time-machine' ); ?></label><br />

			<label for="<?php echo esc_attr( $this->get_field_id( 'exclude_current' ) ); ?>">
			<input class="checkbox" <?php checked( $exclude_current, true, true ); ?> id="<?php echo esc_attr( $this->get_field_id( 'exclude_current' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'exclude_current' ) ); ?>" type="checkbox" value="1">
			<?php esc_html_e( 'Exclude articles from this year', 'time-machine' ); ?></label><br />

			<label for="<?php echo esc_attr( $this->get_field_id( 'display_commentnum' ) ); ?>">
			<input class="checkbox" <?php checked( $display_commentnum, true, true ); ?> id="<?php echo esc_attr( $this->get_field_id( 'display_commentnum' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'display_commentnum' ) ); ?>" type="checkbox" value="1">
			<?php esc_html_e( 'Show number of comments', 'time-machine' ); ?></label>
		</p>

		<h3><?php esc_html_e( 'Time range', 'time-machine' ); ?></h3>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'offset' ) ); ?>"><?php esc_html_e( 'Offset', 'time-machine' ); ?>:</label>
			<input class="small-text" id="<?php echo esc_attr( $this->get_field_id( 'offset' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'offset' ) ); ?>" type="number" value="<?php echo esc_attr( $offset ); ?>">
		</p>

		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'range' ) ); ?>"><?php esc_html_e( 'Range', 'time-machine' ); ?>:</label>
		<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'range' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'range' ) ); ?>">
			<option value="none" <?php selected( $range, 'none' ); ?>><?php esc_html_e( 'Disable offset', 'time-machine' ); ?></option>
			<option value="days" <?php selected( $range, 'days' ); ?>><?php esc_html_e( 'Days', 'time-machine' ); ?></option>
			<option value="weeks" <?php selected( $range, 'weeks' ); ?>><?php esc_html_e( 'Weeks', 'time-machine' ); ?></option>
			<option value="months" <?php selected( $range, 'months' ); ?>><?php esc_html_e( 'Months', 'time-machine' ); ?></option>
		</select>
		</p>

		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'direction' ) ); ?>"><?php esc_html_e( 'Direction', 'time-machine' ); ?>:</label>
		<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'direction' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'direction' ) ); ?>">
			<option value="before" <?php selected( $direction, 'before' ); ?>><?php esc_html_e( 'Before [-]', 'time-machine' ); ?></option>
			<option value="both" <?php selected( $direction, 'both' ); ?>><?php esc_html_e( 'Both [+/-]', 'time-machine' ); ?></option>
			<option value="after" <?php selected( $direction, 'after' ); ?>><?php esc_html_e( 'After [+]', 'time-machine' ); ?></option>
		</select>
		</p>

		<h3><?php esc_html_e( 'Article excerpt', 'time-machine' ); ?></h3>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'excerpt' ) ); ?>">
			<input class="checkbox" <?php checked( $excerpt, true, true ); ?> id="<?php echo esc_attr( $this->get_field_id( 'excerpt' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'excerpt' ) ); ?>" type="checkbox" value="1">
			<?php esc_html_e( 'Show article excerpt?', 'time-machine' ); ?></label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'excerpt_cut' ) ); ?>">
			<input class="checkbox" <?php checked( $excerpt_cut, true, true ); ?> id="<?php echo esc_attr( $this->get_field_id( 'excerpt_cut' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'excerpt_cut' ) ); ?>" type="checkbox" value="1">
			<?php esc_html_e( 'Shorten article excerpt', 'time-machine' ); ?></label><br />
			<label for="<?php echo esc_attr( $this->get_field_id( 'excerpt_length' ) ); ?>"><?php esc_html_e( 'Excerpt length (characters)', 'time-machine' ); ?>:</label>
			<input class="small-text" id="<?php echo esc_attr( $this->get_field_id( 'excerpt_length' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'excerpt_length' ) ); ?>" type="number" value="<?php echo esc_attr( $excerpt_length ); ?>">
		</p>

		<?php
	}

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

		$instance['title']   = ! empty( $new_instance['title'] ) ? wp_strip_all_tags( $new_instance['title'] ) : '';
		$instance['message'] = ! empty( $new_instance['message'] ) ? wp_strip_all_tags( $new_instance['message'] ) : $this->defaults['message'];
		$instance['posts']   = ! empty( $new_instance['posts'] ) ? absint( $new_instance['posts'] ) : $this->defaults['posts'];

		$instance['showifno']           = ! empty( $new_instance['showifno'] );
		$instance['private']            = ! empty( $new_instance['private'] );
		$instance['exclude_pages']      = ! empty( $new_instance['exclude_pages'] );
		$instance['exclude_current']    = ! empty( $new_instance['exclude_current'] );
		$instance['display_commentnum'] = ! empty( $new_instance['display_commentnum'] );

		$submitted_range     = ! empty( $new_instance['range'] ) ? wp_strip_all_tags( $new_instance['range'] ) : $this->defaults['range'];
		$submitted_direction = ! empty( $new_instance['direction'] ) ? wp_strip_all_tags( $new_instance['direction'] ) : $this->defaults['direction'];

		$instance['range']     = in_array( $submitted_range, Plugin::ALLOWED_RANGES, true ) ? $submitted_range : $this->defaults['range'];
		$instance['offset']    = ! empty( $new_instance['offset'] ) ? absint( $new_instance['offset'] ) : $this->defaults['offset'];
		$instance['direction'] = in_array( $submitted_direction, Plugin::ALLOWED_DIRECTIONS, true ) ? $submitted_direction : $this->defaults['direction'];

		$instance['excerpt']        = ! empty( $new_instance['excerpt'] );
		$instance['excerpt_cut']    = ! empty( $new_instance['excerpt_cut'] );
		$instance['excerpt_length'] = ! empty( $new_instance['excerpt_length'] ) ? absint( $new_instance['excerpt_length'] ) : $this->defaults['excerpt_length'];

		return $instance;
	}
}
