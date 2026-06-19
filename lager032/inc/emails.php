<?php
/**
 * Order e-mails — Serbian sender, subjects and headings for the en_US storefront.
 * (Body text + branding handled separately via template overrides / WC email settings.)
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---- Sender ---- */
add_filter( 'woocommerce_email_from_name', function () {
	return 'LAGER STR';
} );
add_filter( 'woocommerce_email_from_address', function () {
	return 'lager032@gmail.com';
} );

/**
 * Order number from the email object (order for order e-mails).
 */
function lager_email_order_no( $object ) {
	return ( $object && is_a( $object, 'WC_Order' ) ) ? $object->get_order_number() : '';
}

/* ---- Subjects (Serbian, with order number) ---- */
$lager_email_subjects = array(
	'new_order'                 => 'Nova porudžbina #%s — LAGER STR',
	'cancelled_order'           => 'Otkazana porudžbina #%s',
	'failed_order'              => 'Neuspešna porudžbina #%s',
	'customer_on_hold_order'    => 'Primili smo Vašu porudžbinu #%s',
	'customer_processing_order' => 'Primili smo Vašu porudžbinu #%s',
	'customer_completed_order'  => 'Vaša porudžbina #%s je isporučena',
	'customer_refunded_order'   => 'Povraćaj za porudžbinu #%s',
);
foreach ( $lager_email_subjects as $lager_eid => $lager_tpl ) {
	add_filter(
		"woocommerce_email_subject_{$lager_eid}",
		function ( $subject, $object ) use ( $lager_tpl ) {
			return sprintf( $lager_tpl, lager_email_order_no( $object ) );
		},
		10,
		2
	);
}

/* ---- Headings (Serbian) ---- */
$lager_email_headings = array(
	'new_order'                 => 'Nova porudžbina',
	'cancelled_order'           => 'Otkazana porudžbina',
	'failed_order'              => 'Neuspešna porudžbina',
	'customer_on_hold_order'    => 'Hvala na porudžbini',
	'customer_processing_order' => 'Hvala na porudžbini',
	'customer_completed_order'  => 'Vaša porudžbina je isporučena',
);
foreach ( $lager_email_headings as $lager_eid => $lager_h ) {
	add_filter(
		"woocommerce_email_heading_{$lager_eid}",
		function () use ( $lager_h ) {
			return $lager_h;
		}
	);
}

/* ---- Serbian footer line (additional content) on customer e-mails ---- */
$lager_email_footer = 'Hvala na poverenju! Za sva pitanja u vezi sa porudžbinom kontaktirajte nas na lager032@gmail.com ili telefonom +381 32 342 281.';
foreach ( array( 'customer_on_hold_order', 'customer_processing_order', 'customer_completed_order' ) as $lager_eid ) {
	add_filter(
		"woocommerce_email_additional_content_{$lager_eid}",
		function () use ( $lager_email_footer ) {
			return $lager_email_footer;
		}
	);
}

/* ---- Serbian body strings — only while an order e-mail is rendering, so the
   en_US admin/storefront are untouched. (WC 10.8 strings; falls through to EN on a miss.) ---- */
add_action( 'woocommerce_email_header', function () { $GLOBALS['lager_in_email'] = true; }, 1 );
add_action( 'woocommerce_email_footer', function () { $GLOBALS['lager_in_email'] = false; }, 99 );

add_filter( 'gettext', function ( $translated, $text, $domain ) {
	if ( empty( $GLOBALS['lager_in_email'] ) || 'woocommerce' !== $domain ) {
		return $translated;
	}
	static $map = array(
		'Hi %s,'           => 'Poštovani %s,',
		'Hi,'              => 'Poštovani,',
		'We’ve received your order and it’s currently on hold until we can confirm your payment has been processed.' => 'Primili smo Vašu porudžbinu. Trenutno je na čekanju dok ne potvrdimo da je uplata evidentirana.',
		'Here’s a reminder of what you’ve ordered:' => 'Pregled Vaše porudžbine:',
		'Just to let you know &mdash; we’ve received your order, and it is now being processed.' => 'Obaveštavamo Vas da smo primili Vašu porudžbinu i da je u obradi.',
		'You’ve received the following order from %s:' => 'Primili ste sledeću porudžbinu od %s:',
		'You’ve received a new order from %s:' => 'Primili ste novu porudžbinu od %s:',
		'Order summary'    => 'Pregled porudžbine',
		'Billing address'  => 'Podaci kupca',
		'Shipping address' => 'Adresa za dostavu',
		'Product'          => 'Naziv',
		'Quantity'         => 'Količina',
		'Price'            => 'Cena',
		'Subtotal'         => 'Osnovica',
		'Subtotal:'        => 'Osnovica:',
		'Total'            => 'Ukupno',
		'Total:'           => 'Ukupno:',
		'Payment method'   => 'Način plaćanja',
		'Payment method:'  => 'Način plaćanja:',
		'Our bank details' => 'Podaci za uplatu',
		'Account number'   => 'Broj računa',
		'Account name'     => 'Naziv računa',
		'Serbia'           => 'Srbija',
	);
	return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
}, 20, 3 );

// Country name in the e-mail address block (built from a list cached at init, so the gettext
// map above misses it) — swap Serbia -> Srbija only while rendering an e-mail.
add_filter( 'woocommerce_formatted_address_replacements', function ( $replacements ) {
	if ( ! empty( $GLOBALS['lager_in_email'] ) && isset( $replacements['{country}'] ) && 'Serbia' === $replacements['{country}'] ) {
		$replacements['{country}'] = 'Srbija';
	}
	return $replacements;
} );
