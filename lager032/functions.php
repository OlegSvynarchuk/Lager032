<?php
/**
 * Lager032 theme bootstrap.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LAGER032_VERSION' ) ) {
	define( 'LAGER032_VERSION', '0.1.0' );
}

require_once get_template_directory() . '/inc/setup.php';
require_once get_template_directory() . '/inc/icons.php';
require_once get_template_directory() . '/inc/enqueue.php';
require_once get_template_directory() . '/inc/contact.php';
require_once get_template_directory() . '/inc/search.php';
require_once get_template_directory() . '/inc/cart.php';
require_once get_template_directory() . '/inc/checkout.php';
require_once get_template_directory() . '/inc/seo.php';
require_once get_template_directory() . '/inc/emails.php';
require_once get_template_directory() . '/inc/admin-orders.php';
require_once get_template_directory() . '/inc/category-guides.php';
require_once get_template_directory() . '/inc/category-tile-image.php';
require_once get_template_directory() . '/inc/customizer.php';

/**
 * Best illustrative image for a product, as an attachment ID.
 *
 * Products carry no photos of their own, so we show their category visual:
 * the product's subcategory image, else its parent category image, else any
 * of its category images, else the shared placeholder. Returns 0 if none.
 *
 * @param int $product_id Product post ID.
 * @return int Attachment ID (0 when nothing is available).
 */
function lager_product_category_image_id( $product_id ) {
	$cats = wp_get_post_terms( $product_id, 'product_cat' );
	if ( ! $cats || is_wp_error( $cats ) ) {
		$cats = array();
	}

	$vid = 0;
	foreach ( $cats as $pc ) { // prefer a subcategory's own image, else its parent's.
		if ( $pc->parent ) {
			$vid = (int) get_term_meta( $pc->term_id, 'thumbnail_id', true );
			if ( ! $vid ) {
				$vid = (int) get_term_meta( $pc->parent, 'thumbnail_id', true );
			}
			if ( $vid ) {
				break;
			}
		}
	}
	if ( ! $vid ) {
		foreach ( $cats as $pc ) {
			$vid = (int) get_term_meta( $pc->term_id, 'thumbnail_id', true );
			if ( $vid ) {
				break;
			}
		}
	}
	if ( ! $vid ) {
		$vid = (int) get_option( 'lager_cat_placeholder_id' );
	}

	return $vid;
}

/**
 * Known manufacturer tokens. Product names have no dedicated brand field; many
 * end in a brand abbreviation (e.g. "... WBW", "... SKF"). We only treat the
 * trailing token as a brand when it matches this whitelist, so codes/dimensions
 * are never mistaken for a brand. Filterable to extend without code edits.
 *
 * @return string[] Upper-case brand tokens.
 */
function lager_brand_whitelist() {
	// Derived from the actual catalogue (frequency of the trailing title token). Bearings,
	// seals, belts and misc suppliers that appear as a brand mark at the end of product names.
	$brands = array(
		// Bearings.
		'SKF', 'FAG', 'INA', 'NSK', 'NTN', 'SNR', 'KOYO', 'NACHI', 'TIMKEN', 'IKO',
		'ZVL', 'ZKL', 'KLF-ZVL', 'CX', 'WBW', 'WBF', 'WTW', 'KFB-GERMANY', 'KONLON',
		'BBC-R', 'CODEX', 'INROLL', 'LZWB', 'FHY', 'KYK', 'ARB', 'INIS', 'BECO', 'HCH',
		'FAM', 'DALMIK', 'TMB', 'ELDON', 'KBS', 'CFB', 'DPI', 'MSC', 'URB', 'DKF', 'FLT',
		'GPZ', 'CRAFT', 'FBJ', 'NKE', 'PFI', 'HRB',
		// Seals / gaskets.
		'GUFERO', 'CORTECO', 'SIMRIT', 'ELRING', 'NAK', 'NQK', 'DICHTOMATIK', 'HEMOFLUID', 'HEMCO',
		// Belts.
		'SUPTEX', 'UNIBELT', 'OPTIBELT', 'CONTITECH', 'CONTINENTAL', 'GATES', 'DAYCO',
		'BANDO', 'MITSUBOSHI', 'PIX', 'ROULUNDS', 'FENNER', 'STOMIL', 'SAVA', 'TORPOL',
		// Misc.
		'WURTH', 'WÜRTH', 'RUBENA', 'LOCTITE', 'SASIC', 'WD-40', 'WIKO', 'Z&S',
	);
	return apply_filters( 'lager_brand_whitelist', $brands );
}

/**
 * Split a product title into a brand (trailing whitelisted token) + the
 * remaining title. Returns the title unchanged with empty brand when the last
 * word isn't a known brand.
 *
 * @param string $title Raw product title.
 * @return array{brand:string,title:string}
 */
function lager_extract_brand( $title ) {
	$title = trim( (string) $title );
	$out   = array( 'brand' => '', 'title' => $title );
	if ( '' === $title ) {
		return $out;
	}
	$parts = preg_split( '/\s+/', $title );
	if ( count( $parts ) < 2 ) {
		return $out; // a single token is the code itself, not "code + brand".
	}
	$last = end( $parts );
	// Normalize both sides to alnum-only upper-case so "KFB-GERMANY", "BBC-R", "WD-40", "Z&S"
	// match their trailing tokens regardless of punctuation.
	$norm = function ( $s ) {
		return strtoupper( preg_replace( '/[^\p{L}\p{N}]/u', '', (string) $s ) );
	};
	$key  = $norm( $last );
	$list = array_map( $norm, lager_brand_whitelist() );
	if ( '' !== $key && in_array( $key, $list, true ) ) {
		array_pop( $parts );
		$out['brand'] = trim( $last, " \t\n\r\0\x0B()[]" ); // drop stray brackets from the display token.
		$out['title'] = trim( implode( ' ', $parts ) );
	}
	return $out;
}

/**
 * Most specific category name for a product: a subcategory (has a parent) wins
 * over a top-level category. Empty string when the product has no category.
 *
 * @param int $product_id Product post ID.
 * @return string
 */
function lager_product_primary_category_name( $product_id ) {
	$cats = wp_get_post_terms( $product_id, 'product_cat' );
	if ( ! $cats || is_wp_error( $cats ) ) {
		return '';
	}
	foreach ( $cats as $c ) {
		if ( $c->parent ) {
			return $c->name;
		}
	}
	return $cats[0]->name;
}
