<?php
/**
 * Admin-managed homepage category tile image. Adds a "Naslovna slika" image
 * field to each product category (Media Library), used by the homepage grid.
 * Falls back to the bundled theme banner when a category has no image set.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	acf_add_local_field_group( array(
		'key'      => 'group_lager_cattile',
		'title'    => 'Naslovna kartica',
		'fields'   => array(
			array(
				'key'           => 'field_lct_img',
				'label'         => 'Naslovna slika (kartica)',
				'name'          => 'naslovna_slika',
				'type'          => 'image',
				'return_format' => 'id',
				'preview_size'  => 'medium',
				'library'       => 'all',
				'instructions'  => 'Slika kategorije na početnoj strani (kvadratna). Ako je prazno, koristi se podrazumevana slika iz teme.',
			),
		),
		'location' => array(
			array(
				array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'product_cat' ),
			),
		),
	) );
} );

/**
 * Homepage tile image URL for a category term — the admin-managed image if set,
 * otherwise the given fallback URL.
 *
 * @param WP_Term|null $term     Category term.
 * @param string       $fallback Fallback image URL.
 * @return string
 */
function lager_category_tile_image( $term, $fallback ) {
	if ( $term && ! is_wp_error( $term ) && function_exists( 'get_field' ) ) {
		$img_id = (int) get_field( 'naslovna_slika', $term );
		if ( $img_id ) {
			$src = wp_get_attachment_image_url( $img_id, 'full' );
			if ( $src ) {
				return $src;
			}
		}
	}
	return $fallback;
}
