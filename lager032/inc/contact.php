<?php
/**
 * Homepage contact form handler. Posts to admin-post.php (action: lager_contact),
 * emails the site admin, redirects back with a status flag.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lager032_handle_contact() {
	$referer = wp_get_referer() ? wp_get_referer() : home_url( '/' );

	if ( ! isset( $_POST['lager_contact_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['lager_contact_nonce'] ), 'lager_contact' ) ) {
		wp_safe_redirect( add_query_arg( 'kontakt', 'greska', $referer ) . '#kontakt' );
		exit;
	}

	$name    = isset( $_POST['ime'] ) ? sanitize_text_field( wp_unslash( $_POST['ime'] ) ) : '';
	$phone   = isset( $_POST['telefon'] ) ? sanitize_text_field( wp_unslash( $_POST['telefon'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$message = isset( $_POST['poruka'] ) ? sanitize_textarea_field( wp_unslash( $_POST['poruka'] ) ) : '';

	if ( '' === $name || ! is_email( $email ) || '' === $message ) {
		wp_safe_redirect( add_query_arg( 'kontakt', 'greska', $referer ) . '#kontakt' );
		exit;
	}

	$to      = get_option( 'admin_email' );
	$subject = sprintf( '[%s] Upit sa sajta — %s', get_bloginfo( 'name' ), $name );
	$body    = "Ime i prezime: {$name}\nTelefon: {$phone}\nEmail: {$email}\n\nPoruka:\n{$message}\n";
	$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );

	$sent = wp_mail( $to, $subject, $body, $headers );

	wp_safe_redirect( add_query_arg( 'kontakt', $sent ? 'ok' : 'greska', $referer ) . '#kontakt' );
	exit;
}
add_action( 'admin_post_lager_contact', 'lager032_handle_contact' );
add_action( 'admin_post_nopriv_lager_contact', 'lager032_handle_contact' );
