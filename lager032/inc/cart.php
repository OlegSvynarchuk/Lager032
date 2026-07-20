<?php
/**
 * Cart helpers for the product list: report current cart state (to reflect on
 * the archive) and set a product's quantity (add / update / remove).
 * Read state is applied client-side so the archive HTML stays cacheable.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Current cart as { product_id: qty } + total item count. */
function lager_ajax_cart_state() {
	$items = array();
	$count = 0;
	if ( function_exists( 'WC' ) && WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $ci ) {
			$pid           = (string) $ci['product_id'];
			$items[ $pid ] = ( isset( $items[ $pid ] ) ? $items[ $pid ] : 0 ) + $ci['quantity'];
		}
		$count = WC()->cart->get_cart_contents_count();
	}
	wp_send_json( array( 'items' => (object) $items, 'count' => $count ) );
}
// wc-ajax (not admin-ajax) so WooCommerce loads the frontend cart + session.
add_action( 'wc_ajax_lager_cart_state', 'lager_ajax_cart_state' );

/** Set a product's quantity in the cart: add if absent, update if present, remove at 0. */
function lager_ajax_set_cart_qty() {
	check_ajax_referer( 'lager_search', 'nonce' );

	$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$qty = isset( $_POST['quantity'] ) ? max( 0, (int) $_POST['quantity'] ) : 1;
	if ( ! $pid || ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error();
	}

	$key = null;
	foreach ( WC()->cart->get_cart() as $k => $ci ) {
		if ( (int) $ci['product_id'] === $pid ) {
			$key = $k;
			break;
		}
	}
	if ( $key ) {
		WC()->cart->set_quantity( $key, $qty, true ); // qty 0 removes the line
	} elseif ( $qty > 0 ) {
		WC()->cart->add_to_cart( $pid, $qty );
	}

	$newqty = 0;
	foreach ( WC()->cart->get_cart() as $ci ) {
		if ( (int) $ci['product_id'] === $pid ) {
			$newqty += $ci['quantity'];
		}
	}

	wp_send_json( array(
		'qty'       => $newqty,
		'count'     => WC()->cart->get_cart_contents_count(),
		'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
	) );
}
add_action( 'wc_ajax_lager_set_cart_qty', 'lager_ajax_set_cart_qty' );

/**
 * Stock is never a hard limit: orders are processed manually and stock is refilled,
 * so a customer may order any quantity regardless of the DB stock (even at 0).
 * Treat every product as in-stock + allow backorders — no per-product data change,
 * applies to future products too. (Stock quantities stay in the DB for reference.)
 */
add_filter( 'woocommerce_product_is_in_stock', '__return_true' );
add_filter( 'woocommerce_product_backorders_allowed', '__return_true' );

/** Latin "RSD" currency symbol (site is Serbian Latin; WooCommerce default is Cyrillic "рсд"). */
add_filter( 'woocommerce_currency_symbol', function ( $symbol, $currency ) {
	return ( 'RSD' === $currency ) ? 'RSD' : $symbol;
}, 10, 2 );

/**
 * Mini-cart drawer body (items + subtotal + actions). Rendered server-side in the
 * footer and registered as a cart fragment so add/remove updates it live.
 */
function lager_minicart_body_html() {
	$cart     = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart : null;
	$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' );

	ob_start();
	echo '<div class="minicart__body">';

	if ( ! $cart || $cart->is_empty() ) {
		echo '<div class="minicart__empty"><p>' . esc_html__( 'Vaša korpa je prazna.', 'lager032' ) . '</p>';
		echo '<a class="btn btn--navy btn--block" href="' . esc_url( $shop_url ) . '">' . esc_html__( 'Nastavi kupovinu', 'lager032' ) . '</a></div>';
	} else {
		echo '<ul class="minicart__items">';
		foreach ( $cart->get_cart() as $ci ) {
			$product = isset( $ci['data'] ) ? $ci['data'] : null;
			if ( ! $product ) {
				continue;
			}
			$pid  = (int) $ci['product_id'];
			$qty  = (int) $ci['quantity'];
			$link = get_permalink( $pid );
			echo '<li class="minicart__item" data-id="' . esc_attr( $pid ) . '" data-qty="' . esc_attr( $qty ) . '">';
			$mc_img_id = function_exists( 'lager_product_category_image_id' ) ? lager_product_category_image_id( $pid ) : 0;
		$mc_img    = $mc_img_id
			? wp_get_attachment_image( $mc_img_id, 'woocommerce_thumbnail', false, array( 'alt' => $product->get_name() ) )
			: '<img src="' . esc_url( wc_placeholder_img_src( 'woocommerce_thumbnail' ) ) . '" alt="" loading="lazy">';
		echo '<a class="minicart__img" href="' . esc_url( $link ) . '">' . $mc_img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			// Brand + category, same as the product list: product_brand taxonomy if set, else a trailing
			// whitelisted brand token (stripped from the title); category uses subcategory precedence.
			$mc_btax = wp_get_post_terms( $pid, 'product_brand', array( 'fields' => 'names' ) );
			if ( $mc_btax && ! is_wp_error( $mc_btax ) ) {
				$mc_brand = $mc_btax[0];
				$mc_title = $product->get_name();
			} elseif ( function_exists( 'lager_extract_brand' ) ) {
				$mc_be    = lager_extract_brand( $product->get_name() );
				$mc_brand = $mc_be['brand'];
				$mc_title = $mc_be['title'];
			} else {
				$mc_brand = '';
				$mc_title = $product->get_name();
			}
			$mc_cat = function_exists( 'lager_product_primary_category_name' ) ? lager_product_primary_category_name( $pid ) : '';
			echo '<div class="minicart__info">';
			if ( $mc_brand || $mc_cat ) {
				echo '<div class="minicart__meta">';
				if ( $mc_brand ) {
					echo '<span class="minicart__brand">' . esc_html( $mc_brand ) . '</span>';
				}
				if ( $mc_cat ) {
					echo '<span class="minicart__cat">' . esc_html( $mc_cat ) . '</span>';
				}
				echo '</div>';
			}
			echo '<a class="minicart__name" href="' . esc_url( $link ) . '">' . esc_html( $mc_title ) . '</a>';
			echo '<span class="minicart__unit">' . wp_kses_post( wc_price( wc_get_price_to_display( $product ) ) ) . '</span>';
			echo '</div>'; // close .minicart__info so the image squares to the specs only
			echo '<div class="minicart__controls">';
			echo '<div class="qtybox qtybox--mini">';
			echo '<button type="button" class="qtybox__btn" data-dir="-1" aria-label="' . esc_attr__( 'Smanji', 'lager032' ) . '">&minus;</button>';
			echo '<span class="qtybox__val">' . esc_html( $qty ) . '</span>';
			echo '<button type="button" class="qtybox__btn" data-dir="1" aria-label="' . esc_attr__( 'Povećaj', 'lager032' ) . '">+</button>';
			echo '</div>';
			echo '<span class="minicart__price">' . wp_kses_post( $cart->get_product_subtotal( $product, $qty ) ) . '</span>';
			echo '</div>';
			echo '<button type="button" class="minicart__remove" aria-label="' . esc_attr__( 'Ukloni', 'lager032' ) . '">&times;</button>';
			echo '</li>';
		}
		echo '</ul>';
		echo '<div class="minicart__foot">';
		echo '<div class="minicart__subtotal"><span>' . esc_html__( 'Ukupno', 'lager032' ) . '</span><strong>' . wp_kses_post( $cart->get_cart_subtotal() ) . '</strong></div>';
		echo '<a class="btn btn--navy btn--block" href="' . esc_url( wc_get_cart_url() ) . '">' . esc_html__( 'Pogledaj korpu', 'lager032' ) . '</a>';
			echo '<button type="button" class="minicart__clear">' . esc_html__( 'Isprazni korpu', 'lager032' ) . '</button>';
		echo '</div>';
	}

	echo '</div>';
	return ob_get_clean();
}

/** Header cart-count badge markup (hidden when empty). Kept identical to header.php so the fragment swap is seamless. */
function lager_cart_count_html() {
	$count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
	return '<span class="cartbtn__count"' . ( $count ? '' : ' hidden' ) . '>' . esc_html( $count ) . '</span>';
}

add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	$fragments['div.minicart__body']  = lager_minicart_body_html();
	$fragments['span.cartbtn__count'] = lager_cart_count_html();
	return $fragments;
} );

/** Empty the whole cart (drawer "Isprazni korpu"). */
function lager_ajax_clear_cart() {
	check_ajax_referer( 'lager_search', 'nonce' );
	if ( function_exists( 'WC' ) && WC()->cart ) {
		WC()->cart->empty_cart();
	}
	wp_send_json( array(
		'count'     => ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0,
		'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
	) );
}
add_action( 'wc_ajax_lager_clear_cart', 'lager_ajax_clear_cart' );
