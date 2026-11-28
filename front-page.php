<?php
/**
 * Render your site front page, whether the front page displays the blog posts index or a static page.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#front-page-display
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

use WP_HTML_Tag_Processor;

/**
 * Render feature links in the same order as the edition's publishing tags.
 *
 * @param int  $tag     The current or recent edition tag ID.
 * @param bool $current Whether to use the current-edition card layout.
 */
function ccl_home_article_list( int $tag, bool $current ) {
	$posts = get_posts(
		array(
			'tag_id'           => $tag,
			'posts_per_page'   => -1,
			'orderby'          => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'suppress_filters' => false,
		)
	);

	if ( ! $posts ) {
		return;
	}

	$sizes = $current
		? '(max-width: 24rem) calc(100vw - 2rem), (max-width: 40rem) calc((100vw - 2.9rem) / 2), (max-width: 49rem) 18.55rem, (max-width: 85rem) 22vw, 18rem'
		: '84px';
	?>
	<ul class="<?php echo $current ? 'ccl-current-grid' : 'ccl-recent-list'; ?>" role="list">
		<?php foreach ( $posts as $index => $article ) : ?>
			<?php $categories = get_the_category( $article->ID ); ?>
			<li class="ccl-home-article-<?php echo (int) $article->ID; ?>">
				<a href="<?php echo esc_url( get_permalink( $article ) ); ?>">
					<?php
					echo get_the_post_thumbnail(
						$article,
						$current ? 'medium_large' : 'medium',
						array(
							'alt'      => '',
							'decoding' => 'async',
							'loading'  => $current && $index < 2 ? 'eager' : 'lazy',
							'sizes'    => $sizes,
						)
					);
					?>
					<div class="ccl-home-card-copy">
						<?php if ( $categories ) : ?>
							<p class="ccl-home-eyebrow"><?php echo esc_html( $categories[0]->name ); ?></p>
						<?php endif; ?>
						<h3><?php echo esc_html( wp_specialchars_decode( get_the_title( $article ), ENT_QUOTES ) ); ?></h3>
					</div>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * Render the approved layout using the editable cover and flipbook link.
 *
 * @param string $html Original columns block output.
 * @param array  $block Parsed columns block.
 * @return string Homepage layout or original block output.
 */
function ccl_home_edition_layout( string $html, array $block ) : string {
	$classes = preg_split( '/[ \t\r\n\f]+/', $block['attrs']['className'] ?? '' );
	if ( get_the_ID() !== get_queried_object_id() || ! in_array( 'first_column', $classes, true ) ) {
		return $html;
	}

	$cover = Magazine_Cover::resolve_saved_cover( $block );
	if ( null === $cover ) {
		return $html;
	}

	$issue_url = $cover['url'];
	$slug      = $cover['slug'];
	$issue     = $slug ? get_page_by_path( $slug, OBJECT, 'dearpdf' ) : null;
	$title     = $issue && 'publish' === $issue->post_status ? get_the_title( $issue ) : '';

	if ( ! $title && preg_match( '/^([a-z]+)-([a-z]+)-(\d{4})$/', $slug, $date ) ) {
		$title = ucfirst( $date[1] ) . '/' . ucfirst( $date[2] ) . ' ' . $date[3];
	}

	// This number belongs to the approved issue. Later covers publish without it.
	$eyebrow = 'september-october-2026' === $slug ? __( 'Current edition · No. 125', 'wp-rig' ) : __( 'Current edition', 'wp-rig' );
	$alt     = $cover['alt'];
	if ( '' === $alt ) {
		/* translators: %s: issue title. */
		$alt = $title ? sprintf( __( 'Read the %s Chester County Life issue', 'wp-rig' ), $title ) : __( 'Read the current Chester County Life issue', 'wp-rig' );
	}
	$image = wp_get_attachment_image(
		$cover['attachment_id'],
		'large',
		false,
		array(
			'alt'      => $alt,
			'decoding' => 'async',
			'sizes'    => '(max-width: 40rem) calc(100vw - 2rem), (max-width: 49rem) 38rem, (max-width: 85rem) 49vw, 40rem',
		)
	);
	if ( ! $image ) {
		return $html;
	}

	ob_start();
	?>
	<div class="ccl-edition-layout">
		<section aria-labelledby="ccl-edition-title">
			<?php if ( $title ) : ?>
				<p class="ccl-home-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h1 id="ccl-edition-title"><?php echo wp_kses( str_replace( '/', '/<wbr>', esc_html( $title ? $title : __( 'Current edition', 'wp-rig' ) ) ), array( 'wbr' => array() ) ); ?></h1>
			<a class="ccl-read-button" href="<?php echo esc_url( $issue_url ); ?>"><?php esc_html_e( 'Read the issue', 'wp-rig' ); ?></a>
			<a class="ccl-edition-cover" href="<?php echo esc_url( $issue_url ); ?>"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress builds and escapes the attachment image. ?></a>
		</section>

		<div class="ccl-home-articles">
			<section aria-labelledby="ccl-current-title">
				<div class="ccl-home-section-heading">
					<h2 id="ccl-current-title"><?php esc_html_e( 'From the current edition', 'wp-rig' ); ?></h2>
				</div>
				<?php ccl_home_article_list( 82, true ); ?>
			</section>

			<section class="ccl-recent-editions" aria-labelledby="ccl-recent-title">
				<div class="ccl-home-section-heading">
					<h2 id="ccl-recent-title"><?php esc_html_e( 'From recent editions', 'wp-rig' ); ?></h2>
					<a href="<?php echo esc_url( home_url( '/past-issues/' ) ); ?>"><?php esc_html_e( 'All editions', 'wp-rig' ); ?></a>
				</div>
				<?php ccl_home_article_list( 71, false ); ?>
			</section>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

if ( is_page() && class_exists( WP_HTML_Tag_Processor::class ) ) {
	add_filter(
		'body_class',
		static function ( $classes ) {
			$classes[] = 'ccl-home-layout';
			return $classes;
		}
	);

	add_action(
		'wp_enqueue_scripts',
		static function () {
			$asset = '/assets/css/homepage-layout.min.css';
			wp_enqueue_style(
				'wp-rig-homepage-layout',
				get_theme_file_uri( $asset ),
				array( 'wp-rig-global' ),
				wp_rig()->get_asset_version( get_theme_file_path( $asset ) )
			);
		},
		99
	);

	add_filter( 'render_block_core/columns', __NAMESPACE__ . '\\ccl_home_edition_layout', 10, 2 );
}

get_header();

wp_rig()->print_styles( 'wp-rig-content', 'wp-rig-front-page' );

?>
	<main id="primary" class="site-main">

		<?php

		// Get block editor content.

		if ( have_posts() ) {

			while ( have_posts() ) {
				the_post();
				get_template_part( 'template-parts/content/entry_front_page', get_post_type() );
			}

			if ( ! is_singular() ) {
				get_template_part( 'template-parts/content/pagination' );
			}
		} else {
			get_template_part( 'template-parts/content/error' );
		}

		// Paginate.

		get_template_part( 'template-parts/content/pagination' );

		?>


	</main><!-- #primary -->
<?php
get_footer();

