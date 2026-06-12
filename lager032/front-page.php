<?php
/**
 * Front page (Početna) — redesign 2026-06-12 (Figma node 110:2027).
 * Sections: Hero · Kategorije · Proizvodi · O Nama · Brendovi · Kontakt.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$uri      = get_template_directory_uri();
$home_img = $uri . '/assets/img/home';
$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' );
?>

<!-- ============================ HERO ============================ -->
<section class="hero" style="background-image:url('<?php echo esc_url( $home_img . '/hero.jpg' ); ?>')">
	<div class="hero__overlay" aria-hidden="true"></div>
	<div class="container hero__inner">
		<div class="hero__content">
			<span class="hero__eyebrow"><?php esc_html_e( 'Online kupovina — brza isporuka', 'lager032' ); ?></span>
			<h1 class="hero__title">
				<?php esc_html_e( 'Ležajevi i industrijska', 'lager032' ); ?>
				<span><?php esc_html_e( 'oprema za svaki stroj', 'lager032' ); ?></span>
			</h1>
			<p class="hero__lead"><?php esc_html_e( 'Više od 25 godina iskustva u snabdevanju ležajevima, semerinzima, remenjima i celokupnom industrijskom opremom od vodećih svetskih proizvođača.', 'lager032' ); ?></p>
			<div class="hero__cta">
				<a class="btn btn--red" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Pregledaj katalog', 'lager032' ); ?> <?php lager032_icon( 'arrow' ); ?></a>
				<a class="btn btn--ghost" href="tel:+38132342281"><?php esc_html_e( 'Pozovite nas', 'lager032' ); ?></a>
			</div>
		</div>
	</div>
</section>

<!-- HERO KPI band — full width, underneath the hero -->
<div class="hero__stats">
	<div class="container hero__stats-inner">
		<?php
		$stats = array(
			array( 'clock', '25+', 'Godina iskustva' ),
			array( 'box', '5.000+', 'Artikala na lageru' ),
			array( 'truck', 'Brza', 'isporuka kurirskom službom' ),
			array( 'check', '6', 'Sertifikovanih brendova' ),
		);
		foreach ( $stats as $st ) {
			printf(
				'<div class="herostat">%1$s<div><strong>%2$s</strong><span>%3$s</span></div></div>',
				lager032_get_icon( $st[0] ),
				esc_html( $st[1] ),
				esc_html( $st[2] )
			);
		}
		?>
	</div>
</div>

<!-- ========================= KATEGORIJE ========================= -->
<section class="cats">
	<div class="container">
		<div class="sec-head">
			<div>
				<span class="sec-eyebrow"><?php esc_html_e( 'Asortiman', 'lager032' ); ?></span>
				<h2 class="sec-title"><?php esc_html_e( 'Kategorije proizvoda', 'lager032' ); ?></h2>
			</div>
			<a class="sec-link" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Svi proizvodi', 'lager032' ); ?> <?php lager032_icon( 'arrow' ); ?></a>
		</div>

		<div class="cats__grid">
			<?php
			// label, count (design copy), image file, product_cat slug ('' = shop)
			$cats = array(
				array( 'Ležajevi', '1.200+ artikala', 'cat-lezajevi.jpg', 'lezaj' ),
				array( 'Semerinzi', '320+ artikala', 'cat-semerinzi.jpg', 'semering' ),
				array( 'Remenje', '180+ artikala', 'cat-remenje.jpg', 'remen' ),
				array( 'Lanci i Lančanici', '240+ artikala', 'cat-lanci.jpg', 'lanci-i-lancanici' ),
				array( 'Masti', '60+ artikala', 'cat-masti.jpg', 'masti' ),
				array( 'Kućišta', '400+ artikala', 'cat-kucista.jpg', '' ),
			);
			foreach ( $cats as $c ) {
				list( $label, $count, $img, $slug ) = $c;
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
				printf(
					'<a class="catcard" href="%1$s" style="background-image:url(\'%2$s\')"><span class="catcard__shade" aria-hidden="true"></span><span class="catcard__body"><span class="catcard__name">%3$s</span><span class="catcard__count">%4$s</span></span></a>',
					esc_url( $url ),
					esc_url( $home_img . '/' . $img ),
					esc_html( $label ),
					esc_html( $count )
				);
			}
			?>
		</div>
	</div>
</section>

<!-- ========================= PROIZVODI ========================= -->
<section class="catalog">
	<div class="container">
		<div class="sec-head">
			<div>
				<span class="sec-eyebrow"><?php esc_html_e( 'Katalog', 'lager032' ); ?></span>
				<h2 class="sec-title"><?php esc_html_e( 'Proizvodi', 'lager032' ); ?></h2>
			</div>
		</div>

		<form class="catalog__filters" role="search" method="get" action="<?php echo esc_url( $shop_url ); ?>">
			<div class="catalog__search">
				<?php lager032_icon( 'search' ); ?>
				<input type="search" name="s" placeholder="<?php esc_attr_e( 'Pretraži po nazivu ili šifri...', 'lager032' ); ?>" aria-label="<?php esc_attr_e( 'Pretraga', 'lager032' ); ?>">
				<input type="hidden" name="post_type" value="product">
			</div>
			<?php // Filters are visual for now; functional facets land in Phase 3 (AJAX). ?>
			<select name="product_cat" aria-label="<?php esc_attr_e( 'Kategorija', 'lager032' ); ?>">
				<option value=""><?php esc_html_e( 'Sve kategorije', 'lager032' ); ?></option>
				<?php
				$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0, 'number' => 30 ) );
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $t ) {
						if ( 'uncategorized' === $t->slug ) {
							continue;
						}
						printf( '<option value="%1$s">%2$s</option>', esc_attr( $t->slug ), esc_html( $t->name ) );
					}
				}
				?>
			</select>
			<select name="brand" aria-label="<?php esc_attr_e( 'Brend', 'lager032' ); ?>">
				<option value=""><?php esc_html_e( 'Svi brendovi', 'lager032' ); ?></option>
			</select>
			<select name="orderby" aria-label="<?php esc_attr_e( 'Sortiraj', 'lager032' ); ?>">
				<option value="date"><?php esc_html_e( 'Najnovije', 'lager032' ); ?></option>
				<option value="price"><?php esc_html_e( 'Cena: rastuće', 'lager032' ); ?></option>
				<option value="price-desc"><?php esc_html_e( 'Cena: opadajuće', 'lager032' ); ?></option>
				<option value="title"><?php esc_html_e( 'Naziv: A–Z', 'lager032' ); ?></option>
			</select>
		</form>

		<div class="catalog__grid">
			<?php
			$products = function_exists( 'wc_get_products' ) ? wc_get_products( array(
				'status'  => 'publish',
				'limit'   => 12,
				'orderby' => 'date',
				'order'   => 'DESC',
			) ) : array();

			if ( $products ) {
				foreach ( $products as $product ) {
					$pid   = $product->get_id();
					$img   = $product->get_image( 'woocommerce_thumbnail' );
					$terms = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'names' ) );
					$cat   = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : '';
					$sku   = $product->get_sku();
					$net   = $product->get_regular_price();
					$tag   = trim( $cat . ( $sku ? ' · ' . $sku : '' ), ' ·' );
					?>
					<div class="prodcard">
						<a class="prodcard__media" href="<?php echo esc_url( get_permalink( $pid ) ); ?>">
							<?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo image markup. ?>
						</a>
						<div class="prodcard__body">
							<?php if ( $tag ) : ?><span class="prodcard__tag"><?php echo esc_html( $tag ); ?></span><?php endif; ?>
							<h3 class="prodcard__name"><a href="<?php echo esc_url( get_permalink( $pid ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
							<div class="prodcard__foot">
								<span class="prodcard__price"><?php echo wp_kses_post( wc_price( $net ) ); ?><small><?php esc_html_e( 'bez PDV-a', 'lager032' ); ?></small></span>
								<a class="btn btn--navy btn--sm" href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"><?php lager032_icon( 'cart' ); ?> <?php esc_html_e( 'Dodaj', 'lager032' ); ?></a>
							</div>
						</div>
					</div>
					<?php
				}
			} else {
				echo '<p class="catalog__empty">' . esc_html__( 'Katalog se uskoro objavljuje. Proizvodi su trenutno u pripremi.', 'lager032' ) . '</p>';
			}
			?>
		</div>
	</div>
</section>

<!-- =========================== O NAMA =========================== -->
<section class="about">
	<div class="container about__inner">
		<div class="about__media">
			<img src="<?php echo esc_url( $home_img . '/about-magacin.jpg' ); ?>" alt="<?php esc_attr_e( 'LAGER magacin', 'lager032' ); ?>" loading="lazy">
			<div class="about__badge"><strong>25+</strong><span><?php esc_html_e( 'godina na tržištu', 'lager032' ); ?></span></div>
		</div>
		<div class="about__content">
			<span class="sec-eyebrow"><?php esc_html_e( 'O Nama', 'lager032' ); ?></span>
			<h2 class="sec-title"><?php esc_html_e( 'Vaš partner u industrijskoj nabavci', 'lager032' ); ?></h2>
			<p class="about__lead"><?php esc_html_e( '„LAGER" STR je specijalizovana prodavnica ležajeva i industrijske opreme sa sedištem u Čačku. Isporučujemo ležajeve, semerinze, remenje, lančane prenose i drugu opremu od vodećih svetskih proizvođača — FAG, SKF, Timken, SNR, ZVL i mnogi drugi. Nudimo povoljne cene, tehničku podršku i brzu dostavu.', 'lager032' ); ?></p>
			<div class="about__features">
				<?php
				$features = array(
					array( 'clock', '25 godina iskustva', 'Od 1999. godine pouzdano snabdevamo industriju Srbije ležajevima i mehaničkim delovima.' ),
					array( 'check', 'Stručna podrška', 'Naš tim tehničkih stručnjaka pomaže Vam pri odabiru pravog rešenja za Vaše aplikacije.' ),
					array( 'box', '5.000+ artikala', 'Bogat magacinski stok od preko 5.000 artikala — gotovinska i predračunska kupovina.' ),
					array( 'truck', 'Lokalna dostupnost', 'Lično preuzimanje u Čačku ili brza dostava kurirskom službom širom Srbije.' ),
				);
				foreach ( $features as $f ) {
					printf(
						'<div class="feat">%1$s<div><strong>%2$s</strong><p>%3$s</p></div></div>',
						lager032_get_icon( $f[0] ),
						esc_html( $f[1] ),
						esc_html( $f[2] )
					);
				}
				?>
			</div>
		</div>
	</div>
</section>

<!-- ========================== BRENDOVI ========================== -->
<section class="brands">
	<div class="container">
		<div class="sec-head sec-head--center">
			<span class="sec-eyebrow"><?php esc_html_e( 'Sertifikati', 'lager032' ); ?></span>
			<h2 class="sec-title"><?php esc_html_e( 'Ovlašćeni distributer vodećih brendova', 'lager032' ); ?></h2>
			<p class="sec-sub"><?php esc_html_e( 'Sarađujemo isključivo sa originalnim proizvođačima i ovlašćenim distributerima, garantujući autentičnost i kvalitet svakog proizvoda.', 'lager032' ); ?></p>
		</div>
		<div class="brands__grid">
			<?php
			$brands = array(
				array( 'FAG', 'Schaeffler Group — Nemačka' ),
				array( 'SKF', 'Sverige Kullager-Fabriken — Švedska' ),
				array( 'SNR', 'NTN-SNR — Francuska' ),
				array( 'Timken', 'The Timken Company — SAD' ),
				array( 'ZVL', 'ZVL Slovakia — Slovačka' ),
				array( 'Suptex', 'Suptex Seals — Srbija' ),
				array( 'WBF', 'WBF Bearings — Nemačka' ),
				array( 'Gates', 'Gates Corporation — SAD' ),
			);
			foreach ( $brands as $b ) {
				printf(
					'<div class="brandcard"><span class="brandcard__logo">%1$s</span><span class="brandcard__name">%1$s</span><span class="brandcard__desc">%2$s</span></div>',
					esc_html( $b[0] ),
					esc_html( $b[1] )
				);
			}
			?>
		</div>
	</div>
</section>

<!-- =========================== KONTAKT =========================== -->
<section class="contact" id="kontakt">
	<div class="container">
		<div class="sec-head sec-head--center">
			<span class="sec-eyebrow"><?php esc_html_e( 'Kontakt', 'lager032' ); ?></span>
			<h2 class="sec-title"><?php esc_html_e( 'Stupite u kontakt sa nama', 'lager032' ); ?></h2>
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
	</div>
</section>

<?php
get_footer();
