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
		<span class="tag">
			<?php
			// Show terms for tag associated with the post.
			echo get_the_tag_list( '', esc_html( $separator ), '', $post->ID );
			?>
		</span>
</div><!-- .entry-taxonomies -->
