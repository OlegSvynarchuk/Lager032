<?php
/**
 * "O nama" page (Figma node 234:2793).
 * Reuses the homepage hero (different background image) + KPI band, then two
 * alternating About blocks (image left, then image right).
 *
 * Auto-applied to the page with slug `o-nama` (template hierarchy: page-{slug}.php).
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

// Shared About content (same block, rendered twice with the image side alternating).
$about_lead     = __( '„LAGER" STR je specijalizovana prodavnica ležajeva i industrijske opreme sa sedištem u Čačku. Isporučujemo ležajeve, semerinze, remenje, lančane prenose i drugu opremu od vodećih svetskih proizvođača — FAG, SKF, Timken, SNR, ZVL i mnogi drugi. Nudimo povoljne cene, tehničku podršku i brzu dostavu.', 'lager032' );
$about_features = array(
	array( 'clock', '25 godina iskustva', 'Od 1999. godine pouzdano snabdevamo industriju Srbije ležajevima i mehaničkim delovima.' ),
	array( 'check', 'Stručna podrška', 'Naš tim tehničkih stručnjaka pomaže Vam pri odabiru pravog rešenja za Vaše aplikacije.' ),
	array( 'box', '5.000+ artikala', 'Bogat magacinski stok od preko 5.000 artikala — gotovinska i predračunska kupovina.' ),
	array( 'truck', 'Lokalna dostupnost', 'Lično preuzimanje u Čačku ili brza dostava kurirskom službom širom Srbije.' ),
);
$about_img     = $home_img . '/about-magacin.jpg?v=' . filemtime( get_template_directory() . '/assets/img/home/about-magacin.jpg' );

/**
 * Render one About block.
 *
 * @param bool   $reverse  Image on the right when true.
 * @param string $img      Image URL.
 * @param string $lead     Lead paragraph.
 * @param array  $features Feature rows [ icon, title, text ].
 */
$render_about = function ( $reverse, $img, $lead, $features ) {
	?>
	<section class="about<?php echo $reverse ? ' about--reverse' : ''; ?>">
		<div class="container about__inner">
			<div class="about__media">
				<img src="<?php echo esc_url( $img ); ?>" alt="<?php esc_attr_e( 'LAGER magacin', 'lager032' ); ?>" loading="lazy">
			</div>
			<div class="about__content">
				<span class="sec-eyebrow"><?php esc_html_e( 'O Nama', 'lager032' ); ?></span>
				<h2 class="sec-title"><?php esc_html_e( 'Vaš partner u industrijskoj nabavci', 'lager032' ); ?></h2>
				<p class="about__lead"><?php echo esc_html( $lead ); ?></p>
				<div class="about__features">
					<?php
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
	<?php
};
?>

<!-- ============================ HERO ============================ -->
<section class="hero" style="background-image:url('<?php echo esc_url( $home_img . '/about-magacin.jpg?v=' . filemtime( get_template_directory() . '/assets/img/home/about-magacin.jpg' ) ); ?>')">
	<div class="hero__overlay" aria-hidden="true"></div>
	<div class="container hero__inner">
		<div class="hero__content">
			<span class="hero__eyebrow"><?php esc_html_e( 'O nama', 'lager032' ); ?></span>
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

<!-- HERO KPI band -->
<div class="hero__stats">
	<div class="container hero__stats-inner">
		<?php
		$stats = array(
			array( 'clock', '25+', 'Godina iskustva' ),
			array( 'box', '5.000+', 'Artikala na stanju' ),
			array( 'truck', 'Brza', 'Isporuka kurirskom službom' ),
			array( 'check', '14+', 'Sertifikovanih brendova' ),
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

<!-- ============================ O NAMA ============================ -->
<?php
$render_about( false, $about_img, $about_lead, $about_features );
$render_about( true, $about_img, $about_lead, $about_features );

get_footer();
