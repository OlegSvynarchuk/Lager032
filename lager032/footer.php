<?php
/**
 * Footer — redesign 2026-06-12 (Figma node 110:2027 footer).
 * 4 columns (brand · Navigacija · Kategorije · Kontakt) + legal bar.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' );

// Footer category links (label => slug; '' = not mapped → shop).
$foot_cats = array(
	'Ležajevi'          => 'lezaj',
	'Semerinzi'         => 'semering',
	'Remenje'           => 'remen',
	'Segeri'            => 'seger',
	'Lanci i Lančanici' => 'lanci-i-lancanici',
	'Masti'             => 'masti',
	'Kućišta'           => '',
);
?>
</main><!-- #content -->

<footer class="sitefoot">
	<div class="container sitefoot__cols">

		<div class="sitefoot__brand">
			<a class="footbrand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<span class="footbrand__badge">L032</span>
				<span class="footbrand__text">
					<span class="footbrand__name">LAGER STR</span>
					<span class="footbrand__sub"><?php esc_html_e( 'Čačak, Srbija', 'lager032' ); ?></span>
				</span>
			</a>
			<p class="sitefoot__blurb">
				<?php esc_html_e( 'Specijalizovana prodavnica ležajeva i industrijske opreme. 25+ godina iskustva i poverenja.', 'lager032' ); ?>
			</p>
		</div>

		<div class="sitefoot__col">
			<h4 class="sitefoot__title"><?php esc_html_e( 'Navigacija', 'lager032' ); ?></h4>
			<ul class="sitefoot__list">
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Početna', 'lager032' ); ?></a></li>
				<li><a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Katalog', 'lager032' ); ?></a></li>
				<li><a href="#"><?php esc_html_e( 'O Nama', 'lager032' ); ?></a></li>
				<li><a href="#"><?php esc_html_e( 'Sertifikati', 'lager032' ); ?></a></li>
				<li><a href="#"><?php esc_html_e( 'Kontakt', 'lager032' ); ?></a></li>
			</ul>
		</div>

		<div class="sitefoot__col">
			<h4 class="sitefoot__title"><?php esc_html_e( 'Kategorije', 'lager032' ); ?></h4>
			<ul class="sitefoot__list">
				<?php
				foreach ( $foot_cats as $label => $slug ) {
					$url = $shop_url;
					if ( $slug ) {
						$term = get_term_by( 'slug', $slug, 'product_cat' );
						if ( $term && ! is_wp_error( $term ) ) {
							$link = get_term_link( $term );
							if ( ! is_wp_error( $link ) ) {
								$url = $link;
							}
						}
					}
					printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $url ), esc_html( $label ) );
				}
				?>
			</ul>
		</div>

		<div class="sitefoot__col">
			<h4 class="sitefoot__title"><?php esc_html_e( 'Kontakt', 'lager032' ); ?></h4>
			<ul class="sitefoot__list sitefoot__contact">
				<li><?php lager032_icon( 'pin' ); ?><span><?php esc_html_e( 'Kneza Miloša 100, 32000 Čačak, Srbija', 'lager032' ); ?></span></li>
				<li><?php lager032_icon( 'phone' ); ?><a href="tel:+38132342281">+381 32 342 281</a></li>
				<li><?php lager032_icon( 'phone' ); ?><a href="tel:+381631093199">+381 63 109 31 99</a></li>
				<li><?php lager032_icon( 'mail' ); ?><a href="mailto:lager032@gmail.com">lager032@gmail.com</a></li>
			</ul>
		</div>

	</div>

	<div class="sitefoot__legal">
		<div class="container sitefoot__legal-inner">
			<p>
				<?php
				printf(
					/* translators: %s: current year. */
					esc_html__( '© %s LAGER STR Čačak. Sva prava zadržana.', 'lager032' ),
					esc_html( wp_date( 'Y' ) )
				);
				?>
			</p>
			<?php // NOTE: PIB / Matični broj are placeholders from the mockup — confirm real values with client. ?>
			<p class="sitefoot__legal-id"><?php esc_html_e( 'PIB: 109876543 · Matični broj: 65432198', 'lager032' ); ?></p>
		</div>
	</div>
</footer>

<!-- Mini-cart drawer -->
<div class="minicart-overlay" hidden></div>
<aside class="minicart" aria-label="<?php esc_attr_e( 'Korpa', 'lager032' ); ?>" hidden>
	<div class="minicart__head">
		<strong><?php esc_html_e( 'Vaša korpa', 'lager032' ); ?></strong>
		<button type="button" class="minicart__close" aria-label="<?php esc_attr_e( 'Zatvori', 'lager032' ); ?>">&times;</button>
	</div>
	<?php echo lager_minicart_body_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</aside>

<?php wp_footer(); ?>
</body>
</html>
