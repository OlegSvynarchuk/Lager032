<?php
/**
 * Single category guide (cat_guide) — hero (image + title + subtitle) → editor
 * body → "view products" CTA. Same chrome/styles as the rest of the site.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$pid = get_the_ID();

	$hero_id = function_exists( 'get_field' ) ? (int) get_field( 'hero_image', $pid ) : 0;
	if ( ! $hero_id ) {
		$hero_id = get_post_thumbnail_id( $pid );
	}
	$hero = $hero_id ? wp_get_attachment_image_url( $hero_id, 'full' ) : '';
	$sub  = function_exists( 'get_field' ) ? (string) get_field( 'podnaslov', $pid ) : '';

	$tid = function_exists( 'get_field' ) ? get_field( 'povezana_kategorija', $pid ) : 0;
	if ( is_array( $tid ) ) {
		$tid = reset( $tid );
	}
	$tid  = (int) $tid;
	$term = $tid ? get_term( $tid, 'product_cat' ) : null;
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' );
	$cat_url = ( $term && ! is_wp_error( $term ) ) ? get_term_link( $term ) : $shop;
	if ( is_wp_error( $cat_url ) ) {
		$cat_url = $shop;
	}
	?>

	<section class="guidehero"<?php echo $hero ? ' style="background-image:url(\'' . esc_url( $hero ) . '\')"' : ''; ?>>
		<div class="guidehero__overlay" aria-hidden="true"></div>
		<div class="container guidehero__inner">
			<nav class="crumbs crumbs--light" aria-label="<?php esc_attr_e( 'Putanja', 'lager032' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Početna', 'lager032' ); ?></a>
				<span aria-hidden="true">/</span>
				<?php if ( $term && ! is_wp_error( $term ) ) : ?>
					<a href="<?php echo esc_url( $cat_url ); ?>"><?php echo esc_html( $term->name ); ?></a>
					<span aria-hidden="true">/</span>
				<?php endif; ?>
				<span class="crumbs__current"><?php the_title(); ?></span>
			</nav>
			<h1 class="guidehero__title"><?php the_title(); ?></h1>
			<?php if ( $sub ) : ?><p class="guidehero__sub"><?php echo esc_html( $sub ); ?></p><?php endif; ?>
			<a class="btn btn--red guidehero__cta" href="<?php echo esc_url( $cat_url ); ?>"><?php esc_html_e( 'Pogledaj sve proizvode', 'lager032' ); ?> <?php lager032_icon( 'arrow' ); ?></a>
		</div>
	</section>

	<section class="guide">
		<div class="container guide__inner">
			<div class="entry-content">
				<?php
				$body = function_exists( 'get_field' ) ? (string) get_field( 'sadrzaj', $pid ) : '';
				if ( '' === trim( $body ) ) {
					$body = apply_filters( 'the_content', get_post_field( 'post_content', $pid ) ); // fallback to legacy editor content.
				}
				echo wp_kses_post( $body );
				?>
			</div>
			<?php
			$gallery = array();
			for ( $gi = 1; $gi <= 3; $gi++ ) {
				$img = function_exists( 'get_field' ) ? (int) get_field( 'slika_' . $gi, $pid ) : 0;
				if ( $img ) {
					$gallery[] = $img;
				}
			}
			if ( $gallery ) {
				echo '<div class="guide__gallery">';
				foreach ( $gallery as $img_id ) {
					echo wp_get_attachment_image( $img_id, 'large', false, array( 'class' => 'guide__galimg', 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</div>';
			}
			?>
			<div class="guide__foot">
				<a class="btn btn--navy" href="<?php echo esc_url( $cat_url ); ?>"><?php lager032_icon( 'grid' ); ?> <span><?php esc_html_e( 'Pogledaj sve proizvode', 'lager032' ); ?></span></a>
			</div>
		</div>
	</section>

	<?php
endwhile;

get_footer();
