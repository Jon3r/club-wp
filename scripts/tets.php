<?php
add_action( 'elementor/query/kmdi_location_coaches', function ( $query ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}

	if ( ! is_singular() ) {
		return;
	}

	$location_id = (int) get_queried_object_id();
	if ( $location_id <= 0 ) {
		return;
	}

	$location_slug = (string) get_post_field( 'post_name', $location_id );

	$orders_by_id = [
		1611 => [ 1767, 1893, 1899, 1895 ], // Northern Beaches (add Anne & Nev IDs once created)
	];

	$orders_by_slug = [
		'kmdi-sydney' => [ 1767, 1895, 1893, 1897, 1903, 1899, 1901, 1905 ],
	];

	$desired = $orders_by_id[ $location_id ] ?? ( $orders_by_slug[ $location_slug ] ?? [] );
	$desired = array_values( array_filter( array_map( 'intval', $desired ), static function ( $id ) {
		return $id > 0;
	} ) );

	if ( empty( $desired ) ) {
		return;
	}

	$matching_ids = get_posts( [
		'post_type'              => 'coach',
		'post_status'            => 'publish',
		'posts_per_page'         => 50,
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'meta_query'             => [
			[
				'key'     => 'location',
				'value'   => (string) $location_id,
				'compare' => '=',
			],
		],
	] );

	if ( empty( $matching_ids ) ) {
		return;
	}

	$matching_ids = array_map( 'intval', $matching_ids );
	$ordered_ids  = [];

	foreach ( $desired as $id ) {
		if ( in_array( $id, $matching_ids, true ) && ! in_array( $id, $ordered_ids, true ) ) {
			$ordered_ids[] = $id;
		}
	}
	foreach ( $matching_ids as $id ) {
		if ( ! in_array( $id, $ordered_ids, true ) ) {
			$ordered_ids[] = $id;
		}
	}

	$query->set( 'post_type', 'coach' );
	$query->set( 'post__in', $ordered_ids );
	$query->set( 'orderby', 'post__in' );
	$query->set( 'posts_per_page', count( $ordered_ids ) );
	$query->set( 'ignore_sticky_posts', true );
} );