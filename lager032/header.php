<?php
/**
 * Header — redesign 2026-06-12 (Figma nodes 106:3461 / 106:3506).
 * Utility bar + masthead (logo · "Svi proizvodi" mega-dropdown · nav · search · cart).
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
<!-- Utility bar -->
<div class="util-bar">
	<div class="container util-bar__inner">
		<div class="util-bar__contacts">
			<a href="tel:+38132342281"><?php lager032_icon( 'phone' ); ?><span>+381 32 342 281</span></a>
			<a href="tel:+381631093199"><?php lager032_icon( 'phone' ); ?><span>+381 63 109 31 99</span></a>
		</div>
		<div class="util-bar__meta">
			<a href="mailto:lager032@gmail.com"><?php lager032_icon( 'mail' ); ?><span>lager032@gmail.com</span></a>
			<span class="util-bar__addr"><?php esc_html_e( 'Kneza Miloša 100, Čačak', 'lager032' ); ?></span>
		</div>
	</div>
</div>

<!-- Masthead -->
<header class="masthead">
	<div class="container masthead__inner">

		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<img class="brand__img" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/logo.png' ); ?>" width="141" height="36" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		</a>

		<?php
		// "Svi proizvodi" mega-dropdown. Categories from the Figma (label, subtitle, slug).
		// Subtitles are curated copy; slug links to the product_cat archive ('' = not mapped yet).
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
			array( 'Linearne tehnologije', 'Vođice, klizači, šine', '' ),
		);
		$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' );
		?>
		<div class="shopcats">
			<a class="shopcats__btn" href="<?php echo esc_url( $shop_url ); ?>" aria-haspopup="true">
				<?php lager032_icon( 'grid' ); ?>
				<span><?php esc_html_e( 'Svi proizvodi', 'lager032' ); ?></span>
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
							'<a class="megacat" href="%1$s" role="menuitem"><span class="megacat__name">%2$s</span><span class="megacat__sub">%3$s</span></a>',
							esc_url( $url ),
							esc_html( $label ),
							esc_html( $sub )
						);
					}
					?>
				</div>
			</div>
		</div>

		<nav class="mainnav" aria-label="<?php esc_attr_e( 'Glavni meni', 'lager032' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'mainnav__list',
					'depth'          => 1,
				) );
			} else {
				printf(
					'<ul class="mainnav__list"><li><a href="%1$s">%2$s</a></li><li><a href="#">O Nama</a></li><li><a href="#">Sertifikati</a></li><li><a href="#">Kontakt</a></li></ul>',
					esc_url( home_url( '/' ) ),
					esc_html__( 'Početna', 'lager032' )
				);
			}
			?>
		</nav>

		<form class="searchbar" role="search" method="get" action="<?php echo esc_url( $shop_url ); ?>">
			<?php lager032_icon( 'search' ); ?>
			<input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>"
				placeholder="<?php esc_attr_e( 'Pretraži artikle...', 'lager032' ); ?>"
				aria-label="<?php esc_attr_e( 'Pretraži artikle', 'lager032' ); ?>">
			<input type="hidden" name="post_type" value="product">
		</form>

		<?php $cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/korpa/' ); ?>
		<a class="cartbtn" href="<?php echo esc_url( $cart_url ); ?>">
			<?php lager032_icon( 'cart' ); ?>
			<span><?php esc_html_e( 'Korpa', 'lager032' ); ?></span>
		</a>

		<button class="navtoggle" aria-label="<?php esc_attr_e( 'Meni', 'lager032' ); ?>" aria-expanded="false">
			<span></span><span></span><span></span>
		</button>
	</div>
</header>
</div><!-- .siteheader -->

<main id="content" class="site-content">
