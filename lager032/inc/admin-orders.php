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

/**
 * Hide WooCommerce's automated order notes in the admin.
 *
 * The "Order notes" panel mixes staff notes with WooCommerce's own system entries
 * ("Email 'New order' sent.", "Stock levels reduced: …", status changes) — untranslated
 * English on an otherwise Serbian screen, which reads as noise to the shop owner.
 *
 * These are hidden, not deleted: WooCommerce marks them with a `system-note` class, so the
 * audit trail stays intact in the database and reappears the moment this rule is removed.
 * Manual notes and "Add private note" are untouched.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$is_order  = $screen && in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true );
	if ( ! $is_order && ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	if ( ! $is_order ) {
		return;
	}
	wp_register_style( 'lager-admin-orders', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_enqueue_style( 'lager-admin-orders' );
	wp_add_inline_style( 'lager-admin-orders', '.order_notes li.system-note { display: none; }' );
} );

/**
 * Hide the "Custom fields" metabox on the order screen.
 *
 * It exposes raw internal meta (`is_vat_exempt: no` and similar) with editable inputs —
 * meaningless to the shop owner and easy to break an order with by accident. The data is
 * untouched; only the editor panel is removed.
 *
 * Runs at priority 999 because WordPress registers `postcustom` before firing this hook,
 * and covers both the legacy screen and HPOS.
 */
add_action( 'add_meta_boxes', function ( $screen_id ) {
	$order_screens = array( 'shop_order', 'woocommerce_page_wc-orders' );
	if ( function_exists( 'wc_get_page_screen_id' ) ) {
		$order_screens[] = wc_get_page_screen_id( 'shop-order' );
	}
	if ( ! in_array( $screen_id, $order_screens, true ) ) {
		return;
	}
	foreach ( array( 'normal', 'advanced', 'side' ) as $context ) {
		remove_meta_box( 'postcustom', $screen_id, $context );
	}
}, 999 );
