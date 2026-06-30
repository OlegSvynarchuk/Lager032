<?php
/**
 * "Katalog" page — embedded PDF catalog viewer + download.
 *
 * Auto-applied to the page with slug `katalog` (template hierarchy: page-{slug}.php).
 * The PDF lives in wp-content/uploads so it can be replaced without a theme deploy.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$pdf_file = 'katalog-2022-2023.pdf';
$uploads  = wp_get_upload_dir();
$pdf_url  = trailingslashit( $uploads['baseurl'] ) . $pdf_file;
$pdf_path = trailingslashit( $uploads['basedir'] ) . $pdf_file;
$has_pdf  = file_exists( $pdf_path );
?>

<section class="katalog">
	<div class="container">

		<div class="katalog__head">
			<span class="katalog__icon" aria-hidden="true"><?php lager032_icon( 'download' ); ?></span>
			<div class="katalog__headtext">
				<h1 class="katalog__title"><?php esc_html_e( 'Preuzmite katalog 2022/2023', 'lager032' ); ?></h1>
				<p class="katalog__sub"><?php esc_html_e( 'Kompletan asortiman ležajeva i industrijske opreme u PDF formatu.', 'lager032' ); ?></p>
			</div>
			<?php if ( $has_pdf ) : ?>
				<a class="btn btn--red katalog__btn" href="<?php echo esc_url( $pdf_url ); ?>" download>
					<?php lager032_icon( 'download' ); ?> <span><?php esc_html_e( 'Preuzmi PDF', 'lager032' ); ?></span>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( $has_pdf ) : ?>
			<div class="katalog__viewer">
				<iframe src="<?php echo esc_url( $pdf_url ); ?>#view=FitH" title="<?php esc_attr_e( 'Katalog 2022/2023', 'lager032' ); ?>" loading="lazy"></iframe>
			</div>
			<p class="katalog__fallback">
				<?php esc_html_e( 'Ako se katalog ne prikazuje, ', 'lager032' ); ?>
				<a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'otvorite ga u novom prozoru', 'lager032' ); ?></a>.
			</p>
		<?php else : ?>
			<p class="katalog__fallback"><?php esc_html_e( 'Katalog trenutno nije dostupan. Pokušajte ponovo kasnije.', 'lager032' ); ?></p>
		<?php endif; ?>

	</div>
</section>

<?php
get_footer();
