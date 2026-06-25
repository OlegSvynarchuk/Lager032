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
		<div class="sec-head sec-head--center">
			<h2 class="sec-title"><?php esc_html_e( 'Naša ponuda obuhvata:', 'lager032' ); ?></h2>
		</div>

		<div class="cats__grid">
			<?php
			// label, subtitle, image file, product_cat slug ('' = shop)
			$cats = array(
				array( 'Ležajevi', 'Kuglični, valjkasti, aksijalni, konični', 'cat-lezajevi.jpg', 'lezaj' ),
				array( 'Semerinzi', 'NBR, FKM, PTFE zaptivke', 'cat-semerinzi.jpg', 'semering' ),
				array( 'Remenje', 'Klinasti, rebrasti, zupčasti', 'cat-remenje.jpg', 'remen' ),
				array( 'Segeri', 'Unutrašnji i spoljašnji DIN 471/472', 'cat-segeri.jpg', 'seger' ),
				array( 'Krstovi Kardana', 'Kardanski krstovi svih dimenzija', 'cat-krstovi.jpg', 'krst-kardana' ),
				array( 'Masti', 'Litijumske, EP, visokotemperaturne', 'cat-masti.jpg', 'masti' ),
				array( 'Kućišta', 'Pernasta, četvrtasta kućišta', 'cat-kucista.jpg', '' ),
				array( 'Lanci i Lančanici', 'Standardni i specijalni lanci', 'cat-lanci.jpg', 'lanci-i-lancanici' ),
				array( 'Hilzne', 'Adapterske i zaključne hilzne', 'cat-hilzne.jpg', 'hilzna' ),
				array( 'KM Navrtke', 'Za montažu ležajeva', 'cat-navrtke.jpg', 'navrtka' ),
				array( 'MB Podloške', 'Sigurnosne podloške za navrtke', 'cat-podloske.jpg', '' ),
				array( 'Kuglice', 'Čelične i inoks kuglice', 'cat-kuglice.jpg', 'kuglica' ),
			);
			foreach ( $cats as $c ) {
				list( $label, $subtitle, $img, $slug ) = $c;
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
					'<a class="catcard" href="%1$s" style="background-image:url(\'%2$s\')"><span class="catcard__shade" aria-hidden="true"></span><span class="catcard__cta">Preuzmi katalog</span><span class="catcard__body"><span class="catcard__name">%3$s</span><span class="catcard__desc">%4$s</span></span></a>',
					esc_url( $url ),
					esc_url( $home_img . '/' . $img ),
					esc_html( $label ),
					esc_html( $subtitle )
				);
			}
			?>
		</div>
	</div>
</section>

<!-- =========================== O NAMA =========================== -->
<section class="about">
	<div class="container about__inner">
		<div class="about__media">
			<img src="<?php echo esc_url( $home_img . '/about-magacin.jpg?v=' . filemtime( get_template_directory() . '/assets/img/home/about-magacin.jpg' ) ); ?>" alt="<?php esc_attr_e( 'LAGER magacin', 'lager032' ); ?>" loading="lazy">
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
			<h2 class="sec-title"><?php esc_html_e( 'Veliki asortiman najvećih brendova', 'lager032' ); ?></h2>
		</div>
		<div class="brands__slider">
			<div class="brands__track">
				<?php
				$brand_logos = array(
					array( 'brand-skf.png', 'SKF' ),
					array( 'brand-wurth.png', 'Würth' ),
					array( 'brand-ntn-snr.png', 'NTN / SNR' ),
				);
				foreach ( $brand_logos as $bl ) {
					printf(
						'<div class="brands__cell"><img class="brands__logo" src="%1$s" alt="%2$s" loading="lazy"></div>',
						esc_url( $home_img . '/' . $bl[0] ),
						esc_attr( $bl[1] )
					);
				}
				?>
			</div>
			<div class="brands__dots" aria-hidden="true"></div>
		</div>
	</div>
</section>

<!-- =========================== KONTAKT =========================== -->
<section class="contact" id="kontakt">
	<div class="container">
		<div class="sec-head sec-head--center">
			<span class="sec-eyebrow"><?php esc_html_e( 'Kontakt', 'lager032' ); ?></span>
			<h2 class="sec-title"><?php esc_html_e( 'Za sva pitanja kontaktirajte nas', 'lager032' ); ?></h2>
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
