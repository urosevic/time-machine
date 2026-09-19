<?php
/**
 * Time Machine shortcode.
 *
 * @package TimeMachine
 */

namespace TechWebUX\TimeMachine;

defined( 'ABSPATH' ) || exit;

/**
 * Class Shortcode
 *
 * Registers the `[time_machine]` shortcode. Renders through the same
 * Content_Generator used by the widget and the Gutenberg block, so all
 * three surfaces stay in sync.
 */
class Shortcode {

	/**
	 * Shortcode tag.
	 *
	 * @var string
	 */
	const TAG = 'time_machine';

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string       $content Shortcode content (unused, shortcode is self-closing).
	 * @param string       $tag     Shortcode tag.
	 *
	 * @return string
	 */
	public static function render( $atts, $content = '', $tag = '' ) {

		$defaults = Plugin::get_defaults();

		$atts = shortcode_atts(
			array(
				'title'              => $defaults['title'],
				'message'            => $defaults['message'],
				'posts'              => $defaults['posts'],
				'showifno'           => Plugin::bool_to_string( $defaults['showifno'] ),
				'private'            => Plugin::bool_to_string( $defaults['private'] ),
				'exclude_pages'      => Plugin::bool_to_string( $defaults['exclude_pages'] ),
				'exclude_current'    => Plugin::bool_to_string( $defaults['exclude_current'] ),
				'display_commentnum' => Plugin::bool_to_string( $defaults['display_commentnum'] ),
				'range'              => $defaults['range'],
				'offset'             => $defaults['offset'],
				'direction'          => $defaults['direction'],
				'excerpt'            => Plugin::bool_to_string( $defaults['excerpt'] ),
				'excerpt_cut'        => Plugin::bool_to_string( $defaults['excerpt_cut'] ),
				'excerpt_length'     => $defaults['excerpt_length'],
			),
			$atts,
			self::TAG
		);

		$settings = array(
			'title'              => sanitize_text_field( $atts['title'] ),
			'message'            => sanitize_text_field( $atts['message'] ),
			'posts'              => absint( $atts['posts'] ),
			'showifno'           => Plugin::string_to_bool( $atts['showifno'] ),
			'private'            => Plugin::string_to_bool( $atts['private'] ),
			'exclude_pages'      => Plugin::string_to_bool( $atts['exclude_pages'] ),
			'exclude_current'    => Plugin::string_to_bool( $atts['exclude_current'] ),
			'display_commentnum' => Plugin::string_to_bool( $atts['display_commentnum'] ),
			'range'              => in_array( $atts['range'], Plugin::ALLOWED_RANGES, true ) ? $atts['range'] : $defaults['range'],
			'offset'             => absint( $atts['offset'] ),
			'direction'          => in_array( $atts['direction'], Plugin::ALLOWED_DIRECTIONS, true ) ? $atts['direction'] : $defaults['direction'],
			'excerpt'            => Plugin::string_to_bool( $atts['excerpt'] ),
			'excerpt_cut'        => Plugin::string_to_bool( $atts['excerpt_cut'] ),
			'excerpt_length'     => absint( $atts['excerpt_length'] ),
		);

		$generator = new Content_Generator( $settings );
		$posts     = $generator->get_articles();

		if ( empty( $posts ) && ! $settings['showifno'] ) {
			return '';
		}

		$html = '<div class="time-machine">';

		if ( ! empty( $settings['title'] ) ) {

			$title_wrap = $generator->get_title_wrap();

			$html .= '<div class="time-machine-title">';
			$html .= $title_wrap['prefix'];
			$html .= esc_html( $settings['title'] );
			$html .= $title_wrap['suffix'];
			$html .= '</div>';
		}

		$html .= $generator->get_list_html( $posts );

		$html .= '</div>';

		return $html;
	}
}
