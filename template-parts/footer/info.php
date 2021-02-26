<?php
/**
 * Template part for displaying the footer info
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

?>

<div class="site-info">
	<p class="footer-paragraph-p">Chester County Life is published bi-monthly by Superior Publications, Inc., 103 Sean Drive, Downingtown, PA 19335. The entire contents of Chester County Life magazine is copyrighted and may not be reproduced in whole or part without the express written consent of the publisher, Superior Publications, Inc. The publisher accepts no responsibility for products, services, or promotions advertised herein, and does not assume any liability for errors and/or omissions.</p>
	<p class="footer-paragraph-p">We reserve the right to edit, rewrite, or effuse submitted material.</p>
	<p class="footer-paragraph-p">&#169; Copyright <?php echo esc_html( gmdate( 'Y' ) ); ?> Chester County Life. All rights reserved.
			<?php
			if ( function_exists( 'the_privacy_policy_link' ) ) {
				the_privacy_policy_link();
			}
			?>
	</p>
</div><!-- .site-info -->
