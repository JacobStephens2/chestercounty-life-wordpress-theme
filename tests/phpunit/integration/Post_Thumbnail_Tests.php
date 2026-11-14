<?php
/**
 * Post thumbnail rendering integration tests.
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Tests\Integration;

use DOMDocument;
use WP_Rig\WP_Rig\Tests\Framework\Integration_Test_Case;

/**
 * Exercises the shared template with real queries and attachment metadata.
 */
class Post_Thumbnail_Tests extends Integration_Test_Case {

	/**
	 * Request state restored after each rendering case.
	 *
	 * @var array
	 */
	private $state;

	/**
	 * Attachment used as both a post thumbnail and an unrelated image.
	 *
	 * @var int
	 */
	private $attachment_id;

	/**
	 * Creates deterministic image metadata without generating image files.
	 */
	public function setUp() {
		parent::setUp();
		$this->state = array(
			'query'              => $GLOBALS['wp_query'],
			'main_query'         => $GLOBALS['wp_the_query'],
			'post'               => isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null,
			'post_type_features' => $GLOBALS['_wp_post_type_features'],
			'image_sizes'        => $GLOBALS['_wp_additional_image_sizes'],
			'sidebars_widgets'   => wp_get_sidebars_widgets(),
			'options'            => array(
				'show_on_front'  => get_option( 'show_on_front' ),
				'page_on_front'  => get_option( 'page_on_front' ),
				'page_for_posts' => get_option( 'page_for_posts' ),
			),
		);
		add_image_size( 'post-thumbnail', 960, 600, true );

		$this->attachment_id = self::factory()->attachment->create_object(
			'thumbnail-fixture.jpg',
			0,
			array( 'post_mime_type' => 'image/jpeg' )
		);
		wp_update_attachment_metadata(
			$this->attachment_id,
			array(
				'width'  => 1600,
				'height' => 1000,
				'file'   => 'thumbnail-fixture.jpg',
				'sizes'  => array(
					'medium_large'   => array(
						'file'      => 'thumbnail-fixture-768x480.jpg',
						'width'     => 768,
						'height'    => 480,
						'mime-type' => 'image/jpeg',
					),
					'post-thumbnail' => array(
						'file'      => 'thumbnail-fixture-960x600.jpg',
						'width'     => 960,
						'height'    => 600,
						'mime-type' => 'image/jpeg',
					),
				),
			)
		);
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', 'Attachment description' );
	}

	/**
	 * Restores query, sidebar, support and size state; WordPress rolls back fixtures.
	 */
	public function tearDown() {
		// Restore the WordPress globals changed by request navigation and support fixtures.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_query']                   = $this->state['query'];
		$GLOBALS['wp_the_query']               = $this->state['main_query'];
		$GLOBALS['post']                       = $this->state['post'];
		$GLOBALS['_wp_post_type_features']     = $this->state['post_type_features'];
		$GLOBALS['_wp_additional_image_sizes'] = $this->state['image_sizes'];
		$GLOBALS['_wp_sidebars_widgets']       = array();
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_reset_postdata();
		wp_set_sidebars_widgets( $this->state['sidebars_widgets'] );
		foreach ( $this->state['options'] as $option => $value ) {
			update_option( $option, $value );
		}
		parent::tearDown();
	}

	/**
	 * Explicit attachment sizes survive before and after rendering a thumbnail.
	 *
	 * @dataProvider data_attachment_queries
	 * @param string $context Query context.
	 * @param bool   $sidebar Whether the primary sidebar has a widget.
	 */
	public function test_unrelated_attachment_sizes_survive_thumbnail_rendering( string $context, bool $sidebar ) {
		$post_id = self::factory()->post->create();
		set_post_thumbnail( $post_id, $this->attachment_id );
		$this->visit_context( $context, $post_id );
		$this->set_primary_sidebar( $sidebar );

		$before = wp_get_attachment_image( $this->attachment_id, 'medium_large', false, array( 'sizes' => '42px' ) );
		$this->render_thumbnail();
		$after = wp_get_attachment_image( $this->attachment_id, 'medium_large', false, array( 'sizes' => '42px' ) );

		foreach ( array( $before, $after ) as $markup ) {
			$image = $this->parse_markup( $markup )->getElementsByTagName( 'img' )->item( 0 );
			$this->assertSame( '42px', $this->fallback_sizes( $image->getAttribute( 'sizes' ) ) );
		}
	}

	/**
	 * Unrelated caller attributes survive in every thumbnail query context.
	 *
	 * @return array Request cases.
	 */
	public function data_attachment_queries() : array {
		return array(
			array( 'front', true ),
			array( 'posts', true ),
			array( 'archive', true ),
			array( 'archive', false ),
		);
	}

	/**
	 * Source selection and responsive attributes follow the real request together.
	 *
	 * @dataProvider data_archive_queries
	 * @param string $context Query context.
	 * @param bool   $sidebar Whether the primary sidebar has a widget.
	 * @param string $size    Expected intermediate image size.
	 * @param string $sizes   Expected responsive fallback.
	 */
	public function test_archive_rendering( string $context, bool $sidebar, string $size, string $sizes ) {
		$post_id = self::factory()->post->create( array( 'post_title' => 'A "title" & <b>County</b>' ) );
		set_post_thumbnail( $post_id, $this->attachment_id );
		$this->visit_context( $context, $post_id );
		$this->set_primary_sidebar( $sidebar );
		$this->assertSame( $sidebar, is_active_sidebar( 'sidebar-1' ) );

		$markup   = $this->render_thumbnail();
		$document = $this->parse_markup( $markup );
		$images   = $document->getElementsByTagName( 'img' );
		$links    = $document->getElementsByTagName( 'a' );
		$this->assertSame( 1, $images->length );
		$this->assertSame( 1, $links->length );
		$image = $images->item( 0 );
		$link  = $links->item( 0 );
		$file  = 'medium_large' === $size ? 'thumbnail-fixture-768x480.jpg' : 'thumbnail-fixture-960x600.jpg';
		$this->assertSame( $this->image_url( $file ), $image->getAttribute( 'src' ) );
		$this->assertSame( $sizes, $this->fallback_sizes( $image->getAttribute( 'sizes' ) ) );
		$this->assertContains( $this->image_url( 'thumbnail-fixture-768x480.jpg' ) . ' 768w', $image->getAttribute( 'srcset' ) );
		$this->assertContains( $this->image_url( 'thumbnail-fixture-960x600.jpg' ) . ' 960w', $image->getAttribute( 'srcset' ) );
		$this->assertContains( $this->image_url( 'thumbnail-fixture.jpg' ) . ' 1600w', $image->getAttribute( 'srcset' ) );
		$this->assertSame( 'A “title” & County', $image->getAttribute( 'alt' ) );
		$this->assertContains( 'alt="A &#8220;title&#8221; &amp; County"', $markup );
		$this->assertSame( 'skip-lazy wp-post-image', $image->getAttribute( 'class' ) );
		$this->assertSame( 'post-thumbnail', $link->getAttribute( 'class' ) );
		$this->assertSame( get_permalink( $post_id ), $link->getAttribute( 'href' ) );
		$this->assertSame( 'true', $link->getAttribute( 'aria-hidden' ) );
	}

	/**
	 * The front-page and posts-index predicates are exercised independently.
	 *
	 * @return array Request cases.
	 */
	public function data_archive_queries() : array {
		return array(
			'front page'      => array( 'front', true, 'medium_large', '(max-width: 768px) 90vw, 360px' ),
			'posts index'     => array( 'posts', true, 'medium_large', '(max-width: 768px) 90vw, 360px' ),
			'with sidebar'    => array( 'archive', true, 'post-thumbnail', '(min-width: 960px) 75vw, 100vw' ),
			'without sidebar' => array( 'archive', false, 'post-thumbnail', '100vw' ),
		);
	}

	/**
	 * Later loop items retain WordPress image classes without skip-lazy.
	 */
	public function test_later_loop_item() {
		foreach ( array( 'First', 'Second' ) as $title ) {
			$post_id = self::factory()->post->create( array( 'post_title' => $title ) );
			set_post_thumbnail( $post_id, $this->attachment_id );
		}
		update_option( 'show_on_front', 'posts' );
		$this->go_to( home_url( '/' ) );
		the_post();
		$first = $this->parse_markup( $this->render_thumbnail() )->getElementsByTagName( 'img' )->item( 0 );
		the_post();
		$later = $this->parse_markup( $this->render_thumbnail() )->getElementsByTagName( 'img' )->item( 0 );
		$this->assertSame( 1, $GLOBALS['wp_query']->current_post );
		$this->assertContains( 'skip-lazy', $first->getAttribute( 'class' ) );
		$this->assertNotContains( 'skip-lazy', $later->getAttribute( 'class' ) );
		$this->assertContains( 'attachment-medium_large', $later->getAttribute( 'class' ) );
		$this->assertContains( 'size-medium_large', $later->getAttribute( 'class' ) );
		$this->assertContains( 'wp-post-image', $later->getAttribute( 'class' ) );
		$this->assertSame( $this->image_url( 'thumbnail-fixture-768x480.jpg' ), $later->getAttribute( 'src' ) );
		$this->assertSame( '(max-width: 768px) 90vw, 360px', $this->fallback_sizes( $later->getAttribute( 'sizes' ) ) );
	}

	/**
	 * Missing intermediate metadata falls back to the full source, keeping sizes.
	 *
	 * @dataProvider data_archive_queries
	 * @param string $context Query context.
	 * @param bool   $sidebar Whether the primary sidebar has a widget.
	 * @param string $size    Requested size.
	 * @param string $sizes   Expected responsive fallback.
	 */
	public function test_missing_intermediate_metadata( string $context, bool $sidebar, string $size, string $sizes ) {
		$metadata          = wp_get_attachment_metadata( $this->attachment_id );
		$metadata['sizes'] = array();
		wp_update_attachment_metadata( $this->attachment_id, $metadata );
		$post_id = self::factory()->post->create();
		set_post_thumbnail( $post_id, $this->attachment_id );
		$this->visit_context( $context, $post_id );
		$this->set_primary_sidebar( $sidebar );

		$image = $this->parse_markup( $this->render_thumbnail() )->getElementsByTagName( 'img' )->item( 0 );
		$this->assertSame( $this->image_url( 'thumbnail-fixture.jpg' ), $image->getAttribute( 'src' ) );
		$this->assertSame( $sizes, $this->fallback_sizes( $image->getAttribute( 'sizes' ) ) );
		$this->assertFalse( $image->hasAttribute( 'srcset' ) );
	}

	/**
	 * Unavailable post thumbnails produce no container or image.
	 *
	 * @dataProvider data_unavailable_thumbnails
	 * @param string $reason Availability guard to exercise.
	 */
	public function test_unavailable_thumbnail( string $reason ) {
		$post_id = self::factory()->post->create( array( 'post_password' => 'protected' === $reason ? 'secret' : '' ) );
		if ( 'absent' !== $reason ) {
			set_post_thumbnail( $post_id, $this->attachment_id );
		}
		if ( 'unsupported' === $reason ) {
			remove_post_type_support( 'post', 'thumbnail' );
		}
		$this->visit_context( 'posts', $post_id );
		$this->assertSame( '', $this->render_thumbnail() );
	}

	/**
	 * Availability guards.
	 *
	 * @return array Guard cases.
	 */
	public function data_unavailable_thumbnails() : array {
		return array(
			array( 'absent' ),
			array( 'protected' ),
			array( 'unsupported' ),
		);
	}

	/**
	 * Singular posts intentionally retain an empty thumbnail container.
	 */
	public function test_singular_thumbnail_container_is_empty() {
		$post_id = self::factory()->post->create();
		set_post_thumbnail( $post_id, $this->attachment_id );
		$this->go_to( get_permalink( $post_id ) );
		the_post();
		$this->assertTrue( is_singular( 'post' ) );
		$document = $this->parse_markup( $this->render_thumbnail() );
		$this->assertSame( 0, $document->getElementsByTagName( 'img' )->length );
		$this->assertSame( 0, $document->getElementsByTagName( 'a' )->length );
		$this->assertSame( 1, $document->getElementsByTagName( 'div' )->length );
		$container = $document->getElementsByTagName( 'div' )->item( 0 );
		$this->assertSame( 'post-thumbnail', $container->getAttribute( 'class' ) );
		$this->assertSame( '', trim( $container->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
	}

	/**
	 * Audio/video attachments use the media-specific thumbnail support slug.
	 *
	 * @dataProvider data_media_attachments
	 * @param string $type    Attachment media type.
	 * @param string $mime    Attachment MIME type.
	 * @param bool   $support Whether the media-specific support is enabled.
	 */
	public function test_media_attachment_thumbnail( string $type, string $mime, bool $support ) {
		$post_id = self::factory()->attachment->create_object( 'media-fixture', 0, array( 'post_mime_type' => $mime ) );
		set_post_thumbnail( $post_id, $this->attachment_id );
		$this->visit_context( 'front', $post_id );
		remove_post_type_support( 'attachment', 'thumbnail' );
		if ( $support ) {
			add_post_type_support( 'attachment:' . $type, 'thumbnail' );
		} else {
			add_post_type_support( 'attachment', 'thumbnail' );
			remove_post_type_support( 'attachment:' . $type, 'thumbnail' );
		}
		$this->assertTrue( wp_attachment_is( $type ) );
		$markup = $this->render_thumbnail();
		if ( ! $support ) {
			$this->assertSame( '', $markup );
			return;
		}
		$document = $this->parse_markup( $markup );
		$this->assertSame( 1, $document->getElementsByTagName( 'a' )->length );
		$this->assertSame( 1, $document->getElementsByTagName( 'img' )->length );
		$image = $document->getElementsByTagName( 'img' )->item( 0 );
		$this->assertSame( $this->image_url( 'thumbnail-fixture-768x480.jpg' ), $image->getAttribute( 'src' ) );
		$this->assertSame( '(max-width: 768px) 90vw, 360px', $this->fallback_sizes( $image->getAttribute( 'sizes' ) ) );
	}

	/**
	 * Both supported and unsupported media attachment subtypes.
	 *
	 * @return array Media cases.
	 */
	public function data_media_attachments() : array {
		return array(
			array( 'audio', 'audio/mpeg', true ),
			array( 'video', 'video/mp4', true ),
			array( 'audio', 'audio/mpeg', false ),
			array( 'video', 'video/mp4', false ),
		);
	}

	/**
	 * A custom logo and general attachments keep their own policy in one request.
	 */
	public function test_custom_logo_and_other_images_do_not_inherit_thumbnail_sizes() {
		$post_id = self::factory()->post->create();
		set_post_thumbnail( $post_id, $this->attachment_id );
		$this->visit_context( 'posts', $post_id );
		set_theme_mod( 'custom_logo', $this->attachment_id );
		$other_id = self::factory()->attachment->create_object( 'other-image.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		wp_update_attachment_metadata( $other_id, wp_get_attachment_metadata( $this->attachment_id ) );

		foreach ( array( 'before', 'after' ) as $phase ) {
			$logo    = $this->parse_markup( get_custom_logo() )->getElementsByTagName( 'img' )->item( 0 );
			$general = $this->parse_markup( wp_get_attachment_image( $other_id, 'medium_large' ) )->getElementsByTagName( 'img' )->item( 0 );
			$this->assertContains( 'custom-logo', $logo->getAttribute( 'class' ), $phase );
			$this->assertSame( '(max-width: 768px) 90vw, 650px', $this->fallback_sizes( $logo->getAttribute( 'sizes' ) ), $phase );
			$this->assertSame( '(max-width: 768px) 90vw, 650px', $this->fallback_sizes( $general->getAttribute( 'sizes' ) ), $phase );
			$explicit = $this->parse_markup( wp_get_attachment_image( $other_id, 'medium_large', false, array( 'sizes' => '80px' ) ) )->getElementsByTagName( 'img' )->item( 0 );
			$this->assertSame( '80px', $this->fallback_sizes( $explicit->getAttribute( 'sizes' ) ), $phase );
			$this->render_thumbnail();
		}
	}

	/**
	 * Navigates WordPress queries, including a post loop on a static front page.
	 *
	 * @param string $context Request context.
	 * @param int    $post_id Post to render on the static front page.
	 */
	private function visit_context( string $context, int $post_id ) {
		$front_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$posts_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_id );
		update_option( 'page_for_posts', $posts_id );
		$urls = array(
			'front'   => get_permalink( $front_id ),
			'posts'   => get_permalink( $posts_id ),
			'archive' => get_category_link( 1 ),
		);
		$this->go_to( $urls[ $context ] );
		$this->assertSame( 'front' === $context, is_front_page() );
		$this->assertSame( 'posts' === $context, is_home() );
		the_post();
		if ( 'front' === $context ) {
			// Match the secondary post loop used by the static front-page template.
			$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Set the loop post for setup_postdata().
			setup_postdata( $GLOBALS['post'] );
		}
	}

	/**
	 * Updates sidebar state, including the front-end cache used by WordPress 4.5.
	 *
	 * @param bool $active Whether to put a widget in the primary sidebar.
	 */
	private function set_primary_sidebar( bool $active ) {
		wp_set_sidebars_widgets( array( 'sidebar-1' => $active ? array( 'text-1' ) : array() ) );
		$GLOBALS['_wp_sidebars_widgets'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Refresh the legacy sidebar cache.
	}

	/**
	 * Returns the upload URL for a deterministic metadata fixture.
	 *
	 * @param string $file Fixture filename.
	 * @return string Image URL.
	 */
	private function image_url( string $file ) : string {
		$uploads = wp_upload_dir();
		return $uploads['baseurl'] . '/' . $file;
	}

	/**
	 * Allows the native auto-sizes enhancement without weakening fallback checks.
	 *
	 * @param string $sizes Emitted sizes attribute.
	 * @return string Responsive fallback.
	 */
	private function fallback_sizes( string $sizes ) : string {
		return preg_replace( '/^auto,\s*/', '', $sizes );
	}

	/**
	 * Loads the same template interface used by entry header callers.
	 *
	 * @return string Rendered markup.
	 */
	private function render_thumbnail() : string {
		ob_start();
		get_template_part( 'template-parts/content/entry_thumbnail', get_post_type() );
		return ob_get_clean();
	}

	/**
	 * Parses observable markup without depending on attribute order.
	 *
	 * @param string $markup Rendered markup.
	 * @return DOMDocument Parsed document.
	 */
	private function parse_markup( string $markup ) : DOMDocument {
		$document = new DOMDocument();
		$document->loadHTML( '<!doctype html><html><body>' . $markup . '</body></html>' );
		return $document;
	}
}
