<?php
/**
 * Cart helpers for the product list: report current cart state (to reflect on
 * the archive) and set a product's quantity (add / update / remove).
 * Read state is applied client-side so the archive HTML stays cacheable.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Current cart as { product_id: qty } + total item count. */
function lager_ajax_cart_state() {
	$items = array();
	$count = 0;
	if ( function_exists( 'WC' ) && WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $ci ) {
			$pid           = (string) $ci['product_id'];
			$items[ $pid ] = ( isset( $items[ $pid ] ) ? $items[ $pid ] : 0 ) + $ci['quantity'];
		}
		$count = WC()->cart->get_cart_contents_count();
	}
	wp_send_json( array( 'items' => (object) $items, 'count' => $count ) );
}
// wc-ajax (not admin-ajax) so WooCommerce loads the frontend cart + session.
add_action( 'wc_ajax_lager_cart_state', 'lager_ajax_cart_state' );

/** Set a product's quantity in the cart: add if absent, update if present, remove at 0. */
function lager_ajax_set_cart_qty() {
	check_ajax_referer( 'lager_search', 'nonce' );

	$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$qty = isset( $_POST['quantity'] ) ? max( 0, (int) $_POST['quantity'] ) : 1;
	if ( ! $pid || ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error();
	}

	$key = null;
	foreach ( WC()->cart->get_cart() as $k => $ci ) {
		if ( (int) $ci['product_id'] === $pid ) {
			$key = $k;
			break;
		}
	}
	if ( $key ) {
		WC()->cart->set_quantity( $key, $qty, true ); // qty 0 removes the line
	} elseif ( $qty > 0 ) {
		WC()->cart->add_to_cart( $pid, $qty );
	}

	$newqty = 0;
	foreach ( WC()->cart->get_cart() as $ci ) {
		if ( (int) $ci['product_id'] === $pid ) {
			$newqty += $ci['quantity'];
		}
	}

	wp_send_json( array(
		'qty'       => $newqty,
		'count'     => WC()->cart->get_cart_contents_count(),
		'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
	) );
}
add_action( 'wc_ajax_lager_set_cart_qty', 'lager_ajax_set_cart_qty' );

/**
 * Stock is never a hard limit: orders are processed manually and stock is refilled,
 * so a customer may order any quantity regardless of the DB stock (even at 0).
 * Treat every product as in-stock + allow backorders — no per-product data change,
 * applies to future products too. (Stock quantities stay in the DB for reference.)
 */
add_filter( 'woocommerce_product_is_in_stock', '__return_true' );
add_filter( 'woocommerce_product_backorders_allowed', '__return_true' );
