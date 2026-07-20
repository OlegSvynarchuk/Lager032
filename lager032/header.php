<?php
/**
 * Header — redesign 2026-06-24 (Figma node 47:2420).
 * Top utility bar (navy): contacts + search + cart.
 * Main nav bar (white): logo · nav links · red "Prodavnica" button (category mega-dropdown).
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' );
$cart_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/korpa/' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Preskoči na sadržaj', 'lager032' ); ?></a>

<div class="siteheader">

	<!-- Top utility bar -->
	<div class="util-bar">
		<div class="container util-bar__inner">
			<div class="util-bar__contacts">
				<span class="util-bar__phones">
					<?php lager032_icon( 'phone' ); ?>
					<a href="tel:+38132342281">+381 32 342 281</a>
					<a href="tel:+381631093199">+381 63 109 31 99</a>
				</span>
				<a href="mailto:lager032@gmail.com"><?php lager032_icon( 'mail' ); ?><span>lager032@gmail.com</span></a>
				<span class="util-bar__addr"><?php lager032_icon( 'pin' ); ?><span><?php esc_html_e( 'LAGER STR, Kneza Miloša 100, Čačak', 'lager032' ); ?></span></span>
			</div>

			<div class="util-bar__tools">
				<form class="searchbar" role="search" method="get" action="<?php echo esc_url( $shop_url ); ?>">
					<?php lager032_icon( 'search' ); ?>
					<input type="search" name="q" value="<?php echo isset( $_GET['q'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['q'] ) ) ) : ''; ?>"
						placeholder="<?php esc_attr_e( 'Pretraži artikle...', 'lager032' ); ?>"
						aria-label="<?php esc_attr_e( 'Pretraži artikle', 'lager032' ); ?>">
				</form>

				<a class="cartbtn" href="<?php echo esc_url( $cart_url ); ?>" aria-label="<?php esc_attr_e( 'Korpa', 'lager032' ); ?>">
					<?php lager032_icon( 'cart' ); ?>
					<?php $cc = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0; ?>
					<span class="cartbtn__count"<?php echo $cc ? '' : ' hidden'; ?>><?php echo esc_html( $cc ); ?></span>
				</a>
			</div>
		</div>
	</div>

	<!-- Main navigation bar -->
	<header class="masthead">
		<div class="container masthead__inner">

			<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<img class="brand__img" src="<?php echo esc_url( lager_site_image( 'lager_logo', lager_theme_img( '/assets/img/logo.png' ) ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			</a>

			<button class="navtoggle" aria-label="<?php esc_attr_e( 'Meni', 'lager032' ); ?>" aria-expanded="false">
				<span></span><span></span><span></span>
			</button>

			<nav class="mainnav" aria-label="<?php esc_attr_e( 'Glavni meni', 'lager032' ); ?>">
				<ul class="mainnav__list">
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Početna', 'lager032' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/o-nama/' ) ); ?>"><?php esc_html_e( 'O nama', 'lager032' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/katalog/' ) ); ?>"><?php esc_html_e( 'Katalog', 'lager032' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/kontakt/' ) ); ?>"><?php esc_html_e( 'Kontakt', 'lager032' ); ?></a></li>
				</ul>

				<?php
				// "Prodavnica" red button + category mega-dropdown (curated top-level categories;
				// those with product_cat children get a subcategory flyout on hover).
				$mega = array(
					array( 'Ležajevi', 'Kuglični, valjkasti, aksijalni, konični', 'lezaj' ),
					array( 'Semerinzi', 'NBR, FKM, PTFE zaptivke', 'semering' ),
					array( 'Remenje', 'Klinasti, rebrasti, zupčasti', 'remen' ),
					array( 'Segeri', 'Unutrašnji i spoljašnji DIN 471/472', 'seger' ),
					array( 'Krstovi Kardana', 'Kardanski krstovi svih dimenzija', 'krst-kardana' ),
					array( 'Masti', 'Litijumske, EP, visokotemperaturne', 'masti' ),
					array( 'Lanci i Lančanici', 'Standardni i specijalni lanci', 'lanci-i-lancanici' ),
					array( 'Hilzne', 'Adapterske i zaključne hilzne', 'hilzna' ),
					array( 'KM Navrtke & MB Podloške', 'Za montažu ležajeva', 'navrtka' ),
					array( 'Kuglice', 'Čelične i inoks kuglice', 'kuglica' ),
					array( 'Kućišta', 'Pernasta, četvrtasta kućišta', '' ),
					array( 'Klinovi', 'Mašinski klinovi i žljebovi', 'klin' ),
				);
				?>
				<div class="shopcats">
					<a class="shopcats__btn" href="<?php echo esc_url( $shop_url ); ?>" aria-haspopup="true">
						<span><?php esc_html_e( 'Prodavnica', 'lager032' ); ?></span>
						<?php lager032_icon( 'chevron' ); ?>
					</a>

					<div class="megamenu" role="menu">
						<div class="megamenu__head">
							<span class="megamenu__title"><?php esc_html_e( 'Kategorije proizvoda', 'lager032' ); ?></span>
							<a class="megamenu__all" href="<?php echo esc_url( $shop_url ); ?>">
								<?php esc_html_e( 'Sve kategorije', 'lager032' ); ?><?php lager032_icon( 'arrow' ); ?>
							</a>
						</div>
						<div class="megamenu__grid">
							<?php
							foreach ( $mega as $m ) {
								list( $label, $sub, $slug ) = $m;
								$url  = $shop_url;
								$kids = array();
								if ( $slug ) {
									$term = get_term_by( 'slug', $slug, 'product_cat' );
									if ( $term && ! is_wp_error( $term ) ) {
										$link = get_term_link( $term );
										if ( ! is_wp_error( $link ) ) {
											$url = $link;
										}
										$kids = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => false ) );
										if ( is_wp_error( $kids ) ) {
											$kids = array();
										}
									}
								}

								if ( $kids ) {
									echo '<div class="megacat megacat--has-sub">';
									printf(
										'<a class="megacat__main" href="%1$s"><span class="megacat__name">%2$s</span><span class="megacat__sub">%3$s</span></a>',
										esc_url( $url ),
										esc_html( $label ),
										esc_html( $sub )
									);
									// Long subcategory lists (e.g. Ležaj ~22) wrap into 2 columns so the flyout isn't excessively tall.
									$submenu_cls = ( count( $kids ) > 8 ) ? ' submenu--cols' : '';
									printf( '<div class="submenu%s" role="menu">', esc_attr( $submenu_cls ) );
									printf(
										'<a class="submenu__all" href="%1$s">%2$s</a>',
										esc_url( $url ),
										/* translators: %s: category name. */
										esc_html( sprintf( __( 'Svi: %s', 'lager032' ), $label ) )
									);
									foreach ( $kids as $kid ) {
										$kl = get_term_link( $kid );
										printf(
											'<a class="submenu__item" href="%1$s">%2$s</a>',
											esc_url( is_wp_error( $kl ) ? $shop_url : $kl ),
											esc_html( $kid->name )
										);
									}
									echo '</div></div>';
								} else {
									printf(
										'<a class="megacat" href="%1$s" role="menuitem"><span class="megacat__name">%2$s</span><span class="megacat__sub">%3$s</span></a>',
										esc_url( $url ),
										esc_html( $label ),
										esc_html( $sub )
									);
								}
							}
							?>
						</div>
					</div>
				</div>
			</nav>
		</div>
	</header>

</div><!-- .siteheader -->

<main id="content" class="site-content">
