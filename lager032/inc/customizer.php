<?php
/**
 * Customizer — admin-managed site images (logo, heroes, brand logos, about
 * photo, certificate badge). Editable at Izgled → Prilagodi → "Slike sajta".
 * Each falls back to the bundled theme image when not set.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site image URL: the admin-set image if present, else the fallback.
 *
 * @param string $key      Theme-mod key.
 * @param string $fallback Fallback URL.
 * @return string
 */
function lager_site_image( $key, $fallback = '' ) {
	$val = get_theme_mod( $key, '' );
	return $val ? $val : $fallback;
}

/** Convenience: theme asset URL with mtime cache-bust. */
function lager_theme_img( $rel ) {
	$path = get_template_directory() . $rel;
	$uri  = get_template_directory_uri() . $rel;
	return file_exists( $path ) ? $uri . '?v=' . filemtime( $path ) : $uri;
}

/** Number of brand-logo slots in the carousel. */
if ( ! defined( 'LAGER_BRAND_SLOTS' ) ) {
	define( 'LAGER_BRAND_SLOTS', 16 );
}

add_action( 'customize_register', function ( $wp_customize ) {
	$reg = function ( $key, $label, $section ) use ( $wp_customize ) {
		$wp_customize->add_setting( $key, array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $key, array(
			'label'   => $label,
			'section' => $section,
		) ) );
	};

	// Site images.
	$wp_customize->add_section( 'lager_images', array( 'title' => 'Slike sajta', 'priority' => 30 ) );
	$reg( 'lager_logo', 'Logo (zaglavlje / podnožje)', 'lager_images' );
	$reg( 'lager_hero_home', 'Hero slika — Početna', 'lager_images' );
	$reg( 'lager_hero_onama', 'Hero slika — O nama', 'lager_images' );
	$reg( 'lager_about_img', 'O nama — slika', 'lager_images' );
	$reg( 'lager_cert', 'Sertifikat bedž (podnožje)', 'lager_images' );

	// Brand logos (carousel).
	$wp_customize->add_section( 'lager_brands', array(
		'title'       => 'Brend logoi',
		'priority'    => 31,
		'description' => 'Logoi u karuselu brendova na početnoj strani. Ostavite prazno da uklonite slot.',
	) );
	for ( $i = 1; $i <= LAGER_BRAND_SLOTS; $i++ ) {
		$reg( 'lager_brand_' . $i, 'Brend logo ' . $i, 'lager_brands' );
	}
} );
