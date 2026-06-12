<?php
/**
 * Front-end assets.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function () {

	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	// Google fonts used in the Figma: Lato (hero), Inter (body/headings), Roboto Condensed (card labels).
	wp_enqueue_style(
		'lager032-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lato:wght@400;700;900&family=Roboto+Condensed:wght@400;700&display=swap',
		array(),
		null
	);

	// Main stylesheet. Versioned by file mtime so changes bust the cache.
	$css = '/assets/css/main.css';
	wp_enqueue_style(
		'lager032-main',
		$uri . $css,
		array( 'lager032-fonts' ),
		file_exists( $dir . $css ) ? filemtime( $dir . $css ) : LAGER032_VERSION
	);

	// Theme header (style.css) — kept for theme metadata; no rules of its own.
	wp_enqueue_style( 'lager032-style', get_stylesheet_uri(), array( 'lager032-main' ), LAGER032_VERSION );

	// Small front-end script (brand carousel, mobile nav) — loaded only if present.
	$js = '/assets/js/main.js';
	if ( file_exists( $dir . $js ) ) {
		wp_enqueue_script(
			'lager032-main',
			$uri . $js,
			array(),
			filemtime( $dir . $js ),
			true
		);
	}
} );

/**
 * Drop WooCommerce's default stylesheets on the pages we fully style ourselves
 * (shop + product category/brand archives, and single product). They inject
 * spacing/typography that clashes with the custom design. Cart/checkout/account
 * keep Woo's CSS so they stay usable until we design them.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_shop' ) ) {
		return;
	}
	if ( is_shop() || is_product_taxonomy() || is_product() ) {
		wp_dequeue_style( 'woocommerce-general' );
		wp_dequeue_style( 'woocommerce-layout' );
		wp_dequeue_style( 'woocommerce-smallscreen' );
	}
}, 99 );
