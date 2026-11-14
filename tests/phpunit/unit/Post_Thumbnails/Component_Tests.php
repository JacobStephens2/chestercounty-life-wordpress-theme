<?php
/**
 * WP_Rig\WP_Rig\Tests\Unit\Post_Thumbnails\Component_Tests class
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Tests\Unit\Post_Thumbnails;

use WP_Rig\WP_Rig\Tests\Framework\Unit_Test_Case;
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
	}
}
