<?php
/**
 * Render your site front page, whether the front page displays the blog posts index or a static page.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#front-page-display
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

use WP_Query;


get_header();


// Use grid layout if blog index is displayed.
if ( is_home() ) {
	wp_rig()->print_styles( 'wp-rig-content', 'wp-rig-front-page' );
} else {
	wp_rig()->print_styles( 'wp-rig-content' );
}

?>
	<main id="primary" class="site-main">

		<?php

		if ( have_posts() ) {

			get_template_part( 'template-parts/content/page_header' );

			while ( have_posts() ) {
				the_post();
				get_template_part( 'template-parts/content/entry', get_post_type() );
			}

			if ( ! is_singular() ) {
				get_template_part( 'template-parts/content/pagination' );
			}
		} else {
			get_template_part( 'template-parts/content/error' );
		}

		?>

		<?php

		// $args = array(
		// 	'tag' => 'featured-article',
		// );

		// $my_query = new WP_Query( $args );

		// while ( $my_query->have_posts() ) {
		// 	$my_query->the_post();
		// 	get_template_part( 'template-parts/content/entry_front_page', $my_query->get_post_type() );
		// }

		// get_template_part( 'template-parts/content/pagination' );

		?>

	</main><!-- #primary -->
<?php
get_footer();


