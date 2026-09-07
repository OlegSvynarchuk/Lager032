<?php
/**
 * Contact form handler (homepage + /kontakt/). Posts to admin-post.php
 * (action: lager_contact), emails the recipient, redirects back with a status flag.
 *
 * Anti-spam is deliberately silent and JS-free: a honeypot field plus a signed
 * render-time stamp. Both catch the overwhelming majority of drive-by bots without
 * putting a CAPTCHA in front of a customer trying to ask about a bearing.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Successful messages allowed per IP per window. */
if ( ! defined( 'LAGER_CONTACT_MAX' ) ) {
	define( 'LAGER_CONTACT_MAX', 5 );
}
/** Throttle window. */
if ( ! defined( 'LAGER_CONTACT_WINDOW' ) ) {
	define( 'LAGER_CONTACT_WINDOW', HOUR_IN_SECONDS );
}
/** A human needs at least this long to fill the form in. */
if ( ! defined( 'LAGER_CONTACT_MIN_SECONDS' ) ) {
	define( 'LAGER_CONTACT_MIN_SECONDS', 3 );
}

/**
 * The visitor's IP address.
 *
 * REMOTE_ADDR is the direct peer, which is correct on a normal host. Behind a CDN or
 * load balancer (CloudFront, Cloudflare, ALB) it becomes the proxy, so every visitor
 * would share one throttle bucket and the form would lock site-wide after a few messages.
 *
 * Reading a forwarded header is NOT safe by default: anyone can send X-Forwarded-For and
 * hand themselves a fresh bucket on every request. So it is opt-in — define the header in
 * wp-config.php *only* on infrastructure where a trusted proxy overwrites it:
 *
 *     define( 'LAGER_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' );   // Cloudflare
 *     define( 'LAGER_PROXY_HEADER', 'HTTP_X_FORWARDED_FOR' );    // CloudFront / ALB
 *     define( 'LAGER_PROXY_DEPTH', 1 );                          // proxies to skip, from the right
 *
 * With X-Forwarded-For the list is "client, proxy1, proxy2"; entries a client sends itself
 * end up on the LEFT, so we count from the right — one hop per proxy in front of the origin.
 *
 * @return string IP address, or 'unknown'.
 */
function lager_client_ip() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip     = $remote;

	if ( defined( 'LAGER_PROXY_HEADER' ) && ! empty( $_SERVER[ LAGER_PROXY_HEADER ] ) ) {
		$parts = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ LAGER_PROXY_HEADER ] ) ) ) );
		$depth = defined( 'LAGER_PROXY_DEPTH' ) ? max( 1, (int) LAGER_PROXY_DEPTH ) : 1;
		$idx   = count( $parts ) - $depth;
		if ( isset( $parts[ $idx ] ) && filter_var( $parts[ $idx ], FILTER_VALIDATE_IP ) ) {
			$ip = $parts[ $idx ];
		}
	}

	/**
	 * Filter the resolved client IP (e.g. for a proxy setup this helper doesn't cover).
	 *
	 * @param string $ip     Resolved address.
	 * @param string $remote The raw REMOTE_ADDR.
	 */
	$ip = apply_filters( 'lager_client_ip', $ip, $remote );

	return ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) ? $ip : 'unknown';
}

/**
 * Throttle key for the current visitor.
 *
 * @return string Transient key.
 */
function lager_contact_throttle_key() {
	return 'lager_contact_' . md5( lager_client_ip() );
}

/**
 * Hidden fields every contact form must render: nonce, honeypot, signed timestamp.
 * Echoed from the templates so the markup can't drift between the two forms.
 */
function lager_contact_fields() {
	wp_nonce_field( 'lager_contact', 'lager_contact_nonce' );
	$ts = time();
	printf(
		'<input type="hidden" name="lager_ts" value="%1$s"><input type="hidden" name="lager_tsk" value="%2$s">',
		esc_attr( $ts ),
		esc_attr( wp_hash( 'lager_contact_' . $ts ) )
	);
	// Honeypot: hidden from people, irresistible to bots. Not `display:none` — some bots
	// skip those; off-screen with aria-hidden + tabindex -1 keeps it out of the tab order.
	echo '<div class="cform__hp" aria-hidden="true">'
		. '<label>Web adresa (ne popunjavajte)'
		. '<input type="text" name="lager_website" value="" tabindex="-1" autocomplete="off">'
		. '</label></div>';
}

/**
 * Render the status notice after a redirect back from the handler.
 * Each outcome says something actionable — a bad e-mail and a dead mail server
 * are different problems for the person standing in front of the form.
 */
function lager_contact_notice() {
	$status = isset( $_GET['kontakt'] ) ? sanitize_key( $_GET['kontakt'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
	if ( '' === $status ) {
		return;
	}

	$messages = array(
		'ok'     => array( 'ok', __( 'Hvala! Vaša poruka je poslata. Javićemo se uskoro.', 'lager032' ) ),
		'greska' => array( 'err', __( 'Proverite unete podatke — ime, ispravna e-mail adresa i poruka su obavezni.', 'lager032' ) ),
		'limit'  => array( 'err', __( 'Primili smo više poruka sa ove adrese. Pokušajte kasnije ili nas pozovite telefonom.', 'lager032' ) ),
		'slanje' => array( 'err', __( 'Poruka trenutno ne može biti poslata. Pokušajte ponovo kasnije ili nas pozovite telefonom.', 'lager032' ) ),
	);

	if ( ! isset( $messages[ $status ] ) ) {
		return;
	}
	printf(
		'<p class="formmsg formmsg--%1$s">%2$s</p>',
		esc_attr( $messages[ $status ][0] ),
		esc_html( $messages[ $status ][1] )
	);
}

/**
 * Redirect back to the form with a status flag.
 *
 * @param string $status  One of ok|greska|limit|slanje.
 * @param string $referer Where to go back to.
 */
function lager_contact_redirect( $status, $referer ) {
	wp_safe_redirect( add_query_arg( 'kontakt', $status, $referer ) . '#kontakt' );
	exit;
}

/**
 * Handle the submission.
 */
function lager032_handle_contact() {
	$referer = wp_get_referer() ? wp_get_referer() : home_url( '/' );

	if ( ! isset( $_POST['lager_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lager_contact_nonce'] ) ), 'lager_contact' ) ) {
		lager_contact_redirect( 'greska', $referer );
	}

	// --- Bot traps. Both answer "ok" so a bot learns nothing and stops retrying. ---
	if ( ! empty( $_POST['lager_website'] ) ) {
		lager_contact_redirect( 'ok', $referer );
	}
	$ts  = isset( $_POST['lager_ts'] ) ? absint( $_POST['lager_ts'] ) : 0;
	$tsk = isset( $_POST['lager_tsk'] ) ? sanitize_text_field( wp_unslash( $_POST['lager_tsk'] ) ) : '';
	if ( ! $ts || ! hash_equals( wp_hash( 'lager_contact_' . $ts ), $tsk ) || ( time() - $ts ) < LAGER_CONTACT_MIN_SECONDS ) {
		lager_contact_redirect( 'ok', $referer );
	}

	// --- Throttle. Checked here, but only *successful* sends are counted below, so a
	// typo or a mail-server outage never eats someone's allowance. ---
	$key  = lager_contact_throttle_key();
	$sent_count = (int) get_transient( $key );
	if ( $sent_count >= LAGER_CONTACT_MAX ) {
		lager_contact_redirect( 'limit', $referer );
	}

	$name    = isset( $_POST['ime'] ) ? sanitize_text_field( wp_unslash( $_POST['ime'] ) ) : '';
	$phone   = isset( $_POST['telefon'] ) ? sanitize_text_field( wp_unslash( $_POST['telefon'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$message = isset( $_POST['poruka'] ) ? sanitize_textarea_field( wp_unslash( $_POST['poruka'] ) ) : '';

	// --- Validation. Length caps stop a bot posting a megabyte through the mail server. ---
	$name    = trim( $name );
	$message = trim( $message );
	$valid   = '' !== $name
		&& mb_strlen( $name ) <= 100
		&& is_email( $email )
		&& mb_strlen( $email ) <= 200
		&& mb_strlen( $phone ) <= 40
		&& mb_strlen( $message ) >= 5
		&& mb_strlen( $message ) <= 5000;

	if ( ! $valid ) {
		lager_contact_redirect( 'greska', $referer );
	}

	$to      = apply_filters( 'lager_contact_recipient', get_option( 'admin_email' ) );
	$subject = sprintf( '[%s] Upit sa sajta — %s', get_bloginfo( 'name' ), $name );
	$body    = "Ime i prezime: {$name}\nTelefon: {$phone}\nEmail: {$email}\n\nPoruka:\n{$message}\n";
	// Reply-To carries the visitor's address; sanitize_* already stripped any newline,
	// so no header can be injected through the name.
	$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );

	$sent = wp_mail( $to, $subject, $body, $headers );

	if ( ! $sent ) {
		// Mail failed — the visitor did nothing wrong, so this does NOT count against them.
		lager_contact_redirect( 'slanje', $referer );
	}

	set_transient( $key, $sent_count + 1, LAGER_CONTACT_WINDOW );
	lager_contact_redirect( 'ok', $referer );
}
add_action( 'admin_post_lager_contact', 'lager032_handle_contact' );
add_action( 'admin_post_nopriv_lager_contact', 'lager032_handle_contact' );
