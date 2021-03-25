<?php
/**
 * WP_Rig\WP_Rig\Category\Component class
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Category;

use WP_Rig\WP_Rig\Component_Interface;
use WP_Rig\WP_Rig\Templating_Component_Interface;

/**
 * Class for modified category functions.
 *
 * Exposes template tags:
 * *`wp-rig()->my_archive_title()`
 */
class Component implements Component_Interface, Templating_Component_Interface {

	/**
	 * Gets the unique identifier for the theme component.
	 *
	 * @return string Component slug.
	 */
	public function get_slug() : string {
		return 'category';
	}

	/**
	 * Adds the action and filter hooks to integrate with WordPress.
	 */
	public function initialize() {
	}

	/**
	 * Gets template tags to expose as methods on the Template_Tags class instance, accessible through `wp_rig()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function template_tags() : array {
		return array(
			'my_archive_title' => array( $this, 'my_archive_title' ),
		);
	}

	/**
	 * Display the archive title based on the queried object.
	 *
	 * @since 4.1.0
	 *
	 * @see get_my_archive_title()
	 *
	 * @param string $before Optional. Content to prepend to the title. Default empty.
	 * @param string $after  Optional. Content to append to the title. Default empty.
	 */
	public function my_archive_title( $before = '', $after = '' ) {
		$title = $this->get_my_archive_title();

		if ( ! empty( $title ) ) {
			echo $before . $title . $after;
		}
	}

	/**
	 * Retrieve the archive title based on the queried object.
	 *
	 * @since 4.1.0
	 * @since 5.5.0 The title part is wrapped in a `<span>` element.
	 *
	 * @return string Archive title.
	 */
	public function get_my_archive_title() {
		$title  = __( 'Archives' );
		$prefix = '';

		if ( is_category() ) {
			$title  = single_cat_title( '', false );
			$prefix = _x( '', 'category archive title prefix' );
		} elseif ( is_tag() ) {
			$title  = single_tag_title( '', false );
			$prefix = _x( 'Tag:', 'tag archive title prefix' );
		} elseif ( is_author() ) {
			$title  = get_the_author();
			$prefix = _x( 'Author:', 'author archive title prefix' );
		} elseif ( is_year() ) {
			$title  = get_the_date( _x( 'Y', 'yearly archives date format' ) );
			$prefix = _x( 'Year:', 'date archive title prefix' );
		} elseif ( is_month() ) {
			$title  = get_the_date( _x( 'F Y', 'monthly archives date format' ) );
			$prefix = _x( 'Month:', 'date archive title prefix' );
		} elseif ( is_day() ) {
			$title  = get_the_date( _x( 'F j, Y', 'daily archives date format' ) );
			$prefix = _x( 'Day:', 'date archive title prefix' );
		} elseif ( is_tax( 'post_format' ) ) {
			if ( is_tax( 'post_format', 'post-format-aside' ) ) {
				$title = _x( 'Asides', 'post format archive title' );
			} elseif ( is_tax( 'post_format', 'post-format-gallery' ) ) {
				$title = _x( 'Galleries', 'post format archive title' );
			} elseif ( is_tax( 'post_format', 'post-format-image' ) ) {
				$title = _x( 'Images', 'post format archive title' );
			} elseif ( is_tax( 'post_format', 'post-format-video' ) ) {
				$title = _x( 'Videos', 'post format archive title' );
			} elseif ( is_tax( 'post_format', 'post-format-quote' ) ) {
				$title = _x( 'Quotes', 'post format archive title' );
			} elseif ( is_tax( 'post_format', 'post-format-link' ) ) {
				$title = _x( 'Links', 'post format archive title' );
			} elseif ( is_tax( 'post_format', 'post-format-status' ) ) {
				$title = _x( 'Statuses', 'post format archive title' );
			} elseif ( is_tax( 'post_format', 'post-format-audio' ) ) {
				$title = _x( 'Audio', 'post format archive title' );
			} elseif ( is_tax( 'post_format', 'post-format-chat' ) ) {
				$title = _x( 'Chats', 'post format archive title' );
			}
		} elseif ( is_post_type_archive() ) {
			$title  = post_type_archive_title( '', false );
			$prefix = _x( 'Archives:', 'post type archive title prefix' );
		} elseif ( is_tax() ) {
			$queried_object = get_queried_object();
			if ( $queried_object ) {
				$tax    = get_taxonomy( $queried_object->taxonomy );
				$title  = single_term_title( '', false );
				$prefix = sprintf(
					/* translators: %s: Taxonomy singular name. */
					_x( '%s:', 'taxonomy term archive title prefix' ),
					$tax->labels->singular_name
				);
			}
		}

		$original_title = $title;

		/**
		 * Filters the archive title prefix.
		 *
		 * @since 5.5.0
		 *
		 * @param string $prefix Archive title prefix.
		 */
		$prefix = apply_filters( 'get_my_archive_title_prefix', $prefix );
		if ( $prefix ) {
			$title = sprintf(
				/* translators: 1: Title prefix. 2: Title. */
				_x( '%1$s %2$s', 'archive title' ),
				$prefix,
				'<span>' . $title . '</span>'
			);
		}

		/**
		 * Filters the archive title.
		 *
		 * @since 4.1.0
		 * @since 5.5.0 Added the `$prefix` and `$original_title` parameters.
		 *
		 * @param string $title          Archive title to be displayed.
		 * @param string $original_title Archive title without prefix.
		 * @param string $prefix         Archive title prefix.
		 */
		return apply_filters( 'get_my_archive_title', $title, $original_title, $prefix );
	}

}
