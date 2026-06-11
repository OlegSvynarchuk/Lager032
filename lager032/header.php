<?php
/**
 * Header: utility bar + main header/nav.
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

<!-- Utility bar: contacts + search/cart -->
<div class="topbar">
	<div class="container topbar__inner">
		<ul class="topbar__contacts">
			<li>
				<?php lager032_icon( 'phone' ); ?>
				<a href="tel:+38132342281">+381 32 342 281</a>
			</li>
			<li><a href="tel:+381631093199">+381 63 109 31 99</a></li>
			<li>
				<?php lager032_icon( 'mail' ); ?>
				<a href="mailto:lager032@gmail.com">LAGER032@GMAIL.COM</a>
			</li>
			<li>
				<?php lager032_icon( 'pin' ); ?>
				<span>"LAGER" STR, KNEZA MILOŠA 100, ČAČAK</span>
			</li>
		</ul>
		<div class="topbar__actions">
			<a class="topbar__icon" href="<?php echo esc_url( home_url( '/?s=' ) ); ?>" aria-label="<?php esc_attr_e( 'Pretraga', 'lager032' ); ?>">
				<?php lager032_icon( 'search' ); ?>
			</a>
			<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
				<a class="topbar__icon" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Korpa', 'lager032' ); ?>">
					<?php lager032_icon( 'cart' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Main header: logo + nav + shop CTA -->
<header class="site-header">
	<div class="container site-header__inner">

		<div class="site-branding">
			<?php
			if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) {
				the_custom_logo();
			} elseif ( file_exists( get_template_directory() . '/assets/img/logo.png' ) ) {
				printf(
					'<a class="site-logo" href="%1$s" rel="home"><img src="%2$s" alt="%3$s"></a>',
					esc_url( home_url( '/' ) ),
					esc_url( get_template_directory_uri() . '/assets/img/logo.png' ),
					esc_attr( get_bloginfo( 'name' ) )
				);
			} else {
				printf(
					'<a class="site-logo-text" href="%1$s"><span>LA</span><strong>G</strong><span>ER</span></a>',
					esc_url( home_url( '/' ) )
				);
			}
			?>
		</div>

		<nav class="main-nav" aria-label="<?php esc_attr_e( 'Glavni meni', 'lager032' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'main-nav__list',
					'depth'          => 2,
				) );
			} else {
				// Placeholder until the menu is assigned in Appearance → Menus.
				echo '<ul class="main-nav__list"><li><a href="#">O NAMA</a></li><li><a href="#">KATALOG</a></li><li><a href="#">SERTIFIKATI</a></li></ul>';
			}
			?>
		</nav>

		<a class="btn btn--shop" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' ) ); ?>">
			<?php esc_html_e( 'PRODAVNICA', 'lager032' ); ?>
		</a>

		<button class="nav-toggle" aria-label="<?php esc_attr_e( 'Meni', 'lager032' ); ?>" aria-expanded="false">
			<span></span><span></span><span></span>
		</button>
	</div>
</header>

<main id="content" class="site-content">
