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
		$this->assertNotEquals( false, has_filter( 'render_block', array( $this->component, 'filter_render_block_image' ) ) );
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
}

