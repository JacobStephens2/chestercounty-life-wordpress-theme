<?php
	$args = array(
		'tag' => 'featured-article',
		'date_query' => array(
			array(
				'after' => '2 months ago',
			)
		)
	);

	$my_query = new WP_Query( $args );


	while ( $my_query->have_posts() ) {
		$my_query->the_post();
		get_template_part( 'template-parts/content/entry_front_page', $my_query->get_post_type() );
	}
	?>
