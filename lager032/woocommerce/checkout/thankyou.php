<?php
/**
 * Order-received / thank-you page (theme override) — Serbian Latin.
 * Everything the buyer needs: confirmation, payment instructions (bank transfer / COD),
 * items with image (falls back to the catalog placeholder), totals and delivery details.
 *
 * @package Lager032
 */

defined( 'ABSPATH' ) || exit;

if ( ! $order ) :
	?>
	<section class="thankyou"><div class="container thankyou__inner">
		<h1 class="thankyou__title"><?php esc_html_e( 'Hvala na porudžbini!', 'lager032' ); ?></h1>
		<p class="thankyou__lead"><?php esc_html_e( 'Vaša porudžbina je primljena.', 'lager032' ); ?></p>
	</div></section>
	<?php
	return;
endif;

$method = $order->get_payment_method();
$ph_id  = (int) get_option( 'lager_cat_placeholder_id' );
?>
<section class="thankyou">
	<div class="container thankyou__inner">

		<div class="thankyou__head">
			<span class="thankyou__check"><?php lager032_icon( 'check' ); ?></span>
			<h1 class="thankyou__title"><?php esc_html_e( 'Hvala na porudžbini!', 'lager032' ); ?></h1>
			<p class="thankyou__lead">
				<?php
				/* translators: %s: order number. */
				printf( esc_html__( 'Vaša porudžbina %s je uspešno primljena.', 'lager032' ), '<strong>#' . esc_html( $order->get_order_number() ) . '</strong>' );
				?>
			</p>
		</div>

		<ul class="thankyou__meta">
			<li><span><?php esc_html_e( 'Broj porudžbine', 'lager032' ); ?></span><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></li>
			<li><span><?php esc_html_e( 'Datum', 'lager032' ); ?></span><strong><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></strong></li>
			<li><span><?php esc_html_e( 'E-mail', 'lager032' ); ?></span><strong><?php echo esc_html( $order->get_billing_email() ); ?></strong></li>
			<li><span><?php esc_html_e( 'Plaćanje', 'lager032' ); ?></span><strong><?php echo esc_html( $order->get_payment_method_title() ); ?></strong></li>
		</ul>

		<div class="thankyou__pay">
			<?php if ( 'bacs' === $method ) : ?>
				<h2><?php esc_html_e( 'Uputstvo za plaćanje — uplatnica / bankovni transfer', 'lager032' ); ?></h2>
				<?php
				$accounts = (array) get_option( 'woocommerce_bacs_accounts' );
				$acc      = $accounts ? (array) reset( $accounts ) : array();
				?>
				<ul class="thankyou__bank">
					<li><span><?php esc_html_e( 'Primalac', 'lager032' ); ?></span><strong><?php echo esc_html( ! empty( $acc['account_name'] ) ? $acc['account_name'] : 'LAGER STR, Čačak' ); ?></strong></li>
					<li><span><?php esc_html_e( 'Banka', 'lager032' ); ?></span><strong><?php echo esc_html( ! empty( $acc['bank_name'] ) ? $acc['bank_name'] : '—' ); ?></strong></li>
					<li><span><?php esc_html_e( 'Broj računa', 'lager032' ); ?></span><strong><?php echo esc_html( ! empty( $acc['account_number'] ) ? $acc['account_number'] : '000-0000000000000-00' ); ?></strong></li>
					<li><span><?php esc_html_e( 'Iznos', 'lager032' ); ?></span><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></li>
					<li><span><?php esc_html_e( 'Poziv na broj', 'lager032' ); ?></span><strong><?php echo esc_html( $order->get_order_number() ); ?></strong></li>
				</ul>
				<p class="thankyou__paynote"><?php esc_html_e( 'Kao poziv na broj unesite broj porudžbine. Porudžbinu pripremamo za isporuku po evidentiranoj uplati.', 'lager032' ); ?></p>
			<?php elseif ( 'cod' === $method ) : ?>
				<h2><?php esc_html_e( 'Plaćanje pouzećem', 'lager032' ); ?></h2>
				<p class="thankyou__paynote"><?php esc_html_e( 'Porudžbinu plaćate kuriru prilikom preuzimanja.', 'lager032' ); ?></p>
			<?php else : ?>
				<p class="thankyou__paynote"><?php echo esc_html( $order->get_payment_method_title() ); ?></p>
			<?php endif; ?>
		</div>

		<div class="thankyou__items">
			<h2><?php esc_html_e( 'Vaša porudžbina', 'lager032' ); ?></h2>
			<table class="lager-order-table">
				<thead>
					<tr>
						<th class="ty-prod"><?php esc_html_e( 'Proizvod', 'lager032' ); ?></th>
						<th><?php esc_html_e( 'Šifra', 'lager032' ); ?></th>
						<th><?php esc_html_e( 'Količina', 'lager032' ); ?></th>
						<th><?php esc_html_e( 'Cena', 'lager032' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $order->get_items() as $item ) {
						$product = $item->get_product();
						$img_id  = $product ? $product->get_image_id() : 0;
						if ( ! $img_id ) {
							$img_id = $ph_id;
						}
						$img = $img_id
							? wp_get_attachment_image( $img_id, 'thumbnail', false, array( 'class' => 'thankyou__img' ) )
							: '<img class="thankyou__img" src="' . esc_url( wc_placeholder_img_src( 'thumbnail' ) ) . '" alt="">';
						$sku  = $product ? $product->get_sku() : '';
						$cats = $product ? get_the_terms( $product->get_id(), 'product_cat' ) : false;
						$cat  = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '';
						?>
						<tr>
							<td class="ty-prod"><?php echo wp_kses_post( $img ); ?><span class="ty-prodinfo"><span class="ty-name"><?php echo esc_html( $item->get_name() ); ?></span><?php if ( $cat ) : ?><span class="ty-cat"><?php echo esc_html( $cat ); ?></span><?php endif; ?></span></td>
							<td data-label="<?php esc_attr_e( 'Šifra', 'lager032' ); ?>"><?php echo esc_html( $sku ? $sku : '—' ); ?></td>
							<td data-label="<?php esc_attr_e( 'Količina', 'lager032' ); ?>"><?php echo esc_html( $item->get_quantity() ); ?></td>
							<td data-label="<?php esc_attr_e( 'Cena', 'lager032' ); ?>"><?php echo wp_kses_post( wc_price( $order->get_line_total( $item, true ) ) ); ?></td>
						</tr>
						<?php
					}
					?>
				</tbody>
				<tfoot>
					<tr><td colspan="4">
						<div class="lager-order-summary">
							<div class="los-row"><span><?php esc_html_e( 'Osnovica', 'lager032' ); ?></span><span><?php echo wp_kses_post( wc_price( $order->get_subtotal() ) ); ?></span></div>
							<div class="los-row"><span><?php esc_html_e( 'PDV (20%)', 'lager032' ); ?></span><span><?php echo wp_kses_post( wc_price( $order->get_total_tax() ) ); ?></span></div>
							<div class="los-row los-total"><span><?php esc_html_e( 'Ukupno', 'lager032' ); ?></span><span><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span></div>
						</div>
					</td></tr>
				</tfoot>
			</table>
		</div>

		<div class="thankyou__details">
			<div class="thankyou__card">
				<h3><?php esc_html_e( 'Kupac', 'lager032' ); ?></h3>
				<p>
					<?php echo esc_html( trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ); ?><br>
					<?php echo esc_html( $order->get_billing_email() ); ?><br>
					<?php echo esc_html( $order->get_billing_phone() ); ?>
					<?php $p2 = $order->get_meta( '_billing_phone2' ); echo $p2 ? '<br>' . esc_html( $p2 ) : ''; ?>
				</p>
			</div>
			<div class="thankyou__card">
				<h3><?php esc_html_e( 'Adresa za dostavu', 'lager032' ); ?></h3>
				<p>
					<?php
					echo esc_html( trim( $order->get_billing_address_1() . ' ' . $order->get_meta( '_billing_house_no' ) ) );
					$bits = array();
					$f    = $order->get_meta( '_billing_floor' );
					$a    = $order->get_meta( '_billing_apartment' );
					$i    = $order->get_meta( '_billing_intercom' );
					if ( $f ) {
						$bits[] = esc_html__( 'Sprat', 'lager032' ) . ' ' . $f;
					}
					if ( $a ) {
						$bits[] = esc_html__( 'Stan', 'lager032' ) . ' ' . $a;
					}
					if ( $i ) {
						$bits[] = esc_html__( 'Interfon', 'lager032' ) . ' ' . $i;
					}
					echo $bits ? '<br>' . esc_html( implode( ' · ', $bits ) ) : '';
					echo '<br>' . esc_html( $order->get_billing_city() );
					$dn = $order->get_meta( '_billing_delivery_note' );
					echo $dn ? '<br><em>' . esc_html( $dn ) . '</em>' : '';
					?>
				</p>
			</div>
		</div>

		<div class="thankyou__actions">
			<a class="btn btn--navy" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Nastavi kupovinu', 'lager032' ); ?></a>
		</div>

	</div>
</section>
