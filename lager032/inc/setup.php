<?php
/**
 * Theme setup: supports, menus, WooCommerce.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', function () {

	// Let WordPress manage the document title.
	add_theme_support( 'title-tag' );

	// Featured images (used by products and posts).
	add_theme_support( 'post-thumbnails' );

	// Clean HTML5 markup for core-generated output.
	add_theme_support( 'html5', array(
		'search-form',
		'gallery',
		'caption',
		'style',
		'script',
		'navigation-widgets',
	) );

	// Logo is uploaded in Appearance → Customize → Site Identity.
	add_theme_support( 'custom-logo', array(
		'height'      => 60,
		'width'       => 220,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	// WooCommerce + product gallery features.
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	// Navigation menus from the Figma header.
	register_nav_menus( array(
		'primary' => __( 'Glavni meni (header)', 'lager032' ),
		'footer'  => __( 'Footer meni', 'lager032' ),
	) );

	// Translations.
	load_theme_textdomain( 'lager032', get_template_directory() . '/languages' );
} );

/**
 * Content width for embeds/images.
 */
add_action( 'after_setup_theme', function () {
	$GLOBALS['content_width'] = 1200;
} );
