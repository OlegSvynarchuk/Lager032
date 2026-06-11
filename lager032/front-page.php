<?php
/**
 * Front page (Početna).
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$uri = get_template_directory_uri();
?>

<!-- HERO -->
<section class="hero">
	<div class="hero__bg" aria-hidden="true"></div>
	<div class="hero__overlay" aria-hidden="true"></div>
	<div class="hero__content">
		<h1 class="hero__title"><?php esc_html_e( 'Pouzdan partner za svaku mašinu.', 'lager032' ); ?></h1>
		<p class="hero__subtitle"><?php esc_html_e( 'Širok asortiman ležajeva i prenosne opreme uz stručnu podršku i brzu isporuku.', 'lager032' ); ?></p>
	</div>
</section>

<!-- BRAND STRIP -->
<section class="brand-strip">
	<div class="container brand-strip__logos">
		<?php
		$brands = array(
			'brand-a.png' => 'SKF',
			'brand-b.png' => 'Würth',
			'brand-c.png' => 'NTN / SNR',
		);
		foreach ( $brands as $file => $alt ) {
			printf(
				'<img src="%1$s" alt="%2$s" loading="lazy">',
				esc_url( $uri . '/assets/img/brands/' . $file ),
				esc_attr( $alt )
			);
		}
		?>
	</div>
	<div class="brand-strip__dots" aria-hidden="true">
		<span class="is-active"></span><span></span><span></span>
	</div>
</section>

<!-- NAŠA PONUDA — category grid -->
<section class="ponuda">
	<div class="container">
		<h2 class="ponuda__title"><?php esc_html_e( 'Naša ponuda obuhvata:', 'lager032' ); ?></h2>

		<div class="cat-grid">
			<?php
			// Featured categories from the Figma (label => product_cat slug; '' = not yet mapped).
			$cats = array(
				array( 'LEŽAJEVI', 'lezaj' ),
				array( 'SEMERINZI', 'semering' ),
				array( 'REMENJE', 'remen' ),
				array( 'SEGERI', 'seger' ),
				array( 'KRSTOVI KARDANA', 'krst-kardana' ),
				array( 'MASTI', 'masti' ),
				array( 'WURTH', 'wurth' ),
				array( 'LANCI I LANČANICI', 'lanci-i-lancanici' ),
				array( 'HILZNE', 'hilzna' ),
				array( 'KM NAVRKE', 'navrtka' ),
				array( 'MB PODLOŠKE', '' ),
				array( 'KUGLICE', 'kuglica' ),
			);

			foreach ( $cats as $cat ) {
				list( $label, $slug ) = $cat;
				$url   = '#';
				$style = '';

				if ( $slug ) {
					$term = get_term_by( 'slug', $slug, 'product_cat' );
					if ( $term && ! is_wp_error( $term ) ) {
						$link = get_term_link( $term );
						if ( ! is_wp_error( $link ) ) {
							$url = $link;
						}
						$thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
						if ( $thumb_id ) {
							$src = wp_get_attachment_image_url( $thumb_id, 'medium_large' );
							if ( $src ) {
								$style = ' style="background-image:url(' . esc_url( $src ) . ')"';
							}
						}
					}
				}

				printf(
					'<a class="cat-card" href="%1$s"><span class="cat-card__bg"%2$s aria-hidden="true"></span><span class="cat-card__label">%3$s</span></a>',
					esc_url( $url ),
					$style, // pre-escaped above
					esc_html( $label )
				);
			}
			?>
		</div>
	</div>
</section>

<!-- ABOUT — "Vaš partner u industrijskoj nabavci" (to build next) -->
<section class="about">
	<p class="section-todo"><?php esc_html_e( 'Sledeće: sekcija „Vaš partner u industrijskoj nabavci“ (foto skladišta + tekst + prednosti).', 'lager032' ); ?></p>
</section>

<!-- CONTACT — "Za sva pitanja kontaktirajte nas" (to build next) -->
<section class="contact">
	<p class="section-todo"><?php esc_html_e( 'Sledeće: sekcija „Za sva pitanja kontaktirajte nas“ (kontakt podaci + forma).', 'lager032' ); ?></p>
</section>

<?php
get_footer();
