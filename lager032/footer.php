<?php
/**
 * Footer (simple — full footer design from Figma still to be built).
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main><!-- #content -->

<footer class="site-footer">
	<div class="container site-footer__inner">

		<div class="site-footer__col">
			<h3><?php esc_html_e( 'Kontakt', 'lager032' ); ?></h3>
			<ul>
				<li><a href="tel:+38132342281">+381 32 342 281</a></li>
				<li><a href="tel:+381631093199">+381 63 109 31 99</a></li>
				<li><a href="mailto:lager032@gmail.com">lager032@gmail.com</a></li>
				<li>"LAGER" STR, Kneza Miloša 100, Čačak</li>
			</ul>
		</div>

		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<div class="site-footer__col">
				<h3><?php esc_html_e( 'Navigacija', 'lager032' ); ?></h3>
				<?php
				wp_nav_menu( array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => '',
					'depth'          => 1,
				) );
				?>
			</div>
		<?php endif; ?>

	</div>

	<div class="site-footer__bottom container">
		<?php
		printf(
			/* translators: %s: current year. */
			esc_html__( '© %s LAGER STR. Sva prava zadržana.', 'lager032' ),
			esc_html( wp_date( 'Y' ) )
		);
		?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
