<?php
/**
 * WP_Rig\WP_Rig\Tests\Unit\Post_Thumbnails\Component_Tests class
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Tests\Unit\Post_Thumbnails;

use WP_Rig\WP_Rig\Tests\Framework\Unit_Test_Case;
use Brain\Monkey\Functions;
use WP_Rig\WP_Rig\Post_Thumbnails\Component;

/**
 * Class unit-testing the post thumbnails component.
 *
 * @group hooks
 */
class Component_Tests extends Unit_Test_Case {

	/**
	 * The post thumbnails component instance.
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
		$this->assertSame( 'post_thumbnails', $this->component->get_slug() );
	}

	/**
	 * Tests that the component adds hooks correctly.
	 *
	 * @covers Component::initialize()
	 */
	public function test_initialize() {
		$this->component->initialize();

		$this->assertNotEquals( false, has_action( 'after_setup_theme', array( $this->component, 'action_add_post_thumbnail_support' ) ) );
		$this->assertNotEquals( false, has_action( 'after_setup_theme', array( $this->component, 'action_add_image_sizes' ) ) );
		$this->assertNotEquals( false, has_filter( 'post_thumbnail_size', array( $this->component, 'filter_post_thumbnail_size' ) ) );
	}

	/**
	 * Tests that filter_post_thumbnail_size returns 'medium_large' on front page.
	 *
	 * @covers Component::filter_post_thumbnail_size()
	 */
	public function test_filter_post_thumbnail_size_on_front_page() {
		Functions\when( 'is_front_page' )->justReturn( true );
		Functions\when( 'is_home' )->justReturn( false );

		$size = $this->component->filter_post_thumbnail_size( 'post-thumbnail', 123 );
		$this->assertSame( 'medium_large', $size );
	}

	/**
	 * Tests that filter_post_thumbnail_size returns 'medium_large' on home page.
	 *
	 * @covers Component::filter_post_thumbnail_size()
	 */
	public function test_filter_post_thumbnail_size_on_home() {
		Functions\when( 'is_front_page' )->justReturn( false );
		Functions\when( 'is_home' )->justReturn( true );

		$size = $this->component->filter_post_thumbnail_size( 'post-thumbnail', 123 );
		$this->assertSame( 'medium_large', $size );
	}

	/**
	 * Tests that filter_post_thumbnail_size preserves default size when not on front page or home.
	 *
	 * @covers Component::filter_post_thumbnail_size()
	 */
	public function test_filter_post_thumbnail_size_not_front_page() {
		Functions\when( 'is_front_page' )->justReturn( false );
		Functions\when( 'is_home' )->justReturn( false );

		$size = $this->component->filter_post_thumbnail_size( 'post-thumbnail', 123 );
		$this->assertSame( 'post-thumbnail', $size );
	}
}
