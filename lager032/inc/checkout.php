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

/* Drop WooCommerce's English "(incl. VAT)" / "(ex. VAT)" tax suffix — the order
 * summary already itemises PDV, so the per-line tag is just noise. */
add_filter( 'woocommerce_countries_inc_tax_or_vat', '__return_empty_string' );
add_filter( 'woocommerce_countries_ex_tax_or_vat', '__return_empty_string' );

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
			'label'        => 'Broj',
			'required'     => true,
			'class'        => array( 'form-row-last' ),
			'lager_section' => 'delivery',
			'priority'     => 70,
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
		'billing_house_no'      => 'Broj',
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

/**
 * Add-to-cart notice. WooCommerce assembles it from _n()/__() calls, so the gettext map
 * above can't reach it cleanly (the plural form never matches a single lookup). Replacing
 * the finished message via WooCommerce's own filter is simpler and survives updates.
 */
add_filter( 'wc_add_to_cart_message_html', function ( $message, $products ) {
	$titles = array();
	foreach ( (array) $products as $product_id => $qty ) {
		$title = wp_strip_all_tags( get_the_title( $product_id ) );
		if ( $title ) {
			$titles[] = $title;
		}
	}
	$names = implode( ', ', $titles );
	$text  = $names
		? sprintf( ( count( $titles ) > 1 ? '„%s“ su dodati u korpu.' : '„%s“ je dodat u korpu.' ), $names )
		: 'Proizvod je dodat u korpu.';

	return sprintf(
		'%1$s <a href="%2$s" class="button wc-forward">%3$s</a>',
		esc_html( $text ),
		// This site treats /korpa/ (checkout) as the cart — same target as the header cart
		// button, the tab bar and the mini-cart. wc_get_cart_url() would point at the unused /cart/.
		esc_url( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : wc_get_cart_url() ),
		esc_html( 'Pogledaj korpu' )
	);
}, 10, 2 );

/**
 * Checkout privacy notice in Serbian.
 *
 * WooCommerce reads this from an option and falls back to an English default, so the
 * gettext map above can't reliably reach it. This supplies a Serbian default but stands
 * aside the moment someone fills in WooCommerce → Settings → Accounts & Privacy, so the
 * admin setting always wins. `[privacy_policy]` is WooCommerce's own placeholder and is
 * swapped for a link to the privacy page.
 */
add_filter( 'woocommerce_get_privacy_policy_text', function ( $text, $type ) {
	// Only replace WooCommerce's English boilerplate. Checking whether the option is empty
	// is not enough: the setup wizard writes that default INTO the option, so it is never
	// empty. Matching the wording means any custom text set in the admin is left untouched.
	if ( ! in_array( $type, array( 'checkout', 'registration' ), true )
		|| false === stripos( $text, 'Your personal data will be used' ) ) {
		return $text;
	}

	return 'checkout' === $type
		? 'Vaši lični podaci biće korišćeni za obradu Vaše porudžbine, poboljšanje iskustva na ovom sajtu i u druge svrhe opisane u našoj [privacy_policy].'
		: 'Vaši lični podaci biće korišćeni za poboljšanje iskustva na ovom sajtu, upravljanje pristupom Vašem nalogu i u druge svrhe opisane u našoj [privacy_policy].';
}, 10, 2 );

/**
 * Remove WooCommerce's privacy notice from the checkout.
 *
 * Removing the action (rather than filtering the text to an empty string) also drops the
 * wrapper div, so no empty box is left in the payment panel. The registration variant is
 * untouched, and the Serbian text above still applies wherever it does render.
 */
add_action( 'init', function () {
	remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
} );

/**
 * Mirror the delivery address into the order's shipping fields.
 *
 * The checkout deliberately collects ONE address (Podaci za dostavu) into billing_*, and
 * the separate shipping step is switched off (see woocommerce_cart_needs_shipping_address
 * above). The consequence was that WooCommerce's "Dostava" panel — and anything built from
 * it, like a courier label or a shipping export — stayed empty even though the customer had
 * typed an address.
 *
 * Runs at priority 20, after the extra fields are written at 10, so the house number is
 * available and can be folded into the street line the way a Serbian address reads
 * ("Gračanička 2") rather than landing on its own line.
 */
add_action( 'woocommerce_checkout_create_order', function ( $order ) {
	$street = trim( (string) $order->get_billing_address_1() );
	if ( '' === $street ) {
		return;
	}
	$house = trim( (string) $order->get_meta( '_billing_house_no' ) );

	$order->set_shipping_first_name( $order->get_billing_first_name() );
	$order->set_shipping_last_name( $order->get_billing_last_name() );
	$order->set_shipping_address_1( $house ? $street . ' ' . $house : $street );
	$order->set_shipping_city( $order->get_billing_city() );
	$order->set_shipping_postcode( $order->get_billing_postcode() );
	$order->set_shipping_country( $order->get_billing_country() );
	if ( method_exists( $order, 'set_shipping_phone' ) ) {
		$order->set_shipping_phone( $order->get_billing_phone() );
	}
}, 20 );
