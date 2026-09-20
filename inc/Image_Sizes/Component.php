<?php
/**
 * WP_Rig\WP_Rig\Image_Sizes\Component class
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Image_Sizes;

use WP_Rig\WP_Rig\Component_Interface;
use function WP_Rig\WP_Rig\wp_rig;
use WP_Post;
use function add_filter;
use function is_front_page;
use function is_home;
use function get_bloginfo;
use function esc_attr;
use function __;
use function sprintf;
use function str_replace;
use function preg_match;
use function preg_replace;
use function explode;
use function count;
use function is_numeric;
use function ucfirst;
use function ucwords;

/**
 * Class for managing responsive image sizes.
 */
class Component implements Component_Interface {

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
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'filter_post_thumbnail_sizes_attr' ), 10, 3 );
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
			return '(max-width: 768px) 90vw, 650px';
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
	 * Adds custom image sizes attribute to enhance responsive image functionality for post thumbnails.
	 *
	 * @param array        $attr       Attributes for the image markup.
	 * @param WP_Post      $attachment Attachment post object.
	 * @param string|array $size       Registered image size or flat array of height and width dimensions.
	 * @return array The filtered attributes for the image markup.
	 */
	public function filter_post_thumbnail_sizes_attr( array $attr, WP_Post $attachment, $size ) : array {
		if ( is_front_page() || is_home() ) {
			$attr['sizes'] = '(max-width: 768px) 90vw, 360px';
			return $attr;
		}

		$attr['sizes'] = '100vw';

		if ( wp_rig()->is_primary_sidebar_active() ) {
			$attr['sizes'] = '(min-width: 960px) 75vw, 100vw';
		}

		return $attr;
	}

	/**
	 * Filters image blocks to ensure accurate responsive sizes and descriptive alt attributes on the homepage.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The full block, including name and attributes.
	 * @return string Filtered block content.
	 */
	public function filter_render_block_image( string $block_content, array $block ) : string {
		if ( ! ( is_front_page() || is_home() ) ) {
			return $block_content;
		}

		if ( false === strpos( $block_content, 'front_cover' ) ) {
			return $block_content;
		}

		// Replace 100vw sizes with column-accurate responsive sizes for the magazine cover.
		$block_content = str_replace( 'sizes="auto, 100vw"', 'sizes="(max-width: 768px) 90vw, 650px"', $block_content );
		$block_content = str_replace( 'sizes="100vw"', 'sizes="(max-width: 768px) 90vw, 650px"', $block_content );

		// Handle empty or missing alt text on the magazine cover image.
		$has_empty_alt  = (bool) preg_match( '/alt=(["\'])\s*\1/', $block_content );
		$has_alt_at_all = (bool) preg_match( '/\balt=["\']/', $block_content );

		if ( $has_empty_alt || ! $has_alt_at_all ) {
			$edition_name = $this->determine_cover_edition_name( $block_content );

			if ( ! empty( $edition_name ) ) {
				/* translators: %s: edition name */
				$alt_text = sprintf( __( 'Chester County Life Magazine Cover - %s', 'wp-rig' ), $edition_name );
			} else {
				$alt_text = __( 'Chester County Life Current Edition Magazine Cover', 'wp-rig' );
			}

			$block_content = $this->set_image_alt_attribute( $block_content, $alt_text );
		}

		return $block_content;
	}

	/**
	 * Extracts edition name from cover image block markup.
	 *
	 * @param string $content HTML content containing cover image or link.
	 * @return string Extracted edition name, or empty string if not found.
	 */
	protected function determine_cover_edition_name( string $content ) : string {
		if ( preg_match( '/#pdf-([a-zA-Z0-9-]+)/', $content, $matches ) ) {
			$slug_parts = explode( '-', $matches[1] );
			if ( count( $slug_parts ) === 3 && is_numeric( $slug_parts[2] ) ) {
				return ucfirst( $slug_parts[0] ) . '/' . ucfirst( $slug_parts[1] ) . ' ' . $slug_parts[2];
			}

			return ucwords( str_replace( '-', ' ', $matches[1] ) );
		}

		if ( preg_match( '/CCL_([A-Za-z]+)(\d{4})/i', $content, $matches ) ) {
			return $matches[1] . ' ' . $matches[2];
		}

		return '';
	}

	/**
	 * Sets or updates the alt attribute in an HTML snippet containing an <img> tag.
	 *
	 * @param string $html     HTML snippet.
	 * @param string $alt_text Descriptive alt text to set.
	 * @return string Updated HTML snippet.
	 */
	protected function set_image_alt_attribute( string $html, string $alt_text ) : string {
		if ( preg_match( '/alt=(["\'])\s*\1/', $html ) ) {
			return preg_replace( '/alt=(["\'])\s*\1/', 'alt="' . esc_attr( $alt_text ) . '"', $html );
		}

		return preg_replace( '/<img\b/', '<img alt="' . esc_attr( $alt_text ) . '"', $html );
	}
}
