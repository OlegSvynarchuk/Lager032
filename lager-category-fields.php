<?php
/**
 * Plugin Name: Lager – Category Fields
 * Description: Adds editable "Šifra" and "Marža (%)" fields to WooCommerce
 *              product categories via ACF. Field keys match the WP-CLI
 *              importer (create-categories.sh): field_lager_sifra / field_lager_marza.
 * Author:      Pixels2Pixels
 *
 * Install: drop this file in wp-content/mu-plugins/ (create the folder if missing).
 *          Requires Advanced Custom Fields (free or Pro) to be active.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'acf/init', function () {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return; // ACF not active.
	}

	acf_add_local_field_group( array(
		'key'      => 'group_lager_category_fields',
		'title'    => 'Podaci kategorije',
		'fields'   => array(
			array(
				'key'          => 'field_lager_sifra',
				'label'        => 'Šifra (kod kategorije)',
				'name'         => 'sifra',
				'type'         => 'text',
				'instructions' => 'Interni kod kategorije iz Croonus-a (npr. 01.01). Koristi se za povezivanje proizvoda pri uvozu.',
				'wrapper'      => array( 'width' => '40' ),
			),
			array(
				'key'          => 'field_lager_marza',
				'label'        => 'Marža (%)',
				'name'         => 'marza',
				'type'         => 'number',
				'instructions' => 'Marža za kategoriju u procentima. Koristi se za kasniji obračun cena.',
				'min'          => 0,
				'max'          => 100,
				'step'         => 1,
				'append'       => '%',
				'wrapper'      => array( 'width' => '30' ),
			),
		),
		'location' => array(
			array(
				array(
					'param'    => 'taxonomy',
					'operator' => '==',
					'value'    => 'product_cat',
				),
			),
		),
		'menu_order'            => 0,
		'active'                => true,
		'show_in_rest'          => false,
		'description'           => 'Šifra i marža za WooCommerce kategorije proizvoda.',
	) );
} );

/**
 * Add "Šifra" and "Marža (%)" columns to the product category admin list table.
 */
add_filter( 'manage_edit-product_cat_columns', function ( $columns ) {
	$columns['sifra'] = __( 'Šifra', 'lager' );
	$columns['marza'] = __( 'Marža (%)', 'lager' );
	return $columns;
} );

add_filter( 'manage_product_cat_custom_column', function ( $content, $column, $term_id ) {
	if ( 'sifra' === $column ) {
		$sifra   = get_term_meta( $term_id, 'sifra', true );
		$content = ( '' !== $sifra ) ? esc_html( $sifra ) : '—';
	} elseif ( 'marza' === $column ) {
		$marza   = get_term_meta( $term_id, 'marza', true );
		$content = ( '' !== $marza ) ? esc_html( $marza ) . '%' : '—';
	}
	return $content;
}, 10, 3 );

/**
 * Keep the new columns narrow.
 */
add_action( 'admin_head-edit-tags.php', function () {
	if ( isset( $_GET['taxonomy'] ) && 'product_cat' === $_GET['taxonomy'] ) {
		echo '<style>.column-sifra{width:80px;}.column-marza{width:90px;text-align:center;}</style>';
	}
} );
