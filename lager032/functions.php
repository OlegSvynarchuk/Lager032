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
