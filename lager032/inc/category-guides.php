<?php
/**
 * Category guide pages — a "Kategorije" custom post type. The content manager
 * creates one post per product category (hero image, subtitle, body via the
 * editor) and links it to a product_cat term. The homepage tiles, mega-menu and
 * footer route to the guide when one exists (else fall back to the shop archive).
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the `cat_guide` post type — URLs at /vodic/{slug}/.
 */
add_action( 'init', function () {
	register_post_type( 'cat_guide', array(
		'labels' => array(
			'name'               => 'Kategorije (stranice)',
			'singular_name'      => 'Kategorija (stranica)',
			'menu_name'          => 'Kategorije info',
			'add_new'            => 'Dodaj novu',
			'add_new_item'       => 'Dodaj stranicu kategorije',
			'edit_item'          => 'Uredi stranicu kategorije',
			'new_item'           => 'Nova stranica kategorije',
			'view_item'          => 'Prikaži stranicu',
			'search_items'       => 'Pretraži stranice',
			'not_found'          => 'Nema stranica',
			'not_found_in_trash' => 'Nema stranica u korpi',
		),
		'public'       => true,
		'has_archive'  => false,
		'menu_icon'    => 'dashicons-category',
		'menu_position' => 26,
		'supports'     => array( 'title', 'thumbnail' ),
		'rewrite'      => false, // root-level permalinks handled below (/{slug}/).
		'show_in_rest' => true,
	) );
} );

/**
 * Root-level guide permalinks: /{slug}/ instead of /vodic/{slug}/.
 */
add_filter( 'post_type_link', function ( $link, $post ) {
	if ( isset( $post->post_type ) && 'cat_guide' === $post->post_type ) {
		$link = home_url( user_trailingslashit( $post->post_name ) );
	}
	return $link;
}, 10, 2 );

/**
 * Resolve a bare top-level slug to a guide only when a published guide with that
 * exact slug exists. Pages and any other URLs are left completely untouched.
 */
add_filter( 'request', function ( $vars ) {
	if ( isset( $vars['post_type'] ) ) {
		return $vars;
	}
	// With /%postname%/ permalinks a bare slug arrives as `name` (post); pages use `pagename`.
	$slug = '';
	if ( ! empty( $vars['pagename'] ) ) {
		$slug = $vars['pagename'];
	} elseif ( ! empty( $vars['name'] ) ) {
		$slug = $vars['name'];
	}
	if ( '' === $slug ) {
		return $vars;
	}
	$slug  = sanitize_title( $slug );
	$guide = get_page_by_path( $slug, OBJECT, 'cat_guide' );
	if ( $guide && 'publish' === $guide->post_status ) {
		unset( $vars['pagename'] );
		$vars['post_type'] = 'cat_guide';
		$vars['name']      = $slug;
	}
	return $vars;
} );

/**
 * ACF fields for the guide: hero image, subtitle, related product category,
 * and the main body text (WYSIWYG). (Post title = page H1.)
 */
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	acf_add_local_field_group( array(
		'key'      => 'group_lager_catguide',
		'title'    => 'Stranica kategorije',
		'fields'   => array(
			array(
				'key'           => 'field_lcg_hero',
				'label'         => 'Hero slika',
				'name'          => 'hero_image',
				'type'          => 'image',
				'return_format' => 'id',
				'preview_size'  => 'medium',
				'library'       => 'all',
				'instructions'  => 'Velika slika u zaglavlju stranice (preporuka: široka, min. 1600px).',
			),
			array(
				'key'          => 'field_lcg_sub',
				'label'        => 'Podnaslov',
				'name'         => 'podnaslov',
				'type'         => 'text',
				'instructions' => 'Kratak opis ispod naslova u hero sekciji.',
			),
			array(
				'key'           => 'field_lcg_cat',
				'label'         => 'Povezana kategorija',
				'name'          => 'povezana_kategorija',
				'type'          => 'taxonomy',
				'taxonomy'      => 'product_cat',
				'field_type'    => 'select',
				'add_term'      => 0,
				'save_terms'    => 0,
				'load_terms'    => 0,
				'return_format' => 'id',
				'multiple'      => 0,
				'allow_null'    => 1,
				'instructions'  => 'Kategorija proizvoda na koju se stranica odnosi (za dugme „Pogledaj proizvode" i linkove).',
			),
			array(
				'key'          => 'field_lcg_body',
				'label'        => 'Tekst stranice',
				'name'         => 'sadrzaj',
				'type'         => 'wysiwyg',
				'tabs'         => 'all',
				'toolbar'      => 'full',
				'media_upload' => 1,
				'instructions' => 'Glavni tekst stranice — naslovi, pasusi, slike.',
			),
			array(
				'key'          => 'field_lcg_gal',
				'label'        => 'Slike',
				'name'         => '',
				'type'         => 'message',
				'message'      => 'Dodajte slike koje će se prikazati u galeriji na dnu stranice (popunite koliko Vam treba).',
			),
			array( 'key' => 'field_lcg_img1', 'label' => 'Slika 1', 'name' => 'slika_1', 'type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all', 'wrapper' => array( 'width' => '33' ) ),
			array( 'key' => 'field_lcg_img2', 'label' => 'Slika 2', 'name' => 'slika_2', 'type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all', 'wrapper' => array( 'width' => '33' ) ),
			array( 'key' => 'field_lcg_img3', 'label' => 'Slika 3', 'name' => 'slika_3', 'type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all', 'wrapper' => array( 'width' => '33' ) ),
		),
		'location' => array(
			array(
				array( 'param' => 'post_type', 'operator' => '==', 'value' => 'cat_guide' ),
			),
		),
		'position' => 'acf_after_title',
	) );
} );

/**
 * Map of product_cat term_id => guide permalink (memoized per request).
 *
 * @return array<int,string>
 */
function lager_category_guide_map() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map = array();
	$q   = new WP_Query( array(
		'post_type'      => 'cat_guide',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'fields'         => 'ids',
	) );
	foreach ( $q->posts as $pid ) {
		$tid = function_exists( 'get_field' ) ? get_field( 'povezana_kategorija', $pid ) : 0;
		if ( is_array( $tid ) ) {
			$tid = reset( $tid );
		}
		$tid = (int) $tid;
		if ( $tid && ! isset( $map[ $tid ] ) ) {
			$map[ $tid ] = get_permalink( $pid );
		}
	}
	return $map;
}

/**
 * Guide URL for a product category term, or '' when none exists.
 *
 * @param int $term_id product_cat term id.
 * @return string
 */
function lager_category_guide_url( $term_id ) {
	$map = lager_category_guide_map();
	return isset( $map[ (int) $term_id ] ) ? $map[ (int) $term_id ] : '';
}
