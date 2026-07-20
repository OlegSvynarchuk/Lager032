<?php
/**
 * Order review (theme override) — full cart table with editable quantity, plus a
 * net / PDV / total summary and the "buyer pays shipping" note. Re-rendered by
 * WooCommerce on every `update_checkout`.
 *
 * @package Lager032
 */

defined( 'ABSPATH' ) || exit;

$cart = WC()->cart;
?>
<table class="shop_table woocommerce-checkout-review-order-table lager-order-table">
	<thead>
		<tr>
			<th class="lo-img" aria-hidden="true"></th>
			<th class="lo-kat"><?php esc_html_e( 'Kategorija', 'lager032' ); ?></th>
			<th class="lo-naziv"><?php esc_html_e( 'Naziv', 'lager032' ); ?></th>
			<th class="lo-cena"><?php esc_html_e( 'Cena', 'lager032' ); ?></th>
			<th class="lo-kol"><?php esc_html_e( 'Količina', 'lager032' ); ?></th>
			<th class="lo-x"><span class="screen-reader-text"><?php esc_html_e( 'Ukloni', 'lager032' ); ?></span></th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) {
				continue;
			}
			$pid  = $cart_item['product_id'];
			$cats = get_the_terms( $pid, 'product_cat' );
			$cat  = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '—';
			$link = $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '';
			$name = $_product->get_name();
			?>
			<tr class="cart_item" data-id="<?php echo esc_attr( $pid ); ?>" data-qty="<?php echo esc_attr( $cart_item['quantity'] ); ?>">
				<td class="lo-img">
					<?php
					// Illustrative image: product's subcategory/category image (same logic as the shop list).
					$lo_img_id = function_exists( 'lager_product_category_image_id' ) ? lager_product_category_image_id( $pid ) : 0;
					$lo_img    = $lo_img_id
						? wp_get_attachment_image( $lo_img_id, 'woocommerce_thumbnail', false, array( 'alt' => $name ) )
						: '<img src="' . esc_url( wc_placeholder_img_src( 'woocommerce_thumbnail' ) ) . '" alt="" loading="lazy">';
					echo $link
						? '<a class="lo-thumb" href="' . esc_url( $link ) . '">' . $lo_img . '</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						: '<span class="lo-thumb">' . $lo_img . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</td>
				<td class="lo-kat" data-label="<?php esc_attr_e( 'Kategorija', 'lager032' ); ?>"><?php echo esc_html( $cat ); ?></td>
				<td class="lo-naziv" data-label="<?php esc_attr_e( 'Naziv', 'lager032' ); ?>">
					<?php echo $link ? '<a class="lo-name" href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>' : '<span class="lo-name">' . esc_html( $name ) . '</span>'; ?>
				</td>
				<td class="lo-cena" data-label="<?php esc_attr_e( 'Cena', 'lager032' ); ?>"><?php echo wp_kses_post( wc_price( wc_get_price_to_display( $_product ) ) ); ?></td>
				<td class="lo-kol" data-label="<?php esc_attr_e( 'Količina', 'lager032' ); ?>">
					<div class="qtybox qtybox--mini lo-qty">
						<button type="button" class="qtybox__btn" data-dir="-1" aria-label="<?php esc_attr_e( 'Smanji', 'lager032' ); ?>">&minus;</button>
						<span class="qtybox__val"><?php echo esc_html( $cart_item['quantity'] ); ?></span>
						<button type="button" class="qtybox__btn" data-dir="1" aria-label="<?php esc_attr_e( 'Povećaj', 'lager032' ); ?>">+</button>
					</div>
				</td>
				<td class="lo-x"><button type="button" class="lo-remove" data-id="<?php echo esc_attr( $pid ); ?>" aria-label="<?php esc_attr_e( 'Ukloni proizvod', 'lager032' ); ?>">&times;</button></td>
			</tr>
			<?php
		}
		?>
	</tbody>
	<tfoot>
		<tr class="lo-summary-row"><td class="lo-summary-cell" colspan="6">
<div class="lager-order-summary">
	<?php
	$osnovica = $cart->get_subtotal();   // net (ex PDV)
	$pdv      = $cart->get_total_tax();  // PDV
	?>
	<div class="los-row"><span><?php esc_html_e( 'Osnovica', 'lager032' ); ?></span><span><?php echo wp_kses_post( wc_price( $osnovica ) ); ?></span></div>
	<div class="los-row"><span><?php esc_html_e( 'PDV (20%)', 'lager032' ); ?></span><span><?php echo wp_kses_post( wc_price( $pdv ) ); ?></span></div>
	<div class="los-row los-total"><span><?php esc_html_e( 'Ukupno za naplatu', 'lager032' ); ?></span><span><?php echo wp_kses_post( $cart->get_total() ); ?></span></div>
	<p class="los-note"><?php esc_html_e( 'Troškove dostave plaća kupac.', 'lager032' ); ?></p>
</div>
		</td></tr>
	</tfoot>
</table>
