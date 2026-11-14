<?php
/**
 * Template part for displaying a post's featured image
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

// Audio or video attachments can have featured images, so they need to be specifically checked.
$support_slug = get_post_type();
if ( 'attachment' === $support_slug ) {
	if ( wp_attachment_is( 'audio' ) ) {
		$support_slug .= ':audio';
	} elseif ( wp_attachment_is( 'video' ) ) {
		$support_slug .= ':video';
	}
}

if ( post_password_required() || ! post_type_supports( $support_slug, 'thumbnail' ) || ! has_post_thumbnail() ) {
	return;
}

if ( is_singular( get_post_type() ) ) {
	?>
	<div class="post-thumbnail">
		<?php // the_post_thumbnail( 'wp-rig-featured', array( 'class' => 'skip-lazy' ) ); ?>
	</div><!-- .post-thumbnail -->
	<?php
} else {
	?>
	<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true">
		<?php
		global $wp_query;
		$thumbnail_size = 'post-thumbnail';
		$thumbnail_attr = array(
			'alt'   => the_title_attribute(
				array(
					'echo' => false,
				)
			),
			'sizes' => '100vw',
		);

		if ( is_front_page() || is_home() ) {
			$thumbnail_size          = 'medium_large';
			$thumbnail_attr['sizes'] = '(max-width: 768px) 90vw, 360px';
		} elseif ( wp_rig()->is_primary_sidebar_active() ) {
			$thumbnail_attr['sizes'] = '(min-width: 960px) 75vw, 100vw';
		}

		if ( 0 === $wp_query->current_post ) {
			$thumbnail_attr['class'] = 'skip-lazy';
		}

		the_post_thumbnail( $thumbnail_size, $thumbnail_attr );
		?>
	</a><!-- .post-thumbnail -->
	<?php
}
