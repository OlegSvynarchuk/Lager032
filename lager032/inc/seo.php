<?php
/**
 * SEO for product archives.
 *
 * Clean category/shop pages stay indexable with a self-canonical. Filtered / sorted /
 * searched permutations (?fcat=…&fbrand=…&min_price=…&orderby=…&s=…) are set to
 * noindex,follow and canonicalised back to the clean base page — so crawlers don't
 * waste budget on the combinatorial explosion of facet URLs or treat them as duplicates.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True on the shop or a product category/brand archive. */
function lager_is_product_archive() {
	return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
}

/** The query-string keys that turn a clean archive into a filtered permutation. */
function lager_archive_filter_keys() {
	return array( 'fcat', 'fbrand', 'instock', 'min_price', 'max_price', 'q', 'orderby' );
}

/** True when the current archive carries any filter/sort/search parameter. */
function lager_is_filtered_archive() {
	if ( ! lager_is_product_archive() ) {
		return false;
	}
	foreach ( lager_archive_filter_keys() as $k ) {
		if ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
	}
	return false;
}

/** The clean base URL for the current archive (no filter params), pagination preserved. */
function lager_archive_base_url( $with_pagination = true ) {
	if ( is_product_taxonomy() ) {
		$base = get_term_link( get_queried_object() );
	} elseif ( function_exists( 'wc_get_page_permalink' ) ) {
		$base = wc_get_page_permalink( 'shop' );
	} else {
		$base = home_url( '/prodavnica/' );
	}
	if ( is_wp_error( $base ) || ! $base ) {
		return '';
	}
	$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	if ( $with_pagination && $paged > 1 ) {
		$base = user_trailingslashit( trailingslashit( $base ) . 'page/' . $paged, 'paged' );
	}
	return $base;
}

/** noindex,follow on filtered permutations. */
add_filter( 'wp_robots', function ( $robots ) {
	if ( lager_is_filtered_archive() ) {
		unset( $robots['index'] );
		$robots['noindex'] = true;
		// Only assert "follow" when the site isn't globally nofollow (e.g. blog_public=0 on
		// the dev link) — otherwise we'd emit a contradictory "nofollow, follow".
		if ( empty( $robots['nofollow'] ) ) {
			$robots['follow'] = true;
		}
	}
	return $robots;
} );

/** rel=canonical on product archives → clean base (filtered URLs collapse to page 1). */
add_action( 'wp_head', function () {
	if ( ! lager_is_product_archive() ) {
		return;
	}
	// Filtered URLs canonicalise to the clean base page 1; clean URLs self-canonical (with pagination).
	$canonical = lager_is_filtered_archive()
		? lager_archive_base_url( false )
		: lager_archive_base_url( true );

	if ( $canonical ) {
		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	}
}, 9 );
