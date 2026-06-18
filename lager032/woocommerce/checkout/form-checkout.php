<?php
/**
 * Checkout form (theme override) — one page: customer + delivery details on top,
 * cart table + net/PDV/total summary + payment below. Shipping is not collected
 * (buyer pays the courier).
 *
 * @package Lager032
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_checkout_form', $checkout );

// Registration not required for B2C — guests checkout freely.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout lager-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

	<div class="lager-checkout__grid">
	<div class="lager-checkout__main">
	<?php if ( $checkout->get_checkout_fields() ) : ?>
		<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
		<div id="customer_details">
			<?php do_action( 'woocommerce_checkout_billing' ); ?>
		</div>
		<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
	<?php endif; ?>
	</div><!-- .lager-checkout__main -->

	<div class="lager-checkout__side">
	<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
	<h3 id="order_review_heading" class="lager-order-heading"><?php esc_html_e( 'Vaša porudžbina', 'lager032' ); ?></h3>

	<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
	<div id="order_review" class="woocommerce-checkout-review-order">
		<?php do_action( 'woocommerce_checkout_order_review' ); ?>
	</div>
	<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
	</div><!-- .lager-checkout__side -->
	</div><!-- .lager-checkout__grid -->

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
