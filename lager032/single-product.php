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

	// Illustrative visual: subcategory image → parent category → any category → placeholder.
	$vis_id  = lager_product_category_image_id( $pid );
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
					<?php if ( $cat ) : ?><a class="single__cat" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a><?php endif; ?>
					<h1 class="single__title"><?php the_title(); ?></h1>

					<div class="single__price"><?php echo wp_kses_post( $product->get_price_html() ); ?><small class="price-pdv"><?php esc_html_e( 'sa PDV-om', 'lager032' ); ?></small></div>

					<div class="single__meta">
						<?php if ( $sku ) : ?><p><span class="single__meta-k"><?php esc_html_e( 'Šifra', 'lager032' ); ?></span> <span class="single__meta-v"><?php echo esc_html( $sku ); ?></span></p><?php endif; ?>
						<?php if ( $brand ) : ?><p><span class="single__meta-k"><?php esc_html_e( 'Proizvođač', 'lager032' ); ?></span> <span class="single__meta-v"><?php echo esc_html( $brand ); ?></span></p><?php endif; ?>
						<p><span class="single__meta-k"><?php esc_html_e( 'Dostupnost', 'lager032' ); ?></span> <span class="single__meta-v <?php echo $instock ? 'is-in' : 'is-out'; ?>"><?php echo $instock ? esc_html__( 'Na stanju', 'lager032' ) : esc_html__( 'Nema na stanju', 'lager032' ); ?></span></p>
					</div>

					<?php if ( $instock ) : ?>
						<form class="addcart" method="post" enctype="multipart/form-data">
							<div class="qty">
								<button type="button" class="qty__btn" data-dir="-1" aria-label="−">−</button>
								<input type="number" name="quantity" value="1" min="1" inputmode="numeric">
								<button type="button" class="qty__btn" data-dir="1" aria-label="+">+</button>
							</div>
							<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $pid ); ?>" class="btn btn--navy single__add" data-id="<?php echo esc_attr( $pid ); ?>"><?php lager032_icon( 'cart' ); ?> <span class="single__add-label"><?php esc_html_e( 'Dodaj u korpu', 'lager032' ); ?></span></button>
								<button type="button" class="single__remove" data-id="<?php echo esc_attr( $pid ); ?>" hidden aria-label="<?php esc_attr_e( 'Ukloni iz korpe', 'lager032' ); ?>"><?php lager032_icon( 'trash' ); ?></button>
						</form>
					<?php endif; ?>

					<p class="single__note"><?php esc_html_e( 'Isporuke vršimo brzom poštom ili preuzimanjem u našem magacinu.', 'lager032' ); ?></p>
				</div>
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
						$rid      = $rp->get_id();
						$rlink    = get_permalink( $rid );
						$rel_img  = lager_product_category_image_id( $rid );
						$rel_src  = $rel_img ? wp_get_attachment_image( $rel_img, 'woocommerce_thumbnail' ) : '<img src="' . esc_url( wc_placeholder_img_src( 'woocommerce_thumbnail' ) ) . '" alt="">';
						$r_badge  = function_exists( 'lager_product_primary_category_name' ) ? lager_product_primary_category_name( $rid ) : '';
						$r_title  = function_exists( 'lager_extract_brand' ) ? lager_extract_brand( $rp->get_name() )['title'] : $rp->get_name();
						$r_instk  = $rp->is_in_stock();
						?>
						<div class="relcard">
							<a class="relcard__media" href="<?php echo esc_url( $rlink ); ?>">
								<?php echo $rel_src; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</a>
							<div class="relcard__body">
								<a class="relcard__name" href="<?php echo esc_url( $rlink ); ?>"><?php echo esc_html( $r_title ); ?></a>
								<?php if ( $r_badge ) : ?><span class="relcard__cat"><?php echo esc_html( $r_badge ); ?></span><?php endif; ?>
								<span class="relcard__stock <?php echo $r_instk ? 'is-in' : 'is-out'; ?>"><?php echo $r_instk ? esc_html__( 'Na stanju', 'lager032' ) : esc_html__( 'Nema na stanju', 'lager032' ); ?></span>
								<div class="relcard__foot">
									<span class="relcard__price"><?php echo wp_kses_post( $rp->get_price_html() ); ?><small class="price-pdv"><?php esc_html_e( 'sa PDV-om', 'lager032' ); ?></small></span>
									<?php if ( $r_instk ) : ?>
									<button type="button" class="btn btn--navy btn--sm prow__add relcard__add" data-id="<?php echo esc_attr( $rid ); ?>"><?php lager032_icon( 'cart' ); ?> <span><?php esc_html_e( 'Dodaj', 'lager032' ); ?></span></button>
									<?php endif; ?>
								</div>
							</div>
						</div>
						<?php
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
