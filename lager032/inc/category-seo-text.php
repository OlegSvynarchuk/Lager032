<?php
/**
 * Admin-managed SEO text for each product category. Adds a "SEO tekst" WYSIWYG
 * field to each product_cat term, shown at the bottom of the category archive
 * (first page only) when filled — an empty field renders nothing.
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
		'key'      => 'group_lager_catseo',
		'title'    => 'SEO tekst',
		'fields'   => array(
			array(
				'key'          => 'field_lcs_seo',
				'label'        => 'SEO tekst',
				'name'         => 'seo_tekst',
				'type'         => 'wysiwyg',
				'tabs'         => 'all',
				'toolbar'      => 'full',
				'media_upload' => 1,
				'delay'        => 0,
				'instructions' => 'Tekst koji se prikazuje na dnu stranice kategorije (samo prva strana). Ako je prazno, ništa se ne prikazuje.',
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
 * SEO text for a product category term, or '' when none is set.
 *
 * @param WP_Term|null $term Category term.
 * @return string
 */
function lager_category_seo_text( $term ) {
	if ( $term && ! is_wp_error( $term ) && function_exists( 'get_field' ) ) {
		$txt = get_field( 'seo_tekst', $term );
		if ( ! is_string( $txt ) ) {
			$txt = '';
		}
		$txt = trim( $txt );
		if ( '' !== $txt ) {
			return $txt;
		}
	}
	return '';
}
