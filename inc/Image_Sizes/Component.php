<?php
/**
 * WP_Rig\WP_Rig\Image_Sizes\Component class
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Image_Sizes;

use WP_Rig\WP_Rig\Component_Interface;
use WP_Rig\WP_Rig\Magazine_Cover;
use function WP_Rig\WP_Rig\wp_rig;
use function add_filter;
use function is_front_page;
use function is_home;
use function str_replace;
use function preg_match;
use function preg_replace;

/**
 * Class for managing responsive image sizes.
 */
class Component implements Component_Interface {

	/**
	 * Default source size for homepage content images and the magazine cover.
	 */
	const HOMEPAGE_IMAGE_SIZES = '(max-width: 768px) 90vw, 650px';

	/**
	 * Gets the unique identifier for the theme component.
	 *
	 * @return string Component slug.
	 */
	public function get_slug() : string {
		return 'image_sizes';
	}

	/**
	 * Adds the action and filter hooks to integrate with WordPress.
	 */
	public function initialize() {
		add_filter( 'wp_calculate_image_sizes', array( $this, 'filter_content_image_sizes_attr' ), 10, 2 );
		add_filter( 'get_header_image_tag', array( $this, 'filter_header_image_tag' ), 10, 3 );
		add_filter( 'render_block', array( $this, 'filter_render_block_image' ), 10, 2 );
	}

	/**
	 * Adds custom image sizes attribute to enhance responsive image functionality for content images.
	 *
	 * @param string $sizes A source size value for use in a 'sizes' attribute.
	 * @param array  $size  Image size. Accepts an array of width and height
	 *                      values in pixels (in that order).
	 * @return string A source size value for use in a content image 'sizes' attribute.
	 */
	public function filter_content_image_sizes_attr( string $sizes, array $size ) : string {
		if ( is_front_page() || is_home() ) {
			return self::HOMEPAGE_IMAGE_SIZES;
		}

		$width = $size[0];

		if ( 740 <= $width ) {
			$sizes = '100vw';
		}

		if ( wp_rig()->is_primary_sidebar_active() ) {
			$sizes = '(min-width: 960px) 75vw, 100vw';
		}

		return $sizes;
	}

	/**
	 * Filters the `sizes` value in the header image markup.
	 *
	 * @param string $html   The HTML image tag markup being filtered.
	 * @param object $header The custom header object returned by 'get_custom_header()'.
	 * @param array  $attr   Array of the attributes for the image tag.
	 * @return string The filtered header image HTML.
	 */
	public function filter_header_image_tag( string $html, $header, array $attr ) : string {
		if ( isset( $attr['sizes'] ) ) {
			$html = str_replace( $attr['sizes'], '100vw', $html );
		}

		// Ensure alt attribute is present (explicitly empty if decorative per WCAG standards).
		if ( ! preg_match( '/\balt=/', $html ) ) {
			$html = preg_replace( '/<img\b/', '<img alt=""', $html );
		}

		return $html;
	}

	/**
	 * Filters image blocks to ensure accurate responsive sizes and descriptive alt attributes on the homepage.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The full block, including name and attributes.
	 * @return string Filtered block content.
	 */
	public function filter_render_block_image( string $block_content, array $block ) : string {
		if ( 'core/image' !== ( $block['blockName'] ?? null ) || ! ( is_front_page() || is_home() ) ) {
			return $block_content;
		}

		return Magazine_Cover::apply_rendered_defaults( $block_content, self::HOMEPAGE_IMAGE_SIZES );
	}
}
