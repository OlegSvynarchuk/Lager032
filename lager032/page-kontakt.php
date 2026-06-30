<?php
/**
 * "Kontakt" page — contact details, working contact form (reuses the homepage
 * lager_contact handler) and an embedded map.
 *
 * Auto-applied to the page with slug `kontakt` (template hierarchy: page-{slug}.php).
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$map_q = rawurlencode( 'Kneza Miloša 100, 32000 Čačak, Srbija' );
?>

<section class="contact contact--page" id="kontakt">
	<div class="container">
		<div class="sec-head sec-head--center">
			<span class="sec-eyebrow"><?php esc_html_e( 'Kontakt', 'lager032' ); ?></span>
			<h1 class="sec-title"><?php esc_html_e( 'Za sva pitanja kontaktirajte nas', 'lager032' ); ?></h1>
		</div>

		<div class="contact__grid">
			<div class="contact__info">
				<?php
				$info = array(
					array( 'pin', 'Adresa', 'Kneza Miloša 100, 32000 Čačak, Srbija' ),
					array( 'phone', 'Telefon', '+381 32 342 281 · +381 63 109 31 99' ),
					array( 'mail', 'Email', 'lager032@gmail.com' ),
					array( 'clock', 'Radno vreme', 'Pon–Pet: 08:00–16:00 · Sub: 08:00–13:00' ),
				);
				foreach ( $info as $i ) {
					printf(
						'<div class="cinfo"><span class="cinfo__ic">%1$s</span><div><span class="cinfo__label">%2$s</span><span class="cinfo__val">%3$s</span></div></div>',
						lager032_get_icon( $i[0] ),
						esc_html( $i[1] ),
						esc_html( $i[2] )
					);
				}
				?>
				<div class="contact__note">
					<strong><?php esc_html_e( 'Naručivanje putem telefona', 'lager032' ); ?></strong>
					<p><?php esc_html_e( 'Navedite šifru artikla ili tražene dimenzije. Naš stručni tim će Vam pomoći pri odabiru i dostupnosti na stanju.', 'lager032' ); ?></p>
				</div>
			</div>

			<div class="contact__form-wrap">
				<?php
				$kontakt = isset( $_GET['kontakt'] ) ? sanitize_key( $_GET['kontakt'] ) : '';
				if ( 'ok' === $kontakt ) {
					echo '<p class="formmsg formmsg--ok">' . esc_html__( 'Hvala! Vaša poruka je poslata. Javićemo se uskoro.', 'lager032' ) . '</p>';
				} elseif ( 'greska' === $kontakt ) {
					echo '<p class="formmsg formmsg--err">' . esc_html__( 'Došlo je do greške. Proverite podatke i pokušajte ponovo.', 'lager032' ) . '</p>';
				}
				?>
				<form class="cform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="lager_contact">
					<?php wp_nonce_field( 'lager_contact', 'lager_contact_nonce' ); ?>
					<div class="cform__row">
						<label>
							<span><?php esc_html_e( 'Ime i prezime', 'lager032' ); ?> *</span>
							<input type="text" name="ime" required>
						</label>
						<label>
							<span><?php esc_html_e( 'Telefon', 'lager032' ); ?></span>
							<input type="tel" name="telefon">
						</label>
					</div>
					<label>
						<span><?php esc_html_e( 'Email', 'lager032' ); ?> *</span>
						<input type="email" name="email" required>
					</label>
					<label>
						<span><?php esc_html_e( 'Poruka', 'lager032' ); ?> *</span>
						<textarea name="poruka" rows="5" required placeholder="<?php esc_attr_e( 'Opišite šta tražite — dimenzije, šifru, ili aplikaciju...', 'lager032' ); ?>"></textarea>
					</label>
					<button type="submit" class="btn btn--navy btn--block"><?php esc_html_e( 'Pošalji poruku', 'lager032' ); ?></button>
				</form>
			</div>
		</div>

		<div class="contact__map">
			<iframe
				src="https://www.google.com/maps?q=<?php echo esc_attr( $map_q ); ?>&output=embed"
				title="<?php esc_attr_e( 'LAGER STR — Kneza Miloša 100, Čačak', 'lager032' ); ?>"
				loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
		</div>
	</div>
</section>

<?php
get_footer();
