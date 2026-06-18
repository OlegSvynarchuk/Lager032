<?php
/**
 * One-page checkout for a Serbian B2C shop, modelled on the client's existing
 * site: customer + delivery details, a full cart table, net/PDV/total summary,
 * and "shipping paid by buyer" (no shipping calculation). All Serbian Latin.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Shipping: not calculated — the buyer pays the courier on delivery.
 * ---------------------------------------------------------------------- */
add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );

/* -------------------------------------------------------------------------
 * Merge cart + checkout into one page: the cart URL always lands on checkout.
 * ---------------------------------------------------------------------- */
add_action( 'template_redirect', function () {
	// Only redirect a non-empty cart, so an empty cart can't bounce between
	// cart and checkout in a loop.
	if ( function_exists( 'is_cart' ) && is_cart() && ! is_admin()
		&& WC()->cart && ! WC()->cart->is_empty() ) {
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}
} );

/**
 * Relabel the default address fields in Serbian too — so WooCommerce's address
 * i18n script can't flash the English labels (e.g. "Street address") onto our
 * fixed Serbia locale.
 */
add_filter( 'woocommerce_default_address_fields', function ( $f ) {
	$labels = array(
		'first_name' => 'Ime',
		'last_name'  => 'Prezime',
		'company'    => '',
		'country'    => 'Država',
		'address_1'  => 'Ulica',
		'address_2'  => '',
		'city'       => 'Grad',
		'state'      => '',
		'postcode'   => 'Poštanski broj',
	);
	foreach ( $labels as $key => $label ) {
		if ( isset( $f[ $key ] ) ) {
			$f[ $key ]['label'] = $label;
		}
	}
	return $f;
} );

/* -------------------------------------------------------------------------
 * Checkout fields — rebuilt to match the existing site.
 * Two visual sections (stored in 'lager_section'): "customer" and "delivery".
 * Granular delivery fields are saved as order meta.
 * ---------------------------------------------------------------------- */
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	$billing = array(
		// Podaci kupca.
		'billing_country'    => array(
			'type'    => 'hidden',
			'default' => 'RS',
		),
		'billing_first_name' => array(
			'label'        => 'Ime',
			'required'     => true,
			'class'        => array( 'form-row-first' ),
			'lager_section' => 'customer',
			'priority'     => 10,
		),
		'billing_last_name'  => array(
			'label'        => 'Prezime',
			'required'     => true,
			'class'        => array( 'form-row-last' ),
			'lager_section' => 'customer',
			'priority'     => 20,
		),
		'billing_email'      => array(
			'label'        => 'E-mail',
			'type'         => 'email',
			'required'     => true,
			'class'        => array( 'form-row-first' ),
			'validate'     => array( 'email' ),
			'lager_section' => 'customer',
			'priority'     => 30,
		),
		'billing_phone'      => array(
			'label'        => 'Mobilni telefon',
			'type'         => 'tel',
			'required'     => true,
			'class'        => array( 'form-row-last' ),
			'validate'     => array( 'phone' ),
			'lager_section' => 'customer',
			'priority'     => 40,
		),
		'billing_phone2'     => array(
			'label'        => 'Fiksni telefon',
			'type'         => 'tel',
			'required'     => false,
			'class'        => array( 'form-row-wide' ),
			'lager_section' => 'customer',
			'priority'     => 50,
		),
		// Podaci za dostavu.
		'billing_address_1'  => array(
			'label'        => 'Ulica',
			'required'     => true,
			'class'        => array( 'form-row-first' ),
			'lager_section' => 'delivery',
			'priority'     => 60,
		),
		'billing_house_no'   => array(
			'label'        => 'Broj zgrade/kuće',
			'required'     => false,
			'class'        => array( 'form-row-last' ),
			'lager_section' => 'delivery',
			'priority'     => 70,
		),
		'billing_floor'      => array(
			'label'        => 'Sprat',
			'required'     => false,
			'class'        => array( 'form-row-third' ),
			'lager_section' => 'delivery',
			'priority'     => 80,
		),
		'billing_apartment'  => array(
			'label'        => 'Broj stana',
			'required'     => false,
			'class'        => array( 'form-row-third' ),
			'lager_section' => 'delivery',
			'priority'     => 90,
		),
		'billing_intercom'   => array(
			'label'        => 'Interfon',
			'required'     => false,
			'class'        => array( 'form-row-third' ),
			'lager_section' => 'delivery',
			'priority'     => 100,
		),
		'billing_city'       => array(
			'label'        => 'Grad',
			'required'     => true,
			'class'        => array( 'form-row-first' ),
			'lager_section' => 'delivery',
			'priority'     => 110,
		),
		'billing_delivery_note' => array(
			'label'        => 'Dostavna poruka',
			'type'         => 'textarea',
			'required'     => false,
			'class'        => array( 'form-row-wide' ),
			'lager_section' => 'delivery',
			'priority'     => 120,
		),
	);

	$fields['billing'] = $billing;
	unset( $fields['shipping'], $fields['order'] ); // single address; delivery note replaces order notes.
	$fields['account'] = isset( $fields['account'] ) ? $fields['account'] : array();

	return $fields;
}, 20 );

/** The extra delivery fields we persist on the order. */
function lager_checkout_extra_fields() {
	return array(
		'billing_phone2'        => 'Fiksni telefon',
		'billing_house_no'      => 'Broj zgrade/kuće',
		'billing_floor'         => 'Sprat',
		'billing_apartment'     => 'Broj stana',
		'billing_intercom'      => 'Interfon',
		'billing_delivery_note' => 'Dostavna poruka',
	);
}

/** Save the extra fields onto the order. */
add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
	foreach ( array_keys( lager_checkout_extra_fields() ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$val = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$order->update_meta_data( '_' . $key, $val );
		}
	}
}, 10, 2 );

/** Show the extra fields in the admin order screen. */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	foreach ( lager_checkout_extra_fields() as $key => $label ) {
		$val = $order->get_meta( '_' . $key );
		if ( $val ) {
			echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $val ) . '</p>';
		}
	}
} );

/** Include the extra fields in order e-mails. */
add_action( 'woocommerce_email_customer_details', function ( $order ) {
	$rows = '';
	foreach ( lager_checkout_extra_fields() as $key => $label ) {
		$val = $order->get_meta( '_' . $key );
		if ( $val ) {
			$rows .= '<tr><th style="text-align:left;border:1px solid #e5e5e5;padding:8px;">' . esc_html( $label ) . '</th><td style="border:1px solid #e5e5e5;padding:8px;">' . esc_html( $val ) . '</td></tr>';
		}
	}
	if ( $rows ) {
		echo '<h2>Dodatni podaci za dostavu</h2><table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;">' . $rows . '</table>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}, 25 );

/** "Place order" button. */
add_filter( 'woocommerce_order_button_text', function () {
	return 'Potvrdi porudžbinu';
} );

/**
 * Translate the WooCommerce core strings that still surface (payment box,
 * validation notices, etc.) to Serbian Latin — the site runs the en_US locale.
 */
add_filter( 'gettext', function ( $translated, $text, $domain ) {
	if ( 'woocommerce' !== $domain || is_admin() ) {
		return $translated;
	}
	static $map = null;
	if ( null === $map ) {
		$map = array(
			'Your order'                    => 'Vaša porudžbina',
			'Place order'                   => 'Potvrdi porudžbinu',
			'Have a coupon?'                => 'Imate kod za popust?',
			'Click here to enter your code' => 'Unesite kod ovde',
			'Coupon code'                   => 'Kod za popust',
			'Apply coupon'                  => 'Primeni',
			'Subtotal'                      => 'Osnovica',
			'Total'                         => 'Ukupno za naplatu',
			'Product'                       => 'Naziv',
			'Quantity'                      => 'Količina',
			'Price'                         => 'Cena',
			'Your cart is currently empty.' => 'Vaša korpa je trenutno prazna.',
			'Your cart is currently empty!' => 'Vaša korpa je trenutno prazna.',
			'Return to shop'                => 'Nazad u prodavnicu',
			'Remove this item'              => 'Ukloni proizvod',
			'Please fill in your details above to see available payment methods.' => 'Popunite podatke iznad da biste videli načine plaćanja.',
			'Sorry, your session has expired.' => 'Vaša sesija je istekla.',
			'%s is a required field.'       => '%s je obavezno polje.',
		);
	}
	return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
}, 10, 3 );
