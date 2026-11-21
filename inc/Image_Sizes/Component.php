<?php
/**
 * WP_Rig\WP_Rig\Image_Sizes\Component class
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Image_Sizes;

use WP_Rig\WP_Rig\Component_Interface;
use function WP_Rig\WP_Rig\wp_rig;
use function add_filter;
use function is_front_page;
use function is_home;
use function get_bloginfo;
use function esc_attr;
use function wp_parse_url;
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

		$cover = $this->locate_magazine_cover( $block_content );
		if ( null === $cover ) {
			return $block_content;
		}

		$attributes = $cover['attributes'];
		$updates    = array();
		$sizes      = $attributes['sizes']['value'] ?? '';
		if ( '100vw' === $sizes || 'auto, 100vw' === $sizes ) {
			$updates['sizes'] = self::HOMEPAGE_IMAGE_SIZES;
		}

		if ( preg_match( '/\A[\s\p{Z}]*\z/u', $attributes['alt']['value'] ?? '' ) ) {
			$edition_name = $this->determine_cover_edition_name( $cover['href'], $attributes['src']['value'] ?? '' );
			if ( '' !== $edition_name ) {
				/* translators: %s: edition name */
				$alt_text = sprintf( __( 'Chester County Life Magazine Cover - %s', 'wp-rig' ), $edition_name );
			} else {
				$alt_text = __( 'Chester County Life Current Edition Magazine Cover', 'wp-rig' );
			}

			$updates['alt'] = esc_attr( $alt_text );
		}

		// Work back from the end of the image tag so the earlier offsets stay valid.
		foreach ( array_reverse( $attributes, true ) as $name => $attribute ) {
			if ( isset( $updates[ $name ] ) ) {
				$quote         = $attribute['quote'];
				$block_content = substr_replace(
					$block_content,
					$attribute['assignment'] . $quote . $updates[ $name ] . $quote,
					$cover['offset'] + $attribute['offset'],
					$attribute['length']
				);
			}
		}

		if ( isset( $updates['alt'] ) && ! isset( $attributes['alt'] ) ) {
			$block_content = substr_replace( $block_content, ' alt="' . $updates['alt'] . '"', $cover['offset'], 0 );
		}

		return $block_content;
	}

	/**
	 * Locates the single primary image in a saved Image block.
	 *
	 * @param string $markup Rendered Image block HTML.
	 * @return array|null Attribute offsets and wrapping link, or null for other markup.
	 */
	private function locate_magazine_cover( string $markup ) {
		$space      = '[ \t\r\n\f]';
		$attribute  = '([^ \t\r\n\f\x00"\'<>/=]+)(?:[ \t\r\n\f]*=[ \t\r\n\f]*("[^"]*"|\'[^\']*\'|[^ \t\r\n\f\x00"\'\x60=<>]+))?';
		$attributes = "(?:$space+$attribute)*+$space*+";

		// Match the figure and its optional legacy container, direct link and caption.
		// Caption tags are consumed whole so quoted tag lookalikes cannot end the caption.
		$pattern = <<<REGEX
~
\A $space*
(?: <div(?<container>$attributes)> $space* )?
<figure(?<figure>$attributes)> $space*
(?: <a(?<link>$attributes)> $space* )?
<img(?<image>$attributes)/?>
(?(link) $space* </a$space*> )
$space*
(?:
	<figcaption$attributes>
	(?: [^<]++ | <!-- (?s:.*?) --> | </?(?!(?:figure|figcaption)(?:$space|/|>))[a-z][a-z0-9:-]*$attributes/?> )*+
	</figcaption$space*> $space*
)?
</figure$space*>
(?(container) $space* </div$space*> )
$space* \z
~ix
REGEX;
		if ( ! preg_match( $pattern, $markup, $matches, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		$figure    = $this->read_cover_attributes( $matches['figure'][0], $attribute );
		$container = $this->read_cover_attributes( $matches['container'][0], $attribute );
		if ( null === $figure || null === $container ) {
			return null;
		}

		$class_separator   = '/[ \t\r\n\f]+/';
		$figure_classes    = preg_split( $class_separator, $figure['class']['value'] ?? '' );
		$container_classes = preg_split( $class_separator, $container['class']['value'] ?? '' );
		$marked_container  = in_array( 'wp-block-image', $container_classes, true )
			&& in_array( 'front_cover', $container_classes, true );
		if ( ! in_array( 'front_cover', $figure_classes, true ) && ! $marked_container ) {
			return null;
		}

		$image = $this->read_cover_attributes( $matches['image'][0], $attribute );
		$link  = $this->read_cover_attributes( $matches['link'][0], $attribute );
		if ( null === $image || null === $link ) {
			return null;
		}

		return array(
			'offset'     => $matches['image'][1],
			'attributes' => $image,
			'href'       => $link['href']['value'] ?? '',
		);
	}

	/**
	 * Reads the attributes of one tag matched by the Image block shape.
	 *
	 * @param string $markup  The portion of the tag containing attributes.
	 * @param string $pattern Pattern for a single attribute.
	 * @return array|null Decoded values and positions, or null for duplicate attributes.
	 */
	private function read_cover_attributes( string $markup, string $pattern ) {
		preg_match_all( '~' . $pattern . '~', $markup, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		$attributes = array();
		foreach ( $matches as $match ) {
			$name = strtolower( $match[1][0] );
			if ( isset( $attributes[ $name ] ) ) {
				return null;
			}

			$raw_value = $match[2][0] ?? '';
			$quoted    = isset( $raw_value[0] ) && ( '"' === $raw_value[0] || "'" === $raw_value[0] );
			$attributes[ $name ] = array(
				'value'      => html_entity_decode( $quoted ? substr( $raw_value, 1, -1 ) : $raw_value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'offset'     => $match[2][1] ?? ( $match[1][1] + strlen( $match[1][0] ) ),
				'length'     => strlen( $raw_value ),
				'quote'      => $quoted ? $raw_value[0] : '"',
				'assignment' => isset( $match[2] ) ? '' : '=',
			);
		}

		return $attributes;
	}

	/**
	 * Extracts an edition from the wrapping link or primary image filename.
	 *
	 * @param string $href Wrapping link URL.
	 * @param string $src  Cover image URL.
	 * @return string Extracted edition name, or empty string if not found.
	 */
	private function determine_cover_edition_name( string $href, string $src ) : string {
		$link_url = wp_parse_url( $href );
		if ( preg_match( '~\Apdf-([a-zA-Z0-9-]+)(?:/|\z)~', $link_url['fragment'] ?? '', $matches ) ) {
			$slug_parts = explode( '-', $matches[1] );
			if ( count( $slug_parts ) === 3 && is_numeric( $slug_parts[2] ) ) {
				return ucfirst( $slug_parts[0] ) . '/' . ucfirst( $slug_parts[1] ) . ' ' . $slug_parts[2];
			}

			return ucwords( str_replace( '-', ' ', $matches[1] ) );
		}

		$image_url = wp_parse_url( $src );
		$segments  = explode( '/', $image_url['path'] ?? '' );
		$filename  = end( $segments );
		if ( preg_match( '/CCL_([A-Za-z]+)(\d{4})/i', $filename, $matches ) ) {
			return $matches[1] . ' ' . $matches[2];
		}

		return '';
	}
}
