<?php
/**
 * Magazine cover rendering integration tests.
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Tests\Integration;

use WP_Rig\WP_Rig\Tests\Framework\Integration_Test_Case;

/**
 * Exercises saved Image blocks through WordPress and the active theme.
 */
class Magazine_Cover_Tests extends Integration_Test_Case {

	/**
	 * Skips rendering cases on WordPress versions without a block renderer.
	 */
	public function setUp() {
		parent::setUp();
		if ( ! function_exists( 'do_blocks' ) ) {
			$this->markTestSkipped( 'WordPress does not have a block renderer.' );
		}
	}

	/**
	 * A group keeps its caption and the sibling keeps its decorative alt and sizes.
	 */
	public function test_group_updates_only_the_magazine_cover() {
		$this->visit_context( 'front' );
		$cover   = '<figure class="wp-block-image front_cover"><a href="/past-issues/#pdf-july-august-2026/1/" target="_blank"><img src="/CCL_SepOct2026.jpg" alt="" sizes="100vw" width="400" /></a><figcaption>Read the current issue</figcaption></figure>';
		$sibling = '<figure class="wp-block-image"><img src="/decorative.jpg" alt="" sizes="42px" /></figure>';
		$group   = '<!-- wp:group --><div class="wp-block-group">'
			. $this->image_block( $cover ) . $this->image_block( $sibling )
			. '</div><!-- /wp:group -->';

		$rendered = do_blocks( $group );
		$this->assertContains( $sibling, $rendered );
		$this->assertContains( '<figure class="wp-block-image front_cover"><a href="/past-issues/#pdf-july-august-2026/1/" target="_blank"><img src="/CCL_SepOct2026.jpg" alt="Chester County Life Magazine Cover - July/August 2026" sizes="(max-width: 768px) 90vw, 650px" width="400" /></a><figcaption>Read the current issue</figcaption></figure>', $rendered );
	}

	/**
	 * The separate posts index recognizes the legacy Image block container.
	 */
	public function test_legacy_container_on_posts_index() {
		$this->visit_context( 'posts' );
		$markup   = "<div CLASS='wp-block-image front_cover'><figure class='aligncenter'><IMG SRC=/CCL_MayJun2026-Cover.jpg DATA-ALT='' DATA-SIZES='100vw' ALT = ' &#9;' SIZES = 'auto, 100vw' title='sizes=\"100vw\" > cover' /></figure></div>";
		$expected = "<div CLASS='wp-block-image front_cover'><figure class='aligncenter'><IMG SRC=/CCL_MayJun2026-Cover.jpg DATA-ALT='' DATA-SIZES='100vw' ALT = 'Chester County Life Magazine Cover - MayJun 2026' SIZES = '(max-width: 768px) 90vw, 650px' title='sizes=\"100vw\" > cover' /></figure></div>";
		$this->assertSame( $expected, do_blocks( $this->image_block( $markup ) ) );
	}

	/**
	 * A wrapper without Image block props cannot mark an image as a cover.
	 */
	public function test_nonlegacy_wrapper_passes_through() {
		$this->visit_context( 'front' );
		$markup = '<div class="front_cover"><figure class="wp-block-image"><img src="/cover.jpg" alt="" sizes="100vw" /></figure></div>';
		$this->assertSame( $markup, do_blocks( $this->image_block( $markup ) ) );
	}

	/**
	 * A directory name cannot supply the edition in place of a filename.
	 */
	public function test_directory_url_does_not_supply_cover_edition() {
		$this->visit_context( 'front' );
		$markup   = '<figure class="wp-block-image front_cover"><img src="/CCL_July2026/" alt="" /></figure>';
		$expected = '<figure class="wp-block-image front_cover"><img src="/CCL_July2026/" alt="Chester County Life Current Edition Magazine Cover" /></figure>';
		$this->assertSame( $expected, do_blocks( $this->image_block( $markup ) ) );
	}

	/**
	 * A caption and URL query can contain edition lookalikes.
	 */
	public function test_current_edition_ignores_caption_and_url_lookalikes() {
		$this->visit_context( 'front' );
		$before_image = '<figure class="wp-block-image front_cover"><a href="/issues?jump=%23pdf-january-february-2026">';
		$after_image  = '</a><figcaption><a href="#pdf-march-april-2026" title="</figcaption>">CCL_MarApr2026</a><img src="/caption.jpg" alt="" sizes="100vw" /></figcaption></figure>';
		$markup       = $before_image . '<img src="/CCL_January2026/cover.jpg?filename=CCL_May2026.jpg" data-alt="authored" />' . $after_image;
		$expected     = $before_image . '<img alt="Chester County Life Current Edition Magazine Cover" src="/CCL_January2026/cover.jpg?filename=CCL_May2026.jpg" data-alt="authored" />' . $after_image;
		$this->assertSame( $expected, do_blocks( $this->image_block( $markup ) ) );
	}

	/**
	 * Link edition formatting and translation still produce escaped attributes.
	 */
	public function test_translated_edition_label_is_escaped() {
		$this->visit_context( 'front' );
		$translate = static function ( $translated, $text, $domain ) {
			if ( 'wp-rig' === $domain && 'Chester County Life Magazine Cover - %s' === $text ) {
				return 'Read "%s" & more';
			}
			return $translated;
		};
		add_filter( 'gettext', $translate, 10, 3 );
		try {
			$markup   = '<figure class="wp-block-image front_cover"><a href="#pdf-special-edition"><img src="/cover.jpg" /></a></figure>';
			$expected = '<figure class="wp-block-image front_cover"><a href="#pdf-special-edition"><img alt="Read &quot;Special Edition&quot; &amp; more" src="/cover.jpg" /></a></figure>';
			$this->assertSame( $expected, do_blocks( $this->image_block( $markup ) ) );
		} finally {
			remove_filter( 'gettext', $translate, 10 );
		}
	}

	/**
	 * Markup without applicable cover updates passes through byte for byte.
	 *
	 * @dataProvider data_unchanged_images
	 * @param string $markup Saved Image block HTML.
	 */
	public function test_image_markup_passes_through( string $markup ) {
		$this->visit_context( 'front' );
		$this->assertSame( $markup, do_blocks( $this->image_block( $markup ) ) );
	}

	/**
	 * Authored attributes, marker lookalikes, and unsupported Image block shapes.
	 *
	 * @return array Saved Image block fixtures.
	 */
	public function data_unchanged_images() : array {
		return array(
			'authored alt and sizes'   => array( '<figure class="wp-block-image front_cover"><img src="/cover.jpg" alt="Cover photographed at sunset" sizes="40vw" /></figure>' ),
			'similar class'            => array( '<figure class="wp-block-image new-front_cover front_cover_alt Front_Cover"><img src="/cover.jpg" alt="" sizes="100vw" /></figure>' ),
			'filename and URL'        => array( '<figure class="wp-block-image"><a href="/front_cover"><img src="/front_cover.jpg" alt="" sizes="100vw" /></a></figure>' ),
			'caption marker'          => array( '<figure class="wp-block-image"><img src="/cover.jpg" alt="" sizes="100vw" /><figcaption><span class="front_cover">front_cover</span></figcaption></figure>' ),
			'data attribute marker'   => array( '<figure class="wp-block-image" data-class="front_cover" title=" class=\'front_cover\'"><img src="/cover.jpg" alt="" sizes="100vw" /></figure>' ),
			'marker on image'         => array( '<figure class="wp-block-image"><img src="/cover.jpg" class="front_cover" alt="" sizes="100vw" /></figure>' ),
			'multiple primary images' => array( '<figure class="wp-block-image front_cover"><img src="/one.jpg" alt="" sizes="100vw" /><img src="/two.jpg" alt="" sizes="100vw" /></figure>' ),
			'only a caption image'    => array( '<figure class="wp-block-image front_cover"><figcaption><img src="/cover.jpg" alt="" sizes="100vw" /></figcaption></figure>' ),
			'duplicate attributes'    => array( '<figure class="wp-block-image front_cover"><img src="/cover.jpg" alt="" ALT="Authored" sizes="100vw" /></figure>' ),
		);
	}

	/**
	 * Both the block name and the homepage context must match.
	 */
	public function test_other_block_types_and_requests_pass_through() {
		$markup = '<figure class="wp-block-image front_cover"><img src="/cover.jpg" alt="" sizes="100vw" /></figure>';
		$this->visit_context( 'front' );
		$this->assertSame( $markup, do_blocks( '<!-- wp:html -->' . $markup . '<!-- /wp:html -->' ) );
		$this->visit_context( 'post' );
		$this->assertSame( $markup, do_blocks( $this->image_block( $markup ) ) );
	}

	/**
	 * Navigates to a static front page, a separate posts index, or a single post.
	 *
	 * @param string $context Request context.
	 */
	private function visit_context( string $context ) {
		$urls         = $this->set_up_static_homepage();
		$urls['post'] = get_permalink( self::factory()->post->create() );
		$this->go_to( $urls[ $context ] );
		$this->assertSame( 'front' === $context, is_front_page() );
		$this->assertSame( 'posts' === $context, is_home() );
	}

	/**
	 * Serializes saved Image block markup for the block parser.
	 *
	 * @param string $markup Saved Image block HTML.
	 * @return string Serialized block.
	 */
	private function image_block( string $markup ) : string {
		return '<!-- wp:image -->' . $markup . '<!-- /wp:image -->';
	}
}
