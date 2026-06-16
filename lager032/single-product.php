<?php
/**
 * Single product — data-first (no per-product photos; category image used as an
 * illustrative reference). Figma 47:2291 adapted; eecart-style data emphasis.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$product = wc_get_product( get_the_ID() );
	if ( ! $product ) {
		continue;
	}
	$pid      = get_the_ID();
	$sku      = $product->get_sku();
	$net      = $product->get_regular_price();
	$instock  = $product->is_in_stock();
	$stock_q  = $product->get_stock_quantity();
	$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' );

	$pcats    = wp_get_post_terms( $pid, 'product_cat' );
	$cat      = ( $pcats && ! is_wp_error( $pcats ) ) ? $pcats[0] : null;
	$brands   = wp_get_post_terms( $pid, 'product_brand', array( 'fields' => 'names' ) );
	$brand    = ( $brands && ! is_wp_error( $brands ) ) ? $brands[0] : '';

	// Illustrative visual: category image → shared placeholder → Woo placeholder.
	$vis_id = $cat ? (int) get_term_meta( $cat->term_id, 'thumbnail_id', true ) : 0;
	if ( ! $vis_id ) {
		$vis_id = (int) get_option( 'lager_cat_placeholder_id' );
	}
	$vis_src = $vis_id ? wp_get_attachment_image_url( $vis_id, 'large' ) : wc_placeholder_img_src( 'large' );
	?>

	<section class="single">
		<div class="container">

			<nav class="crumbs" aria-label="breadcrumb">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Početna', 'lager032' ); ?></a>
				<span>/</span>
				<a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Proizvodi', 'lager032' ); ?></a>
				<?php if ( $cat ) : ?>
					<span>/</span>
					<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
				<?php endif; ?>
				<span>/</span>
				<span class="crumbs__current"><?php the_title(); ?></span>
			</nav>

			<div class="single__top">
				<!-- Illustrative visual -->
				<div class="single__media">
					<img src="<?php echo esc_url( $vis_src ); ?>" alt="<?php echo esc_attr( $cat ? $cat->name : get_the_title() ); ?>" loading="lazy">
					<span class="single__refnote"><?php esc_html_e( 'Slika je ilustrativna (prikaz kategorije).', 'lager032' ); ?></span>
				</div>

				<!-- Buy box / data -->
				<div class="single__buy">
					<?php if ( $cat ) : ?>
						<a class="single__cat" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
					<?php endif; ?>
					<h1 class="single__title"><?php the_title(); ?></h1>

					<div class="single__meta">
						<?php if ( $sku ) : ?><span><?php esc_html_e( 'Šifra:', 'lager032' ); ?> <strong><?php echo esc_html( $sku ); ?></strong></span><?php endif; ?>
						<?php if ( $brand ) : ?><span><?php esc_html_e( 'Proizvođač:', 'lager032' ); ?> <strong><?php echo esc_html( $brand ); ?></strong></span><?php endif; ?>
					</div>

					<div class="single__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>

					<div class="single__stock <?php echo $instock ? 'is-in' : 'is-out'; ?>">
						<?php lager032_icon( $instock ? 'check' : 'box' ); ?>
						<?php
						if ( $instock ) {
							echo esc_html( null !== $stock_q ? sprintf( __( 'Na stanju (%s kom)', 'lager032' ), $stock_q ) : __( 'Na stanju', 'lager032' ) );
						} else {
							esc_html_e( 'Nema na stanju', 'lager032' );
						}
						?>
					</div>

					<?php if ( $instock ) : ?>
						<form class="addcart" method="post" enctype="multipart/form-data">
							<div class="qty">
								<button type="button" class="qty__btn" data-dir="-1" aria-label="−">−</button>
								<input type="number" name="quantity" value="1" min="1" inputmode="numeric">
								<button type="button" class="qty__btn" data-dir="1" aria-label="+">+</button>
							</div>
							<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $pid ); ?>" class="btn btn--navy"><?php lager032_icon( 'cart' ); ?> <?php esc_html_e( 'Dodaj u korpu', 'lager032' ); ?></button>
						</form>
					<?php endif; ?>

					<a class="single__ask" href="#kontakt"><?php esc_html_e( 'Pozovite za upit i dostupnost', 'lager032' ); ?></a>
					<p class="single__hint"><?php esc_html_e( 'Za upit navedite šifru artikla — naš tim potvrđuje dostupnost i dimenzije.', 'lager032' ); ?></p>
				</div>
			</div>

			<!-- Specs (grows as data improves: dimensions, tip, zaptivanje…) -->
			<div class="single__specs">
				<h2 class="single__h2"><?php esc_html_e( 'Specifikacija', 'lager032' ); ?></h2>
				<table class="spectable">
					<tbody>
						<?php if ( $cat ) : ?><tr><th><?php esc_html_e( 'Kategorija', 'lager032' ); ?></th><td><?php echo esc_html( $cat->name ); ?></td></tr><?php endif; ?>
						<?php if ( $sku ) : ?><tr><th><?php esc_html_e( 'Šifra', 'lager032' ); ?></th><td><?php echo esc_html( $sku ); ?></td></tr><?php endif; ?>
						<?php if ( $brand ) : ?><tr><th><?php esc_html_e( 'Proizvođač', 'lager032' ); ?></th><td><?php echo esc_html( $brand ); ?></td></tr><?php endif; ?>
						<tr><th><?php esc_html_e( 'Stanje', 'lager032' ); ?></th><td><?php echo $instock ? esc_html( null !== $stock_q ? $stock_q . ' kom' : __( 'Na stanju', 'lager032' ) ) : esc_html__( 'Nema na stanju', 'lager032' ); ?></td></tr>
					</tbody>
				</table>
			</div>

			<!-- Slični proizvodi -->
			<?php
			if ( $cat ) {
				$related = wc_get_products( array(
					'status'   => 'publish',
					'limit'    => 4,
					'category' => array( $cat->slug ),
					'exclude'  => array( $pid ),
					'orderby'  => 'rand',
				) );
				if ( $related ) {
					echo '<div class="single__related"><h2 class="single__h2">' . esc_html__( 'Slični proizvodi', 'lager032' ) . '</h2><div class="relgrid">';
					foreach ( $related as $rp ) {
						$rid = $rp->get_id();
						printf(
							'<a class="relcard" href="%1$s"><span class="relcard__media">%2$s</span><span class="relcard__name">%3$s</span><span class="relcard__price">%4$s</span></a>',
							esc_url( get_permalink( $rid ) ),
							$rp->get_image( 'woocommerce_thumbnail' ), // phpcs:ignore
							esc_html( $rp->get_name() ),
							wp_kses_post( $rp->get_price_html() )
						);
					}
					echo '</div></div>';
				}
			}
			?>

		</div>
	</section>

	<?php get_template_part( 'template-parts/contact' ); ?>

	<?php
	// ---- SEO: Product + BreadcrumbList JSON-LD ----
	$ld_crumbs = array(
		array( 'name' => 'Početna', 'url' => home_url( '/' ) ),
		array( 'name' => 'Proizvodi', 'url' => $shop_url ),
	);
	if ( $cat ) {
		$ld_crumbs[] = array( 'name' => $cat->name, 'url' => get_term_link( $cat ) );
	}
	$ld_crumbs[] = array( 'name' => get_the_title(), 'url' => get_permalink() );
	$crumb_items = array();
	foreach ( $ld_crumbs as $idx => $c ) {
		$crumb_items[] = array(
			'@type'    => 'ListItem',
			'position' => $idx + 1,
			'name'     => $c['name'],
			'item'     => $c['url'],
		);
	}
	$product_ld = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Product',
		'name'        => wp_strip_all_tags( get_the_title() ),
		'sku'         => $sku,
		'category'    => $cat ? $cat->name : '',
		'brand'       => $brand ? array( '@type' => 'Brand', 'name' => $brand ) : null,
		'offers'      => array(
			'@type'         => 'Offer',
			'price'         => wc_get_price_including_tax( $product ),
			'priceCurrency' => get_woocommerce_currency(),
			'availability'  => $instock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
			'url'           => get_permalink(),
		),
	);
	$breadcrumb_ld = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $crumb_items,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( array_filter( $product_ld ) ) . '</script>';
	echo '<script type="application/ld+json">' . wp_json_encode( $breadcrumb_ld ) . '</script>';
	?>

	<?php
endwhile;

get_footer();
