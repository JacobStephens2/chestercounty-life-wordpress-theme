<?php
/**
 * Template part for displaying a post's header
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

?>

<header class="entry-header">
	<?php
	if ( ! is_search() ) {
		get_template_part( 'template-parts/content/entry_thumbnail', get_post_type() );
	}
	?>

	<div class="ccl-content">
		<?php	get_template_part( 'template-parts/content/entry_front_page_footer', get_post_type() ); ?>

		<?php get_template_part( 'template-parts/content/entry_title_no_page_header', get_post_type() ); ?>

		<?php get_template_part( 'template-parts/content/entry_home_content', get_post_type() ); ?>
	</div>

</header><!-- .entry-header -->
