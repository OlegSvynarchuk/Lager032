<?php
/**
 * Plugin Name: Lager – Auto Reprice
 * Description: Keeps product selling prices derived from base price (VP) and the
 *              category marža. Editing a category's marža recomputes net prices
 *              for all its products; editing a product's VP recomputes that product.
 *              net = round(VP × (1 + marža/100), 2)  — PDV is added by Woo on top.
 * Author:      Pixels2Pixels
 *
 * Install: wp-content/mu-plugins/ . Requires WooCommerce (+ ACF for the fields).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Net (ex-PDV) price from base + marža. */
function lager_calc_net( $vp, $marza ) {
	if ( '' === $vp || null === $vp ) {
		return null;
	}
	return round( (float) $vp * ( 1 + (float) $marza / 100 ), 2 );
}

/** The governing marža for a product = marža of its first product_cat term that has one. */
function lager_product_category_marza( $product_id ) {
	$term_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $term_ids ) ) {
		return null;
	}
	foreach ( $term_ids as $tid ) {
		$m = get_term_meta( $tid, 'marza', true );
		if ( '' !== $m ) {
			return (float) $m;
		}
	}
	return null;
}

/** Recompute and store one product's net price from VP × category marža. */
function lager_reprice_product( $product_id ) {
	static $busy = false;
	if ( $busy ) {
		return;
	}
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return;
	}
	$vp = get_post_meta( $product_id, 'vp', true );
	if ( '' === $vp ) {
		return; // no base price -> leave as-is
	}
	$marza = lager_product_category_marza( $product_id );
	if ( null === $marza ) {
		return;
	}
	$net = lager_calc_net( $vp, $marza );
	if ( null === $net ) {
		return;
	}

	// Keep the product's marža snapshot in sync with its category.
	if ( (string) get_post_meta( $product_id, 'marza', true ) !== (string) $marza ) {
		update_post_meta( $product_id, 'marza', $marza );
	}

	// Only write the price if it actually changed.
	if ( (string) $product->get_regular_price() !== (string) $net ) {
		$busy = true;
		$product->set_regular_price( (string) $net );
		$product->set_price( (string) $net );
		$product->save();
		$busy = false;
	}
}

/** Reprice every product in a category (background-batched when large). */
function lager_reprice_category( $term_id ) {
	if ( '' === get_term_meta( $term_id, 'marza', true ) ) {
		return;
	}
	$ids = get_posts( array(
		'post_type'   => 'product',
		'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
		'fields'      => 'ids',
		'numberposts' => -1,
		'tax_query'   => array(
			array(
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => $term_id,
				'include_children' => false, // each subcategory has its own marža
			),
		),
	) );
	if ( empty( $ids ) ) {
		return;
	}

	if ( function_exists( 'as_enqueue_async_action' ) && count( $ids ) > 50 ) {
		foreach ( array_chunk( $ids, 100 ) as $batch ) {
			as_enqueue_async_action( 'lager_reprice_batch', array( $batch ), 'lager' );
		}
	} else {
		foreach ( $ids as $id ) {
			lager_reprice_product( $id );
		}
	}
}

add_action( 'lager_reprice_batch', function ( $ids ) {
	foreach ( (array) $ids as $id ) {
		lager_reprice_product( $id );
	}
} );

/**
 * Trigger on ACF save:
 *  - product edit  -> reprice that product (e.g. VP changed)
 *  - product_cat   -> reprice all products in that category (marža changed)
 */
add_action( 'acf/save_post', function ( $post_id ) {

	// Product saved.
	if ( is_numeric( $post_id ) && 'product' === get_post_type( $post_id ) ) {
		lager_reprice_product( (int) $post_id );
		return;
	}

	// Term saved (ACF uses "term_123"; older "product_cat_123").
	$term_id = 0;
	if ( is_string( $post_id ) ) {
		if ( 0 === strpos( $post_id, 'term_' ) ) {
			$term_id = (int) substr( $post_id, 5 );
		} elseif ( preg_match( '/^product_cat_(\d+)$/', $post_id, $m ) ) {
			$term_id = (int) $m[1];
		}
	}
	if ( $term_id ) {
		$term = get_term( $term_id );
		if ( $term && ! is_wp_error( $term ) && 'product_cat' === $term->taxonomy ) {
			lager_reprice_category( $term_id );
		}
	}
}, 20 );
