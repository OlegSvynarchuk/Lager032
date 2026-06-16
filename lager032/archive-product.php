<?php
/**
 * Product archive — faceted list view (shop + product_cat + product_brand).
 * Figma node 106:2027. Search-first, dense rows, left filter sidebar.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$is_cat       = is_tax( 'product_cat' );
$is_brand     = is_tax( 'product_brand' );
$current_term = ( $is_cat || $is_brand ) ? get_queried_object() : null;
$page_title   = $current_term ? $current_term->name : __( 'Svi proizvodi', 'lager032' );

// ---- Read facet state from the query string ----
$sel_cats   = isset( $_GET['fcat'] ) ? array_map( 'sanitize_title', (array) wp_unslash( $_GET['fcat'] ) ) : array();
$sel_brands = isset( $_GET['fbrand'] ) ? array_map( 'sanitize_title', (array) wp_unslash( $_GET['fbrand'] ) ) : array();
$instock    = ! empty( $_GET['instock'] );
$min_price  = ( isset( $_GET['min_price'] ) && '' !== $_GET['min_price'] ) ? floatval( $_GET['min_price'] ) : '';
$max_price  = ( isset( $_GET['max_price'] ) && '' !== $_GET['max_price'] ) ? floatval( $_GET['max_price'] ) : '';
$search     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$orderby    = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'date';
$paged      = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

// ---- Build the query ----
$tax_query = array(
	'relation' => 'AND',
	array(
		'taxonomy' => 'product_visibility',
		'field'    => 'name',
		'terms'    => array( 'exclude-from-catalog' ),
		'operator' => 'NOT IN',
	),
);
if ( $is_cat ) {
	$tax_query[] = array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array( $current_term->slug ), 'include_children' => true );
}
if ( $is_brand ) {
	$tax_query[] = array( 'taxonomy' => 'product_brand', 'field' => 'slug', 'terms' => array( $current_term->slug ) );
}
if ( $sel_cats ) {
	$tax_query[] = array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $sel_cats );
}
if ( $sel_brands ) {
	$tax_query[] = array( 'taxonomy' => 'product_brand', 'field' => 'slug', 'terms' => $sel_brands );
}

$meta_query = array();
if ( $instock ) {
	$meta_query[] = array( 'key' => '_stock_status', 'value' => 'instock' );
}
if ( '' !== $min_price || '' !== $max_price ) {
	$pq = array( 'key' => '_price', 'type' => 'NUMERIC' );
	if ( '' !== $min_price && '' !== $max_price ) {
		$pq['compare'] = 'BETWEEN';
		$pq['value']   = array( $min_price, $max_price );
	} elseif ( '' !== $min_price ) {
		$pq['compare'] = '>=';
		$pq['value']   = $min_price;
	} else {
		$pq['compare'] = '<=';
		$pq['value']   = $max_price;
	}
	$meta_query[] = $pq;
}

$args = array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => 12,
	'paged'          => $paged,
	'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery
	'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
);
if ( $search ) {
	$args['s'] = $search;
}
switch ( $orderby ) {
	case 'price':
		$args['orderby']  = 'meta_value_num';
		$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery
		$args['order']    = 'ASC';
		break;
	case 'price-desc':
		$args['orderby']  = 'meta_value_num';
		$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery
		$args['order']    = 'DESC';
		break;
	case 'title':
		$args['orderby'] = 'title';
		$args['order']   = 'ASC';
		break;
	default:
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';
}
$q = new WP_Query( $args );

$total    = (int) $q->found_posts;
$per      = 12;
$from     = $total ? ( ( $paged - 1 ) * $per ) + 1 : 0;
$to       = min( $paged * $per, $total );
$base_url = $current_term ? get_term_link( $current_term ) : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' ) );
?>

<section class="archive">
	<div class="container">
		<div class="archive__head">
			<span class="sec-eyebrow"><?php esc_html_e( 'Katalog', 'lager032' ); ?></span>
			<h1 class="archive__title"><?php echo esc_html( $page_title ); ?></h1>
		</div>

		<div class="archive__layout">

			<!-- FILTERS -->
			<aside class="filters">
				<form class="filters__form" method="get" action="<?php echo esc_url( $base_url ); ?>">
					<div class="filters__top"><?php lager032_icon( 'grid' ); ?><span><?php esc_html_e( 'Filteri', 'lager032' ); ?></span></div>

					<div class="filters__search">
						<?php lager032_icon( 'search' ); ?>
						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Pretraži artikle...', 'lager032' ); ?>">
					</div>

					<details class="fsec" open>
						<summary><?php esc_html_e( 'Dostupnost', 'lager032' ); ?><?php lager032_icon( 'chevron' ); ?></summary>
						<label class="fopt"><input type="checkbox" name="instock" value="1" <?php checked( $instock ); ?>> <span><?php esc_html_e( 'Samo na stanju', 'lager032' ); ?></span></label>
					</details>

					<?php
					$cat_terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0 ) );
					if ( ! is_wp_error( $cat_terms ) && $cat_terms ) :
						?>
						<details class="fsec" open>
							<summary><?php esc_html_e( 'Kategorija', 'lager032' ); ?><?php lager032_icon( 'chevron' ); ?></summary>
							<?php
							foreach ( $cat_terms as $t ) {
								if ( 'uncategorized' === $t->slug ) {
									continue;
								}
								printf(
									'<label class="fopt"><span><input type="checkbox" name="fcat[]" value="%1$s" %2$s> %3$s</span><em>(%4$d)</em></label>',
									esc_attr( $t->slug ),
									checked( in_array( $t->slug, $sel_cats, true ), true, false ),
									esc_html( $t->name ),
									(int) $t->count
								);
							}
							?>
						</details>
					<?php endif; ?>

					<?php
					$brand_terms = get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => false ) );
					if ( ! is_wp_error( $brand_terms ) && $brand_terms ) :
						?>
						<details class="fsec" open>
							<summary><?php esc_html_e( 'Proizvođač', 'lager032' ); ?><?php lager032_icon( 'chevron' ); ?></summary>
							<?php
							foreach ( $brand_terms as $t ) {
								printf(
									'<label class="fopt"><span><input type="checkbox" name="fbrand[]" value="%1$s" %2$s> %3$s</span><em>(%4$d)</em></label>',
									esc_attr( $t->slug ),
									checked( in_array( $t->slug, $sel_brands, true ), true, false ),
									esc_html( $t->name ),
									(int) $t->count
								);
							}
							?>
						</details>
					<?php endif; ?>

					<details class="fsec" open>
						<summary><?php esc_html_e( 'Cena (RSD)', 'lager032' ); ?><?php lager032_icon( 'chevron' ); ?></summary>
						<div class="fprice">
							<label><?php esc_html_e( 'Od', 'lager032' ); ?><input type="number" name="min_price" min="0" value="<?php echo esc_attr( $min_price ); ?>"></label>
							<label><?php esc_html_e( 'Do', 'lager032' ); ?><input type="number" name="max_price" min="0" value="<?php echo esc_attr( $max_price ); ?>"></label>
						</div>
					</details>

					<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
					<button type="submit" class="btn btn--navy btn--block filters__apply"><?php esc_html_e( 'Primeni filtere', 'lager032' ); ?></button>
					<?php if ( $sel_cats || $sel_brands || $instock || '' !== $min_price || '' !== $max_price || $search ) : ?>
						<a class="filters__reset" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Poništi filtere', 'lager032' ); ?></a>
					<?php endif; ?>
				</form>
			</aside>

			<!-- RESULTS -->
			<div class="results">
				<div class="results__bar">
					<span class="results__count">
						<?php
						/* translators: 1: from, 2: to, 3: total. */
						printf( esc_html__( '%1$d–%2$d od %3$d artikala', 'lager032' ), (int) $from, (int) $to, (int) $total );
						?>
					</span>
					<label class="results__sort">
						<?php esc_html_e( 'Sortiraj:', 'lager032' ); ?>
						<select onchange="this.form.submit()" name="orderby" form="sortform">
							<option value="date" <?php selected( $orderby, 'date' ); ?>><?php esc_html_e( 'Najnovije', 'lager032' ); ?></option>
							<option value="price" <?php selected( $orderby, 'price' ); ?>><?php esc_html_e( 'Cena: rastuće', 'lager032' ); ?></option>
							<option value="price-desc" <?php selected( $orderby, 'price-desc' ); ?>><?php esc_html_e( 'Cena: opadajuće', 'lager032' ); ?></option>
							<option value="title" <?php selected( $orderby, 'title' ); ?>><?php esc_html_e( 'Naziv: A–Z', 'lager032' ); ?></option>
						</select>
					</label>
					<?php // Sort form carries current filters so changing sort keeps them. ?>
					<form id="sortform" method="get" action="<?php echo esc_url( $base_url ); ?>" hidden>
						<?php
						if ( $search ) {
							echo '<input type="hidden" name="s" value="' . esc_attr( $search ) . '">';
						}
						foreach ( $sel_cats as $c ) {
							echo '<input type="hidden" name="fcat[]" value="' . esc_attr( $c ) . '">';
						}
						foreach ( $sel_brands as $b ) {
							echo '<input type="hidden" name="fbrand[]" value="' . esc_attr( $b ) . '">';
						}
						if ( $instock ) {
							echo '<input type="hidden" name="instock" value="1">';
						}
						if ( '' !== $min_price ) {
							echo '<input type="hidden" name="min_price" value="' . esc_attr( $min_price ) . '">';
						}
						if ( '' !== $max_price ) {
							echo '<input type="hidden" name="max_price" value="' . esc_attr( $max_price ) . '">';
						}
						?>
					</form>
				</div>

				<?php if ( $q->have_posts() ) : ?>
					<div class="plist">
						<?php
						while ( $q->have_posts() ) :
							$q->the_post();
							$product = wc_get_product( get_the_ID() );
							if ( ! $product ) {
								continue;
							}
							$brands   = wp_get_post_terms( get_the_ID(), 'product_brand', array( 'fields' => 'names' ) );
							$cats     = wp_get_post_terms( get_the_ID(), 'product_cat', array( 'fields' => 'names' ) );
							$brand    = ( $brands && ! is_wp_error( $brands ) ) ? $brands[0] : ( ( $cats && ! is_wp_error( $cats ) ) ? $cats[0] : '' );
							$sku      = $product->get_sku();
							$instockp = $product->is_in_stock();
							?>
							<article class="prow">
								<a class="prow__media" href="<?php the_permalink(); ?>"><?php echo $product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore ?></a>
								<div class="prow__main">
									<?php if ( $brand || $sku ) : ?><span class="prow__tag"><?php echo esc_html( trim( $brand . ( $sku ? ' · ' . $sku : '' ), ' ·' ) ); ?></span><?php endif; ?>
									<h3 class="prow__name"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
									<?php
									$short = $product->get_short_description();
									if ( $short ) {
										echo '<p class="prow__desc">' . esc_html( wp_trim_words( wp_strip_all_tags( $short ), 22 ) ) . '</p>';
									}
									?>
								</div>
								<div class="prow__side">
									<span class="prow__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
									<span class="prow__stock <?php echo $instockp ? 'is-in' : 'is-out'; ?>"><?php echo $instockp ? esc_html__( 'Na stanju', 'lager032' ) : esc_html__( 'Nema na stanju', 'lager032' ); ?></span>
									<a class="btn btn--navy btn--sm" href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"><?php lager032_icon( 'cart' ); ?> <?php esc_html_e( 'Dodaj u korpu', 'lager032' ); ?></a>
								</div>
							</article>
							<?php
						endwhile;
						wp_reset_postdata();
						?>
					</div>

					<?php
					$pag = paginate_links( array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $q->max_num_pages,
						'prev_text' => '‹',
						'next_text' => '›',
						'type'      => 'list',
					) );
					if ( $pag ) {
						echo '<nav class="archive__pager">' . wp_kses_post( $pag ) . '</nav>';
					}
					?>
				<?php else : ?>
					<div class="results__empty">
						<p><?php esc_html_e( 'Nema proizvoda koji odgovaraju izabranim filterima.', 'lager032' ); ?></p>
						<p class="results__empty-sub"><?php esc_html_e( 'Katalog se uskoro objavljuje — proizvodi su trenutno u pripremi.', 'lager032' ); ?></p>
					</div>
				<?php endif; ?>
			</div>

		</div>
	</div>
</section>

<?php
get_footer();
