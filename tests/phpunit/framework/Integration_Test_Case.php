<?php
/**
 * WP Rig Integration_Test_Case class
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Tests\Framework;

use WP_UnitTestCase;

/**
 * Integration test case base class.
 */
class Integration_Test_Case extends WP_UnitTestCase {

	/**
	 * Sets up separate static front and posts pages for query tests.
	 *
	 * @return array Permalinks keyed by request context.
	 */
	protected function set_up_static_homepage() : array {
		$front_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$posts_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_id );
		update_option( 'page_for_posts', $posts_id );

		return array(
			'front' => get_permalink( $front_id ),
			'posts' => get_permalink( $posts_id ),
		);
	}

	/**
	 * Tears down the environment after each test.
	 */
	public function tearDown() {

		// Fix for core test suite removing 'html5' theme support after each test.
		$html5_support = get_theme_support( 'html5' );
		parent::tearDown();
		if ( ! empty( $html5_support ) ) {
			add_theme_support( 'html5', $html5_support[0] );
		}
	}
}
