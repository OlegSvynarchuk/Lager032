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

// ---- Active-filter chips: one chip per active filter, each linking to the same view with
// that single filter removed. `$active` holds the full current state; orderby is kept in the
// state (so removing a chip preserves the sort) but is not itself shown as a removable chip.
$active = array();
if ( $search ) {
	$active['s'] = $search;
}
if ( $sel_cats ) {
	$active['fcat'] = $sel_cats;
}
if ( $sel_brands ) {
	$active['fbrand'] = $sel_brands;
}
if ( $instock ) {
	$active['instock'] = '1';
}
if ( '' !== $min_price ) {
	$active['min_price'] = $min_price;
}
if ( '' !== $max_price ) {
	$active['max_price'] = $max_price;
}
if ( 'date' !== $orderby ) {
	$active['orderby'] = $orderby;
}

$chip_url = function ( $params ) use ( $base_url ) {
	return $params ? add_query_arg( $params, $base_url ) : $base_url;
};

$chips = array();
foreach ( $sel_cats as $slug ) {
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	$rest = array_values( array_diff( $sel_cats, array( $slug ) ) );
	$p    = $active;
	if ( $rest ) {
		$p['fcat'] = $rest;
	} else {
		unset( $p['fcat'] );
	}
	$chips[] = array( 'label' => $term ? $term->name : $slug, 'url' => $chip_url( $p ) );
}
foreach ( $sel_brands as $slug ) {
	$term = get_term_by( 'slug', $slug, 'product_brand' );
	$rest = array_values( array_diff( $sel_brands, array( $slug ) ) );
	$p    = $active;
	if ( $rest ) {
		$p['fbrand'] = $rest;
	} else {
		unset( $p['fbrand'] );
	}
	$chips[] = array( 'label' => $term ? $term->name : $slug, 'url' => $chip_url( $p ) );
}
if ( $instock ) {
	$p = $active;
	unset( $p['instock'] );
	$chips[] = array( 'label' => __( 'Na stanju', 'lager032' ), 'url' => $chip_url( $p ) );
}
if ( '' !== $min_price || '' !== $max_price ) {
	$p = $active;
	unset( $p['min_price'], $p['max_price'] );
	$label   = sprintf( '%s–%s RSD', ( '' !== $min_price ? $min_price : '0' ), ( '' !== $max_price ? $max_price : '∞' ) );
	$chips[] = array( 'label' => $label, 'url' => $chip_url( $p ) );
}
if ( $search ) {
	$p = $active;
	unset( $p['s'] );
	$chips[] = array( 'label' => '„' . $search . '"', 'url' => $chip_url( $p ) );
}
?>

<section class="archive">
	<div class="container">

		<?php
		// Breadcrumb (visible + BreadcrumbList JSON-LD) — the primary up/cross navigation now that
		// the category panel only drills down.
		$bc_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' );
		$crumbs  = array(
			array( __( 'Početna', 'lager032' ), home_url( '/' ) ),
			array( __( 'Prodavnica', 'lager032' ), $bc_shop ),
		);
		if ( $is_cat ) {
			foreach ( array_reverse( get_ancestors( (int) $current_term->term_id, 'product_cat' ) ) as $aid ) {
				$at = get_term( $aid, 'product_cat' );
				if ( $at && ! is_wp_error( $at ) ) {
					$al       = get_term_link( $at );
					$crumbs[] = array( $at->name, is_wp_error( $al ) ? $bc_shop : $al );
				}
			}
			$crumbs[] = array( $current_term->name, '' );
		} elseif ( $is_brand ) {
			$crumbs[] = array( $current_term->name, '' );
		}
		$last = count( $crumbs ) - 1;
		?>
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Putanja', 'lager032' ); ?>">
			<?php
			foreach ( $crumbs as $i => $c ) {
				if ( $c[1] && $i !== $last ) {
					printf( '<a href="%1$s">%2$s</a>', esc_url( $c[1] ), esc_html( $c[0] ) );
					echo '<span class="crumbs__sep" aria-hidden="true">›</span>';
				} else {
					printf( '<span aria-current="page">%s</span>', esc_html( $c[0] ) );
				}
			}
			?>
		</nav>
		<?php
		$bc_items = array();
		foreach ( $crumbs as $i => $c ) {
			$item = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0] );
			if ( $c[1] ) {
				$item['item'] = $c[1];
			}
			$bc_items[] = $item;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $bc_items,
		) ) . '</script>' . "\n";
		?>

		<div class="archive__head">
			<span class="sec-eyebrow"><?php esc_html_e( 'Katalog', 'lager032' ); ?></span>
			<h1 class="archive__title"><?php echo esc_html( $page_title ); ?></h1>
		</div>

		<?php if ( $chips ) : ?>
			<div class="archive__chips">
				<span class="archive__chips-label"><?php esc_html_e( 'Aktivni filteri:', 'lager032' ); ?></span>
				<?php foreach ( $chips as $chip ) : ?>
					<a class="chip" href="<?php echo esc_url( $chip['url'] ); ?>"><span><?php echo esc_html( $chip['label'] ); ?></span><span class="chip__x" aria-hidden="true">×</span></a>
				<?php endforeach; ?>
				<a class="chip chip--reset" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Poništi sve', 'lager032' ); ?></a>
			</div>
		<?php endif; ?>

		<div class="archive__layout">

			<!-- FILTERS -->
			<aside class="filters">

				<?php
				// Category panel: the shop shows top-level categories (browse in); a category page
				// shows ONLY its own subcategories (drill-down); a leaf category shows none. Up/cross
				// navigation is via the breadcrumb — so we never offer a sibling that ANDs to empty.
				$shop_link   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' );
				$panel_label = $is_cat ? __( 'Potkategorije', 'lager032' ) : __( 'Kategorije', 'lager032' );
				$panel_terms = get_terms( array(
					'taxonomy'   => 'product_cat',
					'parent'     => $is_cat ? (int) $current_term->term_id : 0,
					'hide_empty' => false,
				) );
				if ( ! is_wp_error( $panel_terms ) && $panel_terms ) :
					?>
					<nav class="catnav" aria-label="<?php echo esc_attr( $panel_label ); ?>">
						<span class="catnav__title"><?php echo esc_html( $panel_label ); ?></span>
						<ul class="catnav__list">
							<?php
							foreach ( $panel_terms as $t ) {
								if ( 'uncategorized' === $t->slug ) {
									continue;
								}
								$tl = get_term_link( $t );
								printf(
									'<li><a class="catnav__item" href="%1$s"><span>%2$s</span><em>%3$d</em></a></li>',
									esc_url( is_wp_error( $tl ) ? $shop_link : $tl ),
									esc_html( $t->name ),
									(int) $t->count
								);
							}
							?>
						</ul>
					</nav>
				<?php endif; ?>

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
