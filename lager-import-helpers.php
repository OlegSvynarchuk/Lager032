<?php
/**
 * Plugin Name: Lager – Import Helpers
 * Description: Enables decimal stock quantities and adds product fields
 *              "Veleprodajna cena (VP)" + "Marža (%)" via ACF. Field keys
 *              match the importer: field_lager_product_vp / field_lager_product_marza.
 * Author:      Pixels2Pixels
 *
 * Install: wp-content/mu-plugins/ . Requires WooCommerce (+ ACF for the fields).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---- Decimal stock support (weight-based items, e.g. oils/grease) ---- */
add_filter( 'woocommerce_stock_amount', 'floatval' );

/* ---- Product fields: base price (VP) + marža, via ACF ---- */
add_action( 'acf/init', function () {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'key'      => 'group_lager_product_pricing',
		'title'    => 'Nabavni podaci (Lager)',
		'fields'   => array(
			array(
				'key'          => 'field_lager_product_vp',
				'label'        => 'Veleprodajna cena (VP)',
				'name'         => 'vp',
				'type'         => 'number',
				'instructions' => 'Bazna (veleprodajna) cena bez marže i PDV-a. Izvor za obračun prodajne cene.',
				'step'         => '0.01',
				'wrapper'      => array( 'width' => '40' ),
			),
			array(
				'key'          => 'field_lager_product_marza',
				'label'        => 'Marža (%)',
				'name'         => 'marza',
				'type'         => 'number',
				'instructions' => 'Marža primenjena pri uvozu (iz kategorije). Net cena = VP × (1 + marža/100).',
				'append'       => '%',
				'wrapper'      => array( 'width' => '30' ),
			),
		),
		'location' => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'product',
				),
			),
		),
		'menu_order'   => 0,
		'active'       => true,
		'show_in_rest' => false,
		'description'  => 'Bazna cena i marža za obračun prodajne cene.',
	) );
} );

/* ---- Admin product list: add "Net cena (bez PDV)" column after Price ---- */
add_filter( 'manage_edit-product_columns', function ( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'price' === $key ) {
			$new['net_price'] = __( 'Net cena (bez PDV)', 'lager' );
		}
	}
	return $new;
} );

add_action( 'manage_product_posts_custom_column', function ( $column, $post_id ) {
	if ( 'net_price' === $column ) {
		$product = wc_get_product( $post_id );
		$rp      = $product ? $product->get_regular_price() : '';
		echo ( '' !== $rp )
			? esc_html( number_format( (float) $rp, 2, ',', '.' ) ) . '&nbsp;' . esc_html( get_woocommerce_currency() )
			: '—';
	}
}, 10, 2 );
