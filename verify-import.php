<?php
/** Quick integrity check after import. Run: wp eval-file verify-import.php */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }

$counts = wp_count_posts( 'product' );
WP_CLI::log( 'draft products       : ' . $counts->draft );
WP_CLI::log( 'published products   : ' . $counts->publish );

$all = get_posts( array(
	'post_type'   => 'product',
	'post_status' => array( 'draft', 'publish', 'pending', 'private' ),
	'fields'      => 'ids',
	'numberposts' => -1,
) );
WP_CLI::log( 'total products       : ' . count( $all ) );

$no_cat = $no_price = $no_vp = 0;
$min = PHP_INT_MAX; $max = 0;
foreach ( $all as $id ) {
	$terms = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'ids' ) );
	if ( empty( $terms ) || is_wp_error( $terms ) ) { $no_cat++; }

	$rp = get_post_meta( $id, '_regular_price', true );
	if ( '' === $rp ) { $no_price++; }
	else { $min = min( $min, (float) $rp ); $max = max( $max, (float) $rp ); }

	$vp = get_post_meta( $id, 'vp', true );
	if ( '' === $vp ) { $no_vp++; }
}

WP_CLI::log( 'products w/o category: ' . $no_cat );
WP_CLI::log( 'products w/o price   : ' . $no_price );
WP_CLI::log( 'products w/o VP meta : ' . $no_vp );
WP_CLI::log( 'net price range      : ' . $min . ' .. ' . $max );
WP_CLI::success( 'Integrity check complete.' );
