<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

use WP_Query;

get_header();

wp_rig()->print_styles( 'wp-rig-content', 'wp-rig-home' );

?>
	<main id="primary" class="site-main">

	<?php

		// query
		$the_query = new WP_Query(array(
			'post_type'			=> 'post',
			'posts_per_page'	=> 10,
			'meta_key'			=> 'post_display_order',
			'orderby'			=> 'meta_value',
			'order'				=> 'DESC'
		));

		if( $the_query->have_posts() ) {

			while( $the_query->have_posts() ) {
				$the_query->the_post();
				get_template_part( 'template-parts/content/entry_home', get_post_type() );
			}

			if ( ! is_singular() ) {
				get_template_part( 'template-parts/content/pagination' );
			}
		} else {
			get_template_part( 'template-parts/content/error' );
		}

	?>

	</main><!-- #primary -->
<?php
get_sidebar();
get_footer();
