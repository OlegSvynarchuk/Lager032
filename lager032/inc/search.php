<?php
/**
 * Live (AJAX) product search — typeahead by product designation (oznaka / post_title).
 * The SKU is intentionally not searched: the client asked for oznaka-only matching.
 * Endpoint: admin-ajax.php?action=lager_search&q=…  →  JSON.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expose ajax url + nonce + endpoints to the front-end script.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! wp_script_is( 'lager032-main', 'enqueued' ) ) {
		return;
	}
	wp_localize_script( 'lager032-main', 'LagerSearch', array(
		'ajax'   => admin_url( 'admin-ajax.php' ),
		'nonce'  => wp_create_nonce( 'lager_search' ),
		'wcAdd'  => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '',
		'cartState' => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'lager_cart_state' ) : '',
		'setQty'    => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'lager_set_cart_qty' ) : '',
		'clearCart' => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'lager_clear_cart' ) : '',
		'minLen' => 2,
		'i18n'   => array(
			'placeholder' => __( 'Pretraži artikle...', 'lager032' ),
			'viewAll'     => __( 'Prikaži sve rezultate', 'lager032' ),
			'noResults'   => __( 'Nema rezultata za', 'lager032' ),
			'noResultsHint' => __( 'Proverite oznaku ili nas pozovite za upit.', 'lager032' ),
			'add'         => __( 'Dodaj u korpu', 'lager032' ),
			'added'       => __( 'Dodato', 'lager032' ),
			'inStock'     => __( 'Na stanju', 'lager032' ),
			'outStock'    => __( 'Nema na stanju', 'lager032' ),
			'withPdv'     => __( 'sa PDV-om', 'lager032' ),
		),
	) );
}, 20 );

/**
 * The search handler.
 */
function lager032_ajax_search() {
	check_ajax_referer( 'lager_search', 'nonce' );

	$q = isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['q'] ) ) ) : '';
	if ( mb_strlen( $q ) < 2 ) {
		wp_send_json( array( 'results' => array(), 'total' => 0 ) );
	}

	global $wpdb;
	$like   = '%' . $wpdb->esc_like( $q ) . '%';
	$starts = $wpdb->esc_like( $q ) . '%';

	// Code normalization: strip spaces/dashes/dots/slashes + lowercase so
	// "6205-2RS", "6205 2RS" and "62052rs" all match (compared to the same on the columns).
	$norm  = preg_replace( '/[\s.\-_\/]+/u', '', mb_strtolower( $q ) );
	if ( '' === $norm ) {
		$norm = mb_strtolower( $q );
	}
	$nlike = '%' . $wpdb->esc_like( $norm ) . '%';
	$ntit  = "REPLACE(REPLACE(REPLACE(REPLACE(LOWER(p.post_title),' ',''),'-',''),'.',''),'/','')";

	// Oznaka only — the SKU (šifra) is deliberately NOT searched; see lager_search_product_ids().
	// Ranked: title starts-with first, then contains.
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT p.ID
		 FROM {$wpdb->posts} p
		 WHERE p.post_type = 'product' AND p.post_status = 'publish'
		   AND ( p.post_title LIKE %s OR {$ntit} LIKE %s )
		 ORDER BY ( CASE WHEN p.post_title LIKE %s THEN 0 ELSE 1 END ), p.post_title ASC
		 LIMIT 8",
		$like, $nlike, $starts
	) );

	$results = array();
	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product ) {
			continue;
		}
		// Illustrative image: product's subcategory/category image (same as single product + shop list).
		$img_id = function_exists( 'lager_product_category_image_id' ) ? lager_product_category_image_id( $id ) : (int) get_option( 'lager_cat_placeholder_id' );
		$img    = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );

		$results[] = array(
			'id'      => (int) $id,
			'title'   => $product->get_name(),
			'sku'     => $product->get_sku(),
			'cat'     => function_exists( 'lager_product_primary_category_name' ) ? lager_product_primary_category_name( $id ) : '',
			'url'     => get_permalink( $id ),
			'price'   => html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, 'UTF-8' ),
			'inStock' => $product->is_in_stock(),
			'img'     => $img,
		);
	}

	$total = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*)
		 FROM {$wpdb->posts} p
		 WHERE p.post_type = 'product' AND p.post_status = 'publish'
		   AND ( p.post_title LIKE %s OR {$ntit} LIKE %s )",
		$like, $nlike
	) );

	// Also suggest matching categories (product names are codes, so a word like
	// "semering" won't hit any product title — but it should surface the category).
	$cats      = array();
	$cat_terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 40, 'orderby' => 'count', 'order' => 'DESC', 'name__like' => $q ) );
	if ( ! is_wp_error( $cat_terms ) && $cat_terms ) {
		// Collapse to the matched parent: drop any term whose ancestor also matched
		// (so "leza" shows just "Ležaj", not its 22 children). Subcategory-only matches stay.
		$matched = wp_list_pluck( $cat_terms, 'term_id' );
		foreach ( $cat_terms as $t ) {
			if ( 'uncategorized' === $t->slug ) {
				continue;
			}
			$skip = false;
			foreach ( get_ancestors( $t->term_id, 'product_cat' ) as $anc ) {
				if ( in_array( $anc, $matched, true ) ) {
					$skip = true;
					break;
				}
			}
			if ( $skip ) {
				continue;
			}
			$cl = get_term_link( $t );
			// Accurate count incl. subcategories (matches the category-page total).
			$cq = new WP_Query( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $t->term_id, 'include_children' => true ) ),
			) );
			$cats[] = array( 'name' => $t->name, 'url' => is_wp_error( $cl ) ? '' : $cl, 'count' => (int) $cq->found_posts );
			if ( count( $cats ) >= 4 ) {
				break;
			}
		}
	}

	wp_send_json( array(
		'categories' => $cats,
		'results'    => $results,
		'total'      => $total,
		'viewAll' => add_query_arg(
			array( 'q' => $q ),
			function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' )
		),
	) );
}
add_action( 'wp_ajax_lager_search', 'lager032_ajax_search' );
add_action( 'wp_ajax_nopriv_lager_search', 'lager032_ajax_search' );

/**
 * Cart-count fragment so the header badge updates after an AJAX add-to-cart.
 */
add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	$count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
	$fragments['span.cartbtn__count'] = '<span class="cartbtn__count"' . ( $count ? '' : ' hidden' ) . '>' . esc_html( $count ) . '</span>';
	return $fragments;
} );

/**
 * Product IDs matching a search query by title / oznaka only (raw AND code-normalized so
 * "6205-2RS" = "6205 2RS" = "62052rs"), ranked. Used by the archive list so its search
 * matches the typeahead instead of WordPress' weaker default title search.
 *
 * @param string $q     Search text.
 * @param int    $limit Max IDs (0 = all).
 * @return int[] Ranked product IDs.
 */
function lager_search_product_ids( $q, $limit = 0 ) {
	$q = trim( (string) $q );
	if ( mb_strlen( $q ) < 2 ) {
		return array();
	}
	global $wpdb;
	$like   = '%' . $wpdb->esc_like( $q ) . '%';
	$starts = $wpdb->esc_like( $q ) . '%';
	$norm   = preg_replace( '/[\s.\-_\/]+/u', '', mb_strtolower( $q ) );
	if ( '' === $norm ) {
		$norm = mb_strtolower( $q );
	}
	$nlike = '%' . $wpdb->esc_like( $norm ) . '%';
	$ntit  = "REPLACE(REPLACE(REPLACE(REPLACE(LOWER(p.post_title),' ',''),'-',''),'.',''),'/','')";
	// Oznaka only: the client asked for the search to match the product designation
	// (post_title / the import's NazivId column) and nothing else — no SKU, no meta.
	// The normalised comparison stays, so "6203 2RS", "6203-2rs" and "62032RS" all match.
	$sql   = "SELECT p.ID
		FROM {$wpdb->posts} p
		WHERE p.post_type = 'product' AND p.post_status = 'publish'
		  AND ( p.post_title LIKE %s OR {$ntit} LIKE %s )
		ORDER BY ( CASE WHEN p.post_title LIKE %s THEN 0 ELSE 1 END ), " . lager_title_order_sql( 'p.post_title' );
	$params = array( $like, $nlike, $starts );
	if ( $limit > 0 ) {
		$sql     .= ' LIMIT %d';
		$params[] = $limit;
	}
	return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) );
}
