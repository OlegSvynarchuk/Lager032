<?php
/**
 * Checkout & cart: trim the form for Serbian B2C, relabel WooCommerce's English
 * strings to Serbian Latin (site locale is en_US), and tidy the order flow.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Address fields (billing + shipping + account): drop B2B / unused fields and
 * relabel in Serbian. Serbia has no state list, so the state field is removed.
 */
add_filter( 'woocommerce_default_address_fields', function ( $fields ) {
	unset( $fields['company'], $fields['address_2'], $fields['state'] );

	$fields['first_name']['label'] = 'Ime';
	$fields['last_name']['label']  = 'Prezime';
	$fields['country']['label']    = 'Država';
	$fields['address_1']['label']       = 'Adresa';
	$fields['address_1']['placeholder'] = 'Ulica i broj';
	$fields['city']['label']       = 'Grad / Mesto';
	$fields['postcode']['label']   = 'Poštanski broj';

	return $fields;
} );

/** Checkout-only fields: require phone, relabel phone/email, soften order notes. */
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	if ( isset( $fields['billing']['billing_phone'] ) ) {
		$fields['billing']['billing_phone']['label']    = 'Telefon';
		$fields['billing']['billing_phone']['required'] = true;
		$fields['billing']['billing_phone']['priority'] = 90;
	}
	if ( isset( $fields['billing']['billing_email'] ) ) {
		$fields['billing']['billing_email']['label']    = 'Email adresa';
		$fields['billing']['billing_email']['priority'] = 100;
	}
	if ( isset( $fields['order']['order_comments'] ) ) {
		$fields['order']['order_comments']['label']       = 'Napomena uz porudžbinu (opciono)';
		$fields['order']['order_comments']['placeholder'] = 'Posebne instrukcije za dostavu, sprat, interfon...';
	}
	return $fields;
} );

/** "Place order" button. */
add_filter( 'woocommerce_order_button_text', function () {
	return 'Potvrdi porudžbinu';
} );

/**
 * Translate the handful of WooCommerce core strings that surface on the styled
 * cart/checkout to Serbian Latin (the site runs the en_US locale).
 */
add_filter( 'gettext', function ( $translated, $text, $domain ) {
	if ( 'woocommerce' !== $domain || is_admin() ) {
		return $translated;
	}
	static $map = null;
	if ( null === $map ) {
		$map = array(
			// Checkout headings / sections.
			'Billing details'             => 'Podaci za dostavu',
			'Ship to a different address?' => 'Dostava na drugu adresu?',
			'Additional information'      => 'Dodatne informacije',
			'Your order'                  => 'Vaša porudžbina',
			'Have a coupon?'              => 'Imate kod za popust?',
			'Click here to enter your code' => 'Unesite kod ovde',
			'Returning customer?'         => 'Već ste kupovali kod nas?',
			// Order review / totals.
			'Product'                     => 'Proizvod',
			'Quantity'                    => 'Količina',
			'Price'                       => 'Cena',
			'Subtotal'                    => 'Međuzbir',
			'Total'                       => 'Ukupno',
			'Shipping'                    => 'Dostava',
			'Cart totals'                 => 'Zbir korpe',
			// Coupons / buttons.
			'Coupon:'                     => 'Kupon:',
			'Coupon code'                 => 'Kod za popust',
			'Apply coupon'                => 'Primeni',
			'Update cart'                 => 'Ažuriraj korpu',
			'Proceed to checkout'         => 'Nastavi na plaćanje',
			'Place order'                 => 'Potvrdi porudžbinu',
			// Cart states / actions.
			'Your cart is currently empty.'  => 'Vaša korpa je trenutno prazna.',
			'Your cart is currently empty!'  => 'Vaša korpa je trenutno prazna.',
			'Return to shop'              => 'Nazad u prodavnicu',
			'Remove this item'            => 'Ukloni proizvod',
			'Calculate shipping'          => 'Izračunaj dostavu',
			'Update totals'               => 'Ažuriraj iznos',
		);
	}
	return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
}, 10, 3 );
