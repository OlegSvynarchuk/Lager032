<?php
/**
 * Inline SVG icons (no icon-font dependency).
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return an inline SVG icon by name.
 *
 * @param string $name Icon key.
 * @return string SVG markup (already safe — static, no user data).
 */
function lager032_get_icon( $name ) {
	$icons = array(
		'phone'  => '<path d="M6.6 10.8a13.4 13.4 0 0 0 6.6 6.6l2.2-2.2a.9.9 0 0 1 .95-.22 10.6 10.6 0 0 0 3.3.53.9.9 0 0 1 .9.9V20a.9.9 0 0 1-.9.9A16.2 16.2 0 0 1 3.2 4.6a.9.9 0 0 1 .9-.9h3.5a.9.9 0 0 1 .9.9c0 1.14.18 2.25.53 3.3a.9.9 0 0 1-.22.95z"/>',
		'mail'   => '<path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm9 7L4 6.5V7l8 5 8-5v-.5z"/>',
		'pin'    => '<path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/>',
		'search' => '<path d="M15.5 14h-.8l-.3-.3a6.5 6.5 0 1 0-.7.7l.3.3v.8l5 5 1.5-1.5-5-5zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z"/>',
		'cart'   => '<path d="M7 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm10 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7.2 14h9.45a1 1 0 0 0 .96-.73L20 6H6.2l-.6-3H2v2h2l2.6 11.6A2 2 0 0 0 8.55 18H19v-2H8.42l.18-.8z"/>',
	);

	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="icon icon--%1$s" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">%2$s</svg>',
		esc_attr( $name ),
		$icons[ $name ]
	);
}

/**
 * Echo an inline SVG icon.
 *
 * @param string $name Icon key.
 */
function lager032_icon( $name ) {
	echo lager032_get_icon( $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
}
