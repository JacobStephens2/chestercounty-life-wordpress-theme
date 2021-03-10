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

<!-- Original approach -->

<?php
$args = array(
	'date_query' => array(
		array(
			'before' => '2 months ago',
		),
	),
);

$my_query = new WP_Query( $args );

while ( $my_query->have_posts() ) {
	$my_query->the_post();
	get_template_part( 'template-parts/content/entry_front_page_snippet', $my_query->get_post_type() );
}
?>

<!-- Use WP_Query object to get posts and order by post_display_order custom field. -->

<?php

// query
$the_query = new WP_Query(array(
	'post_type'			=> 'post',
	'posts_per_page'	=> 5,
	'meta_key'			=> 'post_display_order',
	'orderby'			=> 'meta_value',
	'order'				=> 'DESC'
));

?>
<?php if( $the_query->have_posts() ): ?>
	<ul>
	<?php while( $the_query->have_posts() ) : $the_query->the_post();

		$class = get_field('post_display_order') ? 'class="post_display_order"' : '';

		?>
		<li <?php echo $class; ?>>
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</li>
	<?php endwhile; ?>
	</ul>
<?php endif; ?>

<?php wp_reset_query();	 // Restore global post data stomped by the_post(). ?>


<!-- Get posts and order them by post_display_order custom field using get_posts().  -->

		<?php

		// get posts
		$posts = get_posts(array(
			'post_type'			=> 'post',
			'posts_per_page'	=> 5,
			'meta_key'			=> 'post_display_order',
			'orderby'			=> 'meta_value',
			'order'				=> 'DESC'
		));

		if( $posts ): ?>

			<ul>

			<?php foreach( $posts as $post ):

				setup_postdata( $post )

				?>
				<li>
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</li>

			<?php endforeach; ?>

			</ul>

			<?php wp_reset_postdata(); ?>

		<?php endif; ?>

<!-- Starting template content is below. -->

		<?php

		if ( have_posts() ) {

			while ( have_posts() ) {
				the_post();
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
