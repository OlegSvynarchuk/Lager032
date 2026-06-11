<?php
/**
 * One-time WooCommerce pricing setup for Lager032.
 * Run on the server:  wp eval-file setup-woo-pricing.php
 *
 * - Currency RSD, 0 decimals (whole-dinar display)
 * - Taxes enabled; prices entered NET (ex-tax); displayed INCL tax
 * - Inserts a single 20% standard "PDV" tax rate (idempotent)
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Must run via WP-CLI (wp eval-file).\n";
	return;
}

update_option( 'woocommerce_currency', 'RSD' );
update_option( 'woocommerce_price_num_decimals', 0 );
update_option( 'woocommerce_currency_pos', 'right_space' );
update_option( 'woocommerce_price_thousand_sep', '.' );
update_option( 'woocommerce_price_decimal_sep', ',' );

update_option( 'woocommerce_calc_taxes', 'yes' );
update_option( 'woocommerce_prices_include_tax', 'no' );   // prices entered NET
update_option( 'woocommerce_tax_display_shop', 'incl' );    // show gross in catalog
update_option( 'woocommerce_tax_display_cart', 'incl' );
update_option( 'woocommerce_tax_round_at_subtotal', 'no' );

// Insert 20% standard PDV rate only if no 20% standard rate exists.
global $wpdb;
$exists = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates
	 WHERE tax_rate = 20.0000 AND ( tax_rate_class = '' OR tax_rate_class IS NULL )"
);

if ( ! $exists && class_exists( 'WC_Tax' ) ) {
	WC_Tax::_insert_tax_rate( array(
		'tax_rate_country'  => '',
		'tax_rate_state'    => '',
		'tax_rate'          => '20.0000',
		'tax_rate_name'     => 'PDV',
		'tax_rate_priority' => 1,
		'tax_rate_compound' => 0,
		'tax_rate_shipping' => 1,
		'tax_rate_order'    => 0,
		'tax_rate_class'    => '',
	) );
	echo "Inserted 20% standard PDV tax rate.\n";
} else {
	echo "20% standard tax rate already present (skipped).\n";
}

echo "WooCommerce pricing settings applied (RSD, 0 decimals, PDV 20%, net entry / incl. display).\n";
