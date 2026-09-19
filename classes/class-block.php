<?php
/**
 * Time Machine block.
 *
 * @package TimeMachine
 */

namespace TechWebUX\TimeMachine;

defined( 'ABSPATH' ) || exit;

/**
 * Class Block
 *
 * Registers the `time-machine/time-machine` block. It is a dynamic block:
 * the editor only stores attributes, and the markup is rendered server side
 * through the same Content_Generator used by the widget and the shortcode,
 * so all three surfaces stay in sync.
 *
 * Since WordPress 5.8 the Widgets screen and the Customizer widgets panel
 * are both block based, so a single dynamic block registered here is
 * automatically usable in post content, template parts and as a "block
 * widget" - there is no separate widget block API to implement.
 */
class Block {

	/**
	 * Block name, as registered in block.json.
	 *
	 * @var string
	 */
	const NAME = 'time-machine/time-machine';

	/**
	 * Register the block, if the current WordPress version supports it.
	 *
	 * @return void
	 */
	public static function register() {

		// get_block_wrapper_attributes() (used in render()) was added in WP 5.6,
		// and reading block.json straight from a directory needs WP 5.8. Both are
		// well above this plugin's general 5.3 floor, so the block itself is
		// simply skipped on older sites while the widget and shortcode keep working.
		if ( ! function_exists( 'register_block_type' ) || ! function_exists( 'get_block_wrapper_attributes' ) ) {
			return;
		}

		register_block_type(
			TIME_MACHINE_DIR . 'blocks/time-machine/build',
			array(
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Server side render callback.
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return string
	 */
	public static function render( $attributes ) {

		$defaults = Plugin::get_defaults();

		$settings = array(
			'title'              => '' !== $attributes['title'] ? sanitize_text_field( $attributes['title'] ) : $defaults['title'],
			'message'            => '' !== $attributes['message'] ? sanitize_text_field( $attributes['message'] ) : $defaults['message'],
			'posts'              => absint( $attributes['posts'] ),
			'showifno'           => ! empty( $attributes['showifno'] ),
			'private'            => ! empty( $attributes['private'] ),
			'exclude_pages'      => ! empty( $attributes['excludePages'] ),
			'exclude_current'    => ! empty( $attributes['excludeCurrent'] ),
			'display_commentnum' => ! empty( $attributes['displayCommentNum'] ),
			'range'              => in_array( $attributes['range'], Plugin::ALLOWED_RANGES, true ) ? $attributes['range'] : $defaults['range'],
			'offset'             => absint( $attributes['offset'] ),
			'direction'          => in_array( $attributes['direction'], Plugin::ALLOWED_DIRECTIONS, true ) ? $attributes['direction'] : $defaults['direction'],
			'excerpt'            => ! empty( $attributes['excerpt'] ),
			'excerpt_cut'        => ! empty( $attributes['excerptCut'] ),
			'excerpt_length'     => absint( $attributes['excerptLength'] ),
		);

		$generator = new Content_Generator( $settings );
		$posts     = $generator->get_articles();

		if ( empty( $posts ) && ! $settings['showifno'] ) {
			return '';
		}

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'time-machine' ) );

		$html = '<div ' . $wrapper_attributes . '>';

		if ( ! empty( $settings['title'] ) ) {

			$title_wrap = $generator->get_title_wrap();

			$html .= '<div class="time-machine-title widget-title">';
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
