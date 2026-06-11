<?php
/**
 * Lager032 product importer.
 * Run on the server:
 *   wp eval-file import-products.php [offset] [limit]
 *   wp eval-file import-products.php 0 20      # test batch of 20
 *   wp eval-file import-products.php           # all products
 *
 * Idempotent: matches existing products by SKU (= IdBroj) and updates them.
 * Price: regular_price = round(VP × (1 + category marža / 100), 2)  [NET, ex-PDV]
 * Category: assigned by matching product `code` (KlBroj) to category term meta `sifra`.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "Run via WP-CLI: wp eval-file import-products.php [offset] [limit]\n";
	return;
}

$offset = isset( $args[0] ) ? (int) $args[0] : 0;
$limit  = isset( $args[1] ) ? (int) $args[1] : 0; // 0 = all

$json = __DIR__ . '/products.json';
if ( ! file_exists( $json ) ) {
	WP_CLI::error( "products.json not found next to the script ($json)." );
}
$data = json_decode( file_get_contents( $json ), true );
if ( ! is_array( $data ) ) {
	WP_CLI::error( 'Could not parse products.json.' );
}

// Build code -> [term_id, marza] from product_cat term meta `sifra`.
$terms  = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
$bycode = array();
foreach ( $terms as $t ) {
	$sifra = get_term_meta( $t->term_id, 'sifra', true );
	if ( '' !== $sifra ) {
		$bycode[ $sifra ] = array(
			'id'    => (int) $t->term_id,
			'marza' => (float) get_term_meta( $t->term_id, 'marza', true ),
		);
	}
}
WP_CLI::log( 'Category codes loaded: ' . count( $bycode ) );

$slice = $limit > 0 ? array_slice( $data, $offset, $limit ) : array_slice( $data, $offset );
WP_CLI::log( 'Importing ' . count( $slice ) . " products (offset=$offset, limit=" . ( $limit ?: 'all' ) . ") ..." );

$n = $created = $updated = $skipped = 0;

foreach ( $slice as $p ) {
	$sku   = (string) $p['sku'];
	$code  = (string) $p['code'];
	$name  = (string) $p['name'];
	$vp    = isset( $p['vp'] ) ? $p['vp'] : null;
	$stock = isset( $p['stock'] ) ? $p['stock'] : null;

	if ( ! isset( $bycode[ $code ] ) ) {
		WP_CLI::warning( "No category for code '$code' (sku $sku) — skipped." );
		$skipped++;
		continue;
	}
	$term_id = $bycode[ $code ]['id'];
	$marza   = $bycode[ $code ]['marza'];
	$net     = ( null !== $vp ) ? round( $vp * ( 1 + $marza / 100 ), 2 ) : null;

	$pid = wc_get_product_id_by_sku( $sku );
	if ( $pid ) {
		$product = wc_get_product( $pid );
		$updated++;
	} else {
		$product = new WC_Product_Simple();
		$product->set_sku( $sku );
		$created++;
	}

	$product->set_name( $name );
	$product->set_status( 'draft' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_category_ids( array( $term_id ) );

	if ( null !== $net ) {
		$product->set_regular_price( (string) $net );
		$product->set_price( (string) $net );
	}

	$product->set_manage_stock( true );
	if ( null !== $stock ) {
		$product->set_stock_quantity( $stock );
		$product->set_stock_status( $stock > 0 ? 'instock' : 'outofstock' );
	} else {
		$product->set_stock_status( 'outofstock' );
	}

	$pid = $product->save();

	// Base price + marža as ACF fields (with field-key references).
	update_post_meta( $pid, 'vp', $vp );
	update_post_meta( $pid, '_vp', 'field_lager_product_vp' );
	update_post_meta( $pid, 'marza', $marza );
	update_post_meta( $pid, '_marza', 'field_lager_product_marza' );

	$n++;
	if ( 0 === $n % 250 ) {
		WP_CLI::log( "  ... $n processed" );
		wp_cache_flush();
	}
}

WP_CLI::success( "Done. processed=$n created=$created updated=$updated skipped=$skipped" );
