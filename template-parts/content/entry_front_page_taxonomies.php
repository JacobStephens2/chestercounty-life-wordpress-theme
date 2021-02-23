<?php
/**
 * Template part for displaying a post's taxonomy terms
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

$taxonomies = wp_list_filter(
	get_object_taxonomies( $post, 'objects' ),
	array(
		'public' => true,
	)
);

?>
<div class="entry-taxonomies">
	<?php

	// Get only the categories.

	$category = get_the_category_list( esc_html( $separator ), '', $post->ID );

	foreach ( $taxonomies as $category) {

		$separator = _x( ', ', 'list item separator', 'wp-rig');

		switch ( $category->name ) {
			case 'category':
				$class            = 'category-links term-links';
				$list             = get_the_category_list( esc_html( $separator ), '', $post->ID );
				/* translators: %s: list of taxonomy terms */
				$placeholder_text = __( '%s', 'wp-rig' );
				break;
		}
	}

	?>
	<span class="<?php echo esc_attr( $class ); ?>">
		<?php
		printf(
			esc_html( $placeholder_text ),
			$list // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		?>

	</span>

</div><!-- .entry-taxonomies -->
