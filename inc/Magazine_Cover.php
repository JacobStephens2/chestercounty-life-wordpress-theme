<?php
/**
 * WP_Rig\WP_Rig\Magazine_Cover class.
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

/**
 * Recognizes the Magazine cover in saved and rendered Image blocks.
 */
class Magazine_Cover {

	/**
	 * Resolves the first marked Image in saved homepage Columns.
	 *
	 * @param array $columns Parsed Columns block.
	 * @return array|null Attachment ID, wrapping URL, authored alt and flipbook slug, or null.
	 */
	public static function resolve_saved_cover( array $columns ) {
		$block = self::first_marked_image( $columns['innerBlocks'] ?? array() );
		if ( null === $block ) {
			return null;
		}

		$attachment_id = (int) ( $block['attrs']['id'] ?? 0 );
		$cover         = self::locate_primary_image( $block['innerHTML'] ?? '' );
		if ( ! $attachment_id || null === $cover ) {
			return null;
		}

		$url = esc_url_raw( $cover['href'] );
		if ( '' === $url ) {
			return null;
		}

		return array(
			'attachment_id' => $attachment_id,
			'url'           => $url,
			'alt'           => trim( $cover['attributes']['alt']['value'] ?? '' ),
			'slug'          => self::flipbook_slug( $url, false ),
		);
	}

	/**
	 * Applies responsive sizes and descriptive alt defaults to the rendered Magazine cover.
	 *
	 * @param string $block_content Rendered Image block HTML.
	 * @param string $default_sizes Responsive sizes default for the homepage.
	 * @return string Image block HTML with cover defaults applied.
	 */
	public static function apply_rendered_defaults( string $block_content, string $default_sizes ) : string {
		$cover = self::locate_primary_image( $block_content );
		if ( null === $cover || ! self::is_rendered_cover( $cover ) ) {
			return $block_content;
		}

		$attributes = $cover['attributes'];
		$updates    = array();
		$sizes      = $attributes['sizes']['value'] ?? '';
		if ( '100vw' === $sizes || 'auto, 100vw' === $sizes ) {
			$updates['sizes'] = $default_sizes;
		}

		if ( preg_match( '/\A[\s\p{Z}]*\z/u', $attributes['alt']['value'] ?? '' ) ) {
			$edition_name = self::determine_cover_edition_name( $cover['href'], $attributes['src']['value'] ?? '' );
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
	 * Finds the first Image with the cover marker in its saved block attributes.
	 *
	 * @param array $blocks Parsed child blocks.
	 * @return array|null First marked Image block in depth-first order.
	 */
	private static function first_marked_image( array $blocks ) {
		foreach ( $blocks as $block ) {
			if ( 'core/image' === $block['blockName'] && self::has_class( $block['attrs']['className'] ?? '', 'front_cover' ) ) {
				return $block;
			}

			$image = self::first_marked_image( $block['innerBlocks'] ?? array() );
			if ( null !== $image ) {
				return $image;
			}
		}

		return null;
	}

	/**
	 * Checks for the cover marker on the rendered figure or its legacy container.
	 *
	 * @param array $cover Primary image information returned by the locator.
	 * @return bool Whether the Image block markup marks a Magazine cover.
	 */
	private static function is_rendered_cover( array $cover ) : bool {
		return self::has_class( $cover['figure_classes'], 'front_cover' )
			|| ( self::has_class( $cover['container_classes'], 'wp-block-image' )
				&& self::has_class( $cover['container_classes'], 'front_cover' ) );
	}

	/**
	 * Checks for an exact token in a list of HTML classes.
	 *
	 * @param mixed  $classes Class attribute value.
	 * @param string $class   Expected token.
	 * @return bool Whether the token is present.
	 */
	private static function has_class( $classes, string $class ) : bool {
		return is_string( $classes ) && in_array( $class, preg_split( '/[ \t\r\n\f]+/', $classes ), true );
	}

	/**
	 * Reads the flipbook slug from the wrapping URL fragment.
	 *
	 * @param string $url            Wrapping link URL.
	 * @param bool   $case_sensitive Whether the fragment prefix is case-sensitive.
	 * @return string Flipbook slug, or empty string when the fragment does not match.
	 */
	private static function flipbook_slug( string $url, bool $case_sensitive ) : string {
		$fragment = wp_parse_url( $url, PHP_URL_FRAGMENT );
		$pattern  = '~\Apdf-([a-zA-Z0-9-]+)(?:/|\z)~' . ( $case_sensitive ? '' : 'i' );

		return preg_match( $pattern, $fragment ?? '', $matches ) ? $matches[1] : '';
	}

	/**
	 * Locates the primary image and its direct wrapping link in Image block markup.
	 *
	 * @param string $markup Saved or rendered Image block HTML.
	 * @return array|null Image information, or null for an unsupported Image block shape.
	 */
	private static function locate_primary_image( string $markup ) {
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

		$figure    = self::read_cover_attributes( $matches['figure'][0], $attribute );
		$container = self::read_cover_attributes( $matches['container'][0], $attribute );
		if ( null === $figure || null === $container ) {
			return null;
		}

		$image = self::read_cover_attributes( $matches['image'][0], $attribute );
		$link  = self::read_cover_attributes( $matches['link'][0], $attribute );
		if ( null === $image || null === $link ) {
			return null;
		}

		return array(
			'offset'            => $matches['image'][1],
			'attributes'        => $image,
			'href'              => $link['href']['value'] ?? '',
			'figure_classes'    => $figure['class']['value'] ?? '',
			'container_classes' => $container['class']['value'] ?? '',
		);
	}

	/**
	 * Reads the attributes of one tag matched by the Image block shape.
	 *
	 * @param string $markup  The portion of the tag containing attributes.
	 * @param string $pattern Pattern for a single attribute.
	 * @return array|null Decoded values and positions, or null for duplicate attributes.
	 */
	private static function read_cover_attributes( string $markup, string $pattern ) {
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
	private static function determine_cover_edition_name( string $href, string $src ) : string {
		$slug = self::flipbook_slug( $href, true );
		if ( '' !== $slug ) {
			$slug_parts = explode( '-', $slug );
			if ( count( $slug_parts ) === 3 && is_numeric( $slug_parts[2] ) ) {
				return ucfirst( $slug_parts[0] ) . '/' . ucfirst( $slug_parts[1] ) . ' ' . $slug_parts[2];
			}

			return ucwords( str_replace( '-', ' ', $slug ) );
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
