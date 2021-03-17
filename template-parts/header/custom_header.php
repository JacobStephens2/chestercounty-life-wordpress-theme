<?php
/**
 * Template part for displaying the custom header media
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

if ( ! has_header_image() ) {
	return;
}

?>
<figure class="header-image">

	<div class="header_image_text_box" style="position: relative;">
		<p style="position: absolute;" class="over_header_image_text">Enjoy informative, entertaining, and<br>
		inspiring editorial that showcases the<br>
		Greater Chester County Community.<br>
		<a href="chestercounty-life.local/subscribe"><strong>Subscribe today!</strong></a><p>

		<?php the_header_image_tag(); ?>
	</div>

</figure><!-- .header-image -->
