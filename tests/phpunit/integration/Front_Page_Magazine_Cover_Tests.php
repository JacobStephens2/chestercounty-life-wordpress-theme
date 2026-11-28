<?php
/**
 * Magazine cover rendering through the static front-page template.
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Tests\Integration;

use DOMDocument;
use DOMXPath;
use WP_HTML_Tag_Processor;
use WP_Rig\WP_Rig\Tests\Framework\Integration_Test_Case;

/**
 * Exercises both homepage rendering paths with serialized blocks.
 */
class Front_Page_Magazine_Cover_Tests extends Integration_Test_Case {

	/**
	 * The first marked Image supplies the edition only if its primary image has a usable link.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_static_homepage_uses_the_primary_image_link() {
		if ( ! function_exists( 'do_blocks' ) ) {
			$this->markTestSkipped( 'WordPress does not have a block renderer.' );
		}

		register_post_type( 'dearpdf' );
		self::factory()->post->create(
			array(
				'post_type'   => 'dearpdf',
				'post_name'   => 'september-october-2026',
				'post_title'  => 'The Autumn Issue',
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => 'dearpdf',
				'post_name'   => 'may-june-2027',
				'post_title'  => 'Unpublished title',
				'post_status' => 'draft',
			)
		);

		$attachment_id = $this->create_cover_attachment();
		$cover_url     = '/past-issues/#PDF-september-october-2026/1/';
		$date_url      = '/past-issues/#pdf-may-june-2027/1/';
		$caption_url   = '/past-issues/#pdf-july-august-2027/1/';
		$cover_image   = '<img src="/saved-cover.jpg" alt="Autumn &amp; winter &quot;cover&quot;" />';
		$linked        = $this->image_block( $attachment_id, '<a href="' . $cover_url . '">' . $cover_image . '</a>' );
		$caption_only  = $this->image_block( $attachment_id, $cover_image . '<figcaption><a href="' . $caption_url . '">Browse the issue</a></figcaption>' );
		$ambiguous     = $this->image_block( $attachment_id, '<a href="' . $date_url . '">' . $cover_image . '<img src="/other-cover.jpg" alt="Another cover" /></a>' );
		$date_cover    = $this->image_block( $attachment_id, '<a href="' . $date_url . '"><img src="/saved-cover.jpg" alt="" /></a>' );

		$urls = $this->set_up_static_homepage();
		wp_update_post(
			array(
				'ID'             => get_option( 'page_on_front' ),
				'comment_status' => 'closed',
				'post_content'   => $this->columns_block( 'linked', $linked )
					. $this->columns_block( 'caption-only', $caption_only )
					. $this->columns_block( 'ambiguous-first', $ambiguous . $linked )
					. $this->columns_block( 'date-cover', $date_cover ),
			)
		);
		$this->go_to( $urls['front'] );
		$this->assertTrue( is_front_page() && is_page() );
		$template = get_front_page_template();
		$this->assertSame( TESTS_THEME_DIR . '/front-page.php', $template );

		ob_start();
		try {
			load_template( $template );
			$markup = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		$document = new DOMDocument();
		$document->loadHTML( $markup, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		$xpath   = new DOMXPath( $document );
		$layouts = $xpath->query( '//div[@class="ccl-edition-layout"]' );

		$this->assertSame( $caption_url, $xpath->evaluate( 'string(//div[@id="caption-only"]//figcaption/a/@href)' ) );
		$this->assertSame( 'Browse the issue', $xpath->evaluate( 'string(//div[@id="caption-only"]//figcaption/a)' ) );
		$this->assertSame( 0, $xpath->query( '//div[@id="caption-only"]//a[img]' )->length );
		$this->assertSame( 3, $xpath->query( '//div[@id="ambiguous-first"]//img' )->length );
		$this->assertSame( $cover_url, $xpath->evaluate( 'string(//div[@id="ambiguous-first"]//figure[2]/a/@href)' ) );

		if ( ! class_exists( WP_HTML_Tag_Processor::class ) ) {
			$this->assertSame( 0, $layouts->length );
			$this->assertSame( $cover_url, $xpath->evaluate( 'string(//div[@id="linked"]//a[img]/@href)' ) );
			return;
		}

		$this->assertSame( 2, $layouts->length );
		$edition = $layouts->item( 0 );
		$this->assertSame( 'The Autumn Issue', $xpath->evaluate( 'string(.//h1)', $edition ) );
		$this->assertContains( 'No. 125', $xpath->evaluate( 'string(.//p[@class="ccl-home-eyebrow"])', $edition ) );
		$this->assertSame( 'Read the issue', $xpath->evaluate( 'string(.//a[@class="ccl-read-button"])', $edition ) );
		$this->assertSame( $cover_url, $xpath->evaluate( 'string(.//a[@class="ccl-read-button"]/@href)', $edition ) );
		$this->assertSame( $cover_url, $xpath->evaluate( 'string(.//a[@class="ccl-edition-cover"]/@href)', $edition ) );
		$image = $xpath->query( './/a[@class="ccl-edition-cover"]/img', $edition )->item( 0 );
		$this->assertSame( 'Autumn & winter "cover"', $image->getAttribute( 'alt' ) );
		$this->assertContains( '/cover-fixture-large.jpg', $image->getAttribute( 'src' ) );
		$this->assertSame(
			'(max-width: 40rem) calc(100vw - 2rem), (max-width: 49rem) 38rem, (max-width: 85rem) 49vw, 40rem',
			preg_replace( '/^auto, /', '', $image->getAttribute( 'sizes' ) )
		);
		$this->assertSame( 'May/June 2027', $xpath->evaluate( 'string(.//h1)', $layouts->item( 1 ) ) );
		$this->assertSame( 'Current edition', $xpath->evaluate( 'string(.//p[@class="ccl-home-eyebrow"])', $layouts->item( 1 ) ) );
	}

	/**
	 * Creates deterministic original and large images using attachment metadata.
	 *
	 * @return int Attachment ID.
	 */
	private function create_cover_attachment() : int {
		$attachment_id = self::factory()->attachment->create_object( 'cover-fixture.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'width'  => 1200,
				'height' => 1500,
				'file'   => 'cover-fixture.jpg',
				'sizes'  => array(
					'large' => array(
						'file'      => 'cover-fixture-large.jpg',
						'width'     => 819,
						'height'    => 1024,
						'mime-type' => 'image/jpeg',
					),
				),
			)
		);
		return $attachment_id;
	}

	/**
	 * Serializes an Image whose cover marker is in its saved block attributes.
	 *
	 * @param int    $attachment_id Cover attachment ID.
	 * @param string $markup        Contents of the Image figure.
	 * @return string Serialized Image block.
	 */
	private function image_block( int $attachment_id, string $markup ) : string {
		return '<!-- wp:image {"id":' . $attachment_id . ',"className":"front_cover"} -->'
			. '<figure class="wp-block-image">' . $markup . '</figure><!-- /wp:image -->';
	}

	/**
	 * Places the images in a Group nested inside a marked Columns block.
	 *
	 * @param string $anchor Saved Columns anchor.
	 * @param string $images Serialized Image blocks in authored order.
	 * @return string Serialized Columns block.
	 */
	private function columns_block( string $anchor, string $images ) : string {
		return '<!-- wp:columns {"className":"first_column","anchor":"' . $anchor . '"} -->'
			. '<div id="' . $anchor . '" class="wp-block-columns first_column"><!-- wp:column --><div class="wp-block-column">'
			. '<!-- wp:group --><div class="wp-block-group">' . $images . '</div><!-- /wp:group -->'
			. '</div><!-- /wp:column --></div><!-- /wp:columns -->';
	}
}
