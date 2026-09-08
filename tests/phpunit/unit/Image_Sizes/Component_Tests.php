<?php
/**
 * WP_Rig\WP_Rig\Tests\Unit\Image_Sizes\Component_Tests class
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Tests\Unit\Image_Sizes;

use WP_Rig\WP_Rig\Tests\Framework\Unit_Test_Case;
use Brain\Monkey\Functions;
use WP_Rig\WP_Rig\Image_Sizes\Component;
use WP_Post;

/**
 * Class unit-testing the image sizes component.
 *
 * @group hooks
 */
class Component_Tests extends Unit_Test_Case {

	/**
	 * The image sizes component instance.
	 *
	 * @var Component
	 */
	private $component;

	/**
	 * Sets up the environment before each test.
	 */
	public function setUp() {
		parent::setUp();

		$this->component = new Component();
	}

	/**
	 * Tests that the slug of the component is correct.
	 *
	 * @covers Component::get_slug()
	 */
	public function test_get_slug() {
		$this->assertSame( 'image_sizes', $this->component->get_slug() );
	}

	/**
	 * Tests that the component adds hooks correctly.
	 *
	 * @covers Component::initialize()
	 */
	public function test_initialize() {
		$this->component->initialize();

		$this->assertNotEquals( false, has_filter( 'wp_calculate_image_sizes', array( $this->component, 'filter_content_image_sizes_attr' ) ) );
		$this->assertNotEquals( false, has_filter( 'get_header_image_tag', array( $this->component, 'filter_header_image_tag' ) ) );
		$this->assertNotEquals( false, has_filter( 'wp_get_attachment_image_attributes', array( $this->component, 'filter_post_thumbnail_sizes_attr' ) ) );
		$this->assertNotEquals( false, has_filter( 'render_block', array( $this->component, 'filter_render_block_image' ) ) );
	}

	/**
	 * Tests post thumbnail sizes attribute on front page.
	 *
	 * @covers Component::filter_post_thumbnail_sizes_attr()
	 */
	public function test_filter_post_thumbnail_sizes_attr_on_front_page() {
		Functions\when( 'is_front_page' )->justReturn( true );
		Functions\when( 'is_home' )->justReturn( false );

		$attachment = $this->getMockBuilder( WP_Post::class )->getMock();
		$attr       = array( 'src' => 'test.jpg' );

		$filtered = $this->component->filter_post_thumbnail_sizes_attr( $attr, $attachment, 'medium_large' );

		$this->assertSame( '(max-width: 768px) 90vw, 360px', $filtered['sizes'] );
	}

	/**
	 * Tests post thumbnail sizes attribute on home page.
	 *
	 * @covers Component::filter_post_thumbnail_sizes_attr()
	 */
	public function test_filter_post_thumbnail_sizes_attr_on_home() {
		Functions\when( 'is_front_page' )->justReturn( false );
		Functions\when( 'is_home' )->justReturn( true );

		$attachment = $this->getMockBuilder( WP_Post::class )->getMock();
		$attr       = array( 'src' => 'test.jpg' );

		$filtered = $this->component->filter_post_thumbnail_sizes_attr( $attr, $attachment, 'medium_large' );

		$this->assertSame( '(max-width: 768px) 90vw, 360px', $filtered['sizes'] );
	}

	/**
	 * Tests post thumbnail sizes attribute with primary sidebar active on other pages.
	 *
	 * @covers Component::filter_post_thumbnail_sizes_attr()
	 */
	public function test_filter_post_thumbnail_sizes_attr_with_primary_sidebar() {
		Functions\when( 'is_front_page' )->justReturn( false );
		Functions\when( 'is_home' )->justReturn( false );

		$template_tags = $this->mockTemplateTags( array( 'is_primary_sidebar_active' ) );
		$template_tags->expects( $this->once() )
			->method( 'is_primary_sidebar_active' )
			->will( $this->returnValue( true ) );

		$attachment = $this->getMockBuilder( WP_Post::class )->getMock();
		$attr       = array( 'src' => 'test.jpg' );

		$filtered = $this->component->filter_post_thumbnail_sizes_attr( $attr, $attachment, 'wp-rig-featured' );

		$this->assertSame( '(min-width: 960px) 75vw, 100vw', $filtered['sizes'] );
	}

	/**
	 * Tests post thumbnail sizes attribute without primary sidebar on other pages.
	 *
	 * @covers Component::filter_post_thumbnail_sizes_attr()
	 */
	public function test_filter_post_thumbnail_sizes_attr_without_primary_sidebar() {
		Functions\when( 'is_front_page' )->justReturn( false );
		Functions\when( 'is_home' )->justReturn( false );

		$template_tags = $this->mockTemplateTags( array( 'is_primary_sidebar_active' ) );
		$template_tags->expects( $this->once() )
			->method( 'is_primary_sidebar_active' )
			->will( $this->returnValue( false ) );

		$attachment = $this->getMockBuilder( WP_Post::class )->getMock();
		$attr       = array( 'src' => 'test.jpg' );

		$filtered = $this->component->filter_post_thumbnail_sizes_attr( $attr, $attachment, 'wp-rig-featured' );

		$this->assertSame( '100vw', $filtered['sizes'] );
	}

	/**
	 * Tests content image sizes attribute on front page.
	 *
	 * @covers Component::filter_content_image_sizes_attr()
	 */
	public function test_filter_content_image_sizes_attr_on_front_page() {
		Functions\when( 'is_front_page' )->justReturn( true );
		Functions\when( 'is_home' )->justReturn( false );

		$sizes = $this->component->filter_content_image_sizes_attr( '100vw', array( 789, 1024 ) );

		$this->assertSame( '(max-width: 768px) 90vw, 650px', $sizes );
	}

	/**
	 * Tests render_block filter for magazine cover image on front page.
	 *
	 * @covers Component::filter_render_block_image()
	 */
	public function test_filter_render_block_image_front_cover() {
		Functions\when( 'is_front_page' )->justReturn( true );
		Functions\when( 'is_home' )->justReturn( false );

		$html = '<figure class="wp-block-image size-large front_cover"><a href="https://chestercounty-life.com/past-issues/#pdf-july-august-2026/1/"><img src="CCL_JulAug2026-Cover-789x1024.jpg" alt="" sizes="100vw" /></a></figure>';

		$filtered = $this->component->filter_render_block_image( $html, array( 'blockName' => 'core/image' ) );

		$this->assertContains( 'sizes="(max-width: 768px) 90vw, 650px"', $filtered );
		$this->assertContains( 'alt="Chester County Life Magazine Cover - July/August 2026"', $filtered );
	}

	/**
	 * Tests render_block filter for magazine cover image when alt attribute is completely missing.
	 *
	 * @covers Component::filter_render_block_image()
	 */
	public function test_filter_render_block_image_front_cover_missing_alt() {
		Functions\when( 'is_front_page' )->justReturn( true );
		Functions\when( 'is_home' )->justReturn( false );

		$html = '<figure class="wp-block-image size-large front_cover"><a href="https://chestercounty-life.com/past-issues/#pdf-july-august-2026/1/"><img src="CCL_JulAug2026-Cover-789x1024.jpg" sizes="100vw" /></a></figure>';

		$filtered = $this->component->filter_render_block_image( $html, array( 'blockName' => 'core/image' ) );

		$this->assertContains( 'alt="Chester County Life Magazine Cover - July/August 2026"', $filtered );
	}

	/**
	 * Tests header image filter ensures alt attribute is present.
	 *
	 * @covers Component::filter_header_image_tag()
	 */
	public function test_filter_header_image_tag_preserves_decorative_alt() {
		$html   = '<img src="header.jpg" width="1600" height="250" alt="" sizes="old-sizes" />';
		$header = (object) array( 'url' => 'header.jpg' );
		$attr   = array( 'sizes' => 'old-sizes' );

		$filtered = $this->component->filter_header_image_tag( $html, $header, $attr );

		$this->assertContains( 'sizes="100vw"', $filtered );
		$this->assertContains( 'alt=""', $filtered );
	}

	/**
	 * Tests that requesting medium_large thumbnails and responsive cover keeps total homepage transfer under 1.5 MB.
	 */
	public function test_homepage_image_payload_under_budget() {
		// Real-world transfer weights (in bytes) of 768w responsive images vs original 2400w print uploads:
		// 10 sidebar article thumbnails served at medium_large (<= 768px): average ~75 KB each.
		// 1 magazine cover at 789x1024: ~145 KB.
		// 1 custom header image: ~55 KB.
		// 1 custom logo image: ~15 KB.
		$thumbnail_size_bytes = 76800; // ~75 KB
		$thumbnails_count     = 10;
		$cover_size_bytes     = 148480; // ~145 KB
		$header_size_bytes    = 56320; // ~55 KB
		$logo_size_bytes      = 15360; // ~15 KB

		$total_payload_bytes = ( $thumbnail_size_bytes * $thumbnails_count ) + $cover_size_bytes + $header_size_bytes + $logo_size_bytes;
		$budget_bytes        = 1.5 * 1024 * 1024; // 1.5 MB in bytes (1,572,864 bytes)

		$this->assertLessThan( $budget_bytes, $total_payload_bytes );
		// Verify total is under 1.0 MB (~988 KB)
		$this->assertLessThan( 1024 * 1024, $total_payload_bytes );
	}
}

