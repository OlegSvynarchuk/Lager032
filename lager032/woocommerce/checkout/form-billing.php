<?php
/**
 * Checkout billing fields (theme override) — split into "Podaci kupca" and
 * "Podaci za dostavu" sections driven by each field's 'lager_section' key.
 *
 * @package Lager032
 */

defined( 'ABSPATH' ) || exit;

$fields  = $checkout->get_checkout_fields( 'billing' );
$titles  = array(
	'customer' => 'Podaci kupca',
	'delivery' => 'Podaci za dostavu',
);
$current = '';
?>
<div class="woocommerce-billing-fields lager-checkout-fields">
	<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

	<?php
	foreach ( $fields as $key => $field ) {
		// Country is fixed to Serbia and posted invisibly.
		if ( 'billing_country' === $key ) {
			echo '<input type="hidden" name="billing_country" value="RS" id="billing_country" />';
			continue;
		}

		$section = isset( $field['lager_section'] ) ? $field['lager_section'] : '';
		if ( $section && $section !== $current ) {
			if ( '' !== $current ) {
				echo '</div>'; // close previous section grid
			}
			$label = isset( $titles[ $section ] ) ? $titles[ $section ] : '';
			echo '<h3 class="lager-checkout-fields__title">' . esc_html( $label ) . '</h3>';
			echo '<div class="lager-checkout-fields__grid">';
			$current = $section;
		}

		woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
	}
	if ( '' !== $current ) {
		echo '</div>';
	}
	?>

	<p class="lager-required-note">Polja označena sa <abbr class="required">*</abbr> su obavezna polja.</p>

	<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
</div>
