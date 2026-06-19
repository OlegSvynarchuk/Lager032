<?php
/**
 * Admin orders list — add Telefon / Grad / Broj artikala columns (to match the old
 * site's order overview). Works with both HPOS and the legacy post-based list.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Insert the three columns right after "Total".
 */
function lager_admin_order_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'order_total' === $key ) {
			$new['lager_phone'] = __( 'Telefon', 'lager032' );
			$new['lager_city']  = __( 'Grad', 'lager032' );
			$new['lager_items'] = __( 'Broj artikala', 'lager032' );
		}
	}
	// Fallback if there's no order_total column for some reason.
	if ( ! isset( $new['lager_phone'] ) ) {
		$new['lager_phone'] = __( 'Telefon', 'lager032' );
		$new['lager_city']  = __( 'Grad', 'lager032' );
		$new['lager_items'] = __( 'Broj artikala', 'lager032' );
	}
	return $new;
}
add_filter( 'woocommerce_shop_order_list_table_columns', 'lager_admin_order_columns' ); // HPOS
add_filter( 'manage_edit-shop_order_columns', 'lager_admin_order_columns' );            // legacy

/**
 * Render the column values. $order is a WC_Order (HPOS) or a post ID (legacy).
 */
function lager_admin_order_column_content( $column, $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order );
	}
	if ( ! $order ) {
		return;
	}
	switch ( $column ) {
		case 'lager_phone':
			echo esc_html( $order->get_billing_phone() );
			break;
		case 'lager_city':
			echo esc_html( $order->get_billing_city() );
			break;
		case 'lager_items':
			echo esc_html( $order->get_item_count() );
			break;
	}
}
add_action( 'woocommerce_shop_order_list_table_custom_column', 'lager_admin_order_column_content', 10, 2 ); // HPOS
add_action( 'manage_shop_order_posts_custom_column', 'lager_admin_order_column_content', 10, 2 );           // legacy
