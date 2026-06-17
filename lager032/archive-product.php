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
// Catalog search uses our own `q` param, NOT WordPress' `s` — keeping `s` out of the URL avoids
// WP's search machinery (incl. its single-result "redirect to the product" behaviour). The value
// is still passed to WP_Query as `s` internally below.
$search     = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
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

// ---- Contextual price bounds (gross) — computed from the current list (category + search +
// availability), EXCLUDING the price filter itself, so the slider range always matches what's
// shown and you can still widen. $show_price is false when there's no real range (≤1 product or
// all one price) → the price filter is hidden. ----
$lk = $GLOBALS['wpdb']->prefix . 'wc_product_meta_lookup';
// Search uses the shared normalized matcher (title + SKU) so the archive list matches the typeahead.
$search_ids = ( '' !== $search ) ? lager_search_product_ids( $search ) : null;
$has_constraints = ( $is_cat || $is_brand || '' !== $search || $instock || ! empty( $sel_brands ) || ! empty( $sel_cats ) );
if ( $has_constraints ) {
	$bargs = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'tax_query'      => $tax_query,  // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery -- instock only at this point
	);
	if ( null !== $search_ids ) {
		$bargs['post__in'] = $search_ids ? $search_ids : array( 0 );
	}
	$bound_ids   = get_posts( $bargs );
	$bound_count = count( $bound_ids );
	$prow        = $bound_ids
		? $GLOBALS['wpdb']->get_row( 'SELECT MIN(min_price) lo, MAX(max_price) hi FROM ' . $lk . ' WHERE product_id IN (' . implode( ',', array_map( 'absint', $bound_ids ) ) . ') AND min_price > 0' )
		: null;
} else {
	$prow        = $GLOBALS['wpdb']->get_row( 'SELECT MIN(min_price) lo, MAX(max_price) hi, COUNT(*) c FROM ' . $lk . ' WHERE min_price > 0' );
	$bound_count = $prow ? (int) $prow->c : 0;
}
$price_lo   = ( $prow && null !== $prow->lo ) ? (int) ( floor( (float) $prow->lo * 1.2 / 10 ) * 10 ) : 0;
$price_hi   = ( $prow && null !== $prow->hi ) ? (int) ( ceil( (float) $prow->hi * 1.2 / 10 ) * 10 ) : 0;
$show_price = ( $bound_count > 1 && $price_hi > $price_lo );

if ( '' !== $min_price || '' !== $max_price ) {
	// Od/Do are gross (incl. PDV, matching displayed prices); stored _price is net, so convert
	// the bounds to net for the comparison. PDV is a flat 20%.
	$net_min = ( '' !== $min_price ) ? ( (float) $min_price / 1.2 ) : '';
	$net_max = ( '' !== $max_price ) ? ( (float) $max_price / 1.2 ) : '';
	$pq      = array( 'key' => '_price', 'type' => 'NUMERIC' );
	if ( '' !== $net_min && '' !== $net_max ) {
		$pq['compare'] = 'BETWEEN';
		$pq['value']   = array( $net_min, $net_max );
	} elseif ( '' !== $net_min ) {
		$pq['compare'] = '>=';
		$pq['value']   = $net_min;
	} else {
		$pq['compare'] = '<=';
		$pq['value']   = $net_max;
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
if ( null !== $search_ids ) {
	$args['post__in'] = $search_ids ? $search_ids : array( 0 );
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
if ( null !== $search_ids && 'date' === $orderby ) {
	$args['orderby'] = 'post__in'; // keep search-relevance order unless the user picked a sort
	unset( $args['order'] );
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
	$active['q'] = $search;
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
	unset( $p['q'] );
	$chips[] = array( 'label' => '„' . $search . '"', 'url' => $chip_url( $p ) );
}
?>

<section class="archive<?php echo ( ! $is_cat && ! $is_brand ) ? ' archive--shop' : ''; ?>">
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
				// Persistent category navigation (links, not filters): every archive shows the full
				// top-level list. Parents with subcategories are collapsible — the current branch is
				// auto-expanded and highlighted, the rest collapsed. Navigation only → one click to any
				// category, no empty-AND, consistent rail everywhere. (Breadcrumb above handles "where am I".)
				$shop_link = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/prodavnica/' );
				$cur_id    = $is_cat ? (int) $current_term->term_id : 0;
				$open_id   = $cur_id;
				if ( $cur_id ) {
					$anc = get_ancestors( $cur_id, 'product_cat' );
					if ( $anc ) {
						$open_id = (int) end( $anc ); // top-level ancestor → that branch opens
					}
				}
				$parents = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => false ) );
				?>
				<nav class="catnav" aria-label="<?php esc_attr_e( 'Kategorije', 'lager032' ); ?>">
					<span class="catnav__title"><?php esc_html_e( 'Kategorije', 'lager032' ); ?></span>
					<a class="catnav__all<?php echo $is_cat ? '' : ' is-active'; ?>" href="<?php echo esc_url( $shop_link ); ?>">
						<?php lager032_icon( 'grid' ); ?><span><?php esc_html_e( 'Svi proizvodi', 'lager032' ); ?></span>
					</a>
					<?php if ( ! is_wp_error( $parents ) && $parents ) : ?>
						<ul class="catnav__list">
							<?php
							foreach ( $parents as $p ) {
								if ( 'uncategorized' === $p->slug ) {
									continue;
								}
								$pid      = (int) $p->term_id;
								$p_active = ( $pid === $cur_id );
								$p_link   = get_term_link( $p );
								$p_url    = is_wp_error( $p_link ) ? $shop_link : $p_link;
								$kids     = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $pid, 'hide_empty' => false ) );
								$has_kids = ( $kids && ! is_wp_error( $kids ) );
								$item     = sprintf(
									'<a class="catnav__item%1$s" href="%2$s"%3$s><span>%4$s</span><em>%5$d</em></a>',
									$p_active ? ' is-active' : '',
									esc_url( $p_url ),
									$p_active ? ' aria-current="page"' : '',
									esc_html( $p->name ),
									(int) $p->count
								);

								if ( ! $has_kids ) {
									echo '<li>' . $item . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* above.
									continue;
								}

								$is_open = ( $pid === $open_id );
								printf( '<li class="catnav__group%s"><div class="catnav__row">', $is_open ? ' is-open' : '' );
								echo $item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* above.
								printf(
									'<button type="button" class="catnav__toggle" aria-expanded="%1$s" aria-label="%2$s">%3$s</button></div><ul class="catnav__sub">',
									$is_open ? 'true' : 'false',
									esc_attr__( 'Prikaži potkategorije', 'lager032' ),
									lager032_get_icon( 'chevron' )
								);
								foreach ( $kids as $k ) {
									$k_active = ( (int) $k->term_id === $cur_id );
									$k_link   = get_term_link( $k );
									$k_url    = is_wp_error( $k_link ) ? $shop_link : $k_link;
									printf(
										'<li><a class="catnav__sublink%1$s" href="%2$s"%3$s><span>%4$s</span><em>%5$d</em></a></li>',
										$k_active ? ' is-active' : '',
										esc_url( $k_url ),
										$k_active ? ' aria-current="page"' : '',
										esc_html( $k->name ),
										(int) $k->count
									);
								}
								echo '</ul></li>';
							}
							?>
						</ul>
					<?php endif; ?>
				</nav>

				<form class="filters__form" method="get" action="<?php echo esc_url( $base_url ); ?>">
					<div class="filters__top"><?php lager032_icon( 'grid' ); ?><span><?php esc_html_e( 'Filteri', 'lager032' ); ?></span></div>

					<div class="filters__search">
						<?php lager032_icon( 'search' ); ?>
						<input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Pretraži artikle...', 'lager032' ); ?>">
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

					<?php if ( $show_price ) :
						$val_lo = ( '' !== $min_price ) ? max( $price_lo, min( $price_hi, (int) $min_price ) ) : $price_lo;
						$val_hi = ( '' !== $max_price ) ? max( $price_lo, min( $price_hi, (int) $max_price ) ) : $price_hi;
						?>
					<details class="fsec" open>
						<summary><?php esc_html_e( 'Cena (RSD)', 'lager032' ); ?><?php lager032_icon( 'chevron' ); ?></summary>
						<div class="prange" data-min="<?php echo esc_attr( $price_lo ); ?>" data-max="<?php echo esc_attr( $price_hi ); ?>">
							<div class="prange__slider">
								<span class="prange__rail" aria-hidden="true"></span>
								<span class="prange__fill" aria-hidden="true"></span>
								<input type="range" class="prange__range prange__range--min" min="<?php echo esc_attr( $price_lo ); ?>" max="<?php echo esc_attr( $price_hi ); ?>" step="10" value="<?php echo esc_attr( $val_lo ); ?>" aria-label="<?php esc_attr_e( 'Najniža cena', 'lager032' ); ?>">
								<input type="range" class="prange__range prange__range--max" min="<?php echo esc_attr( $price_lo ); ?>" max="<?php echo esc_attr( $price_hi ); ?>" step="10" value="<?php echo esc_attr( $val_hi ); ?>" aria-label="<?php esc_attr_e( 'Najviša cena', 'lager032' ); ?>">
							</div>
							<div class="prange__fields">
								<label class="prange__field"><?php esc_html_e( 'Od', 'lager032' ); ?><input type="number" inputmode="numeric" class="prange__num prange__num--min" name="min_price" min="<?php echo esc_attr( $price_lo ); ?>" max="<?php echo esc_attr( $price_hi ); ?>" value="<?php echo ( '' !== $min_price ) ? esc_attr( (int) $min_price ) : ''; ?>" placeholder="<?php echo esc_attr( $price_lo ); ?>"></label>
								<label class="prange__field"><?php esc_html_e( 'Do', 'lager032' ); ?><input type="number" inputmode="numeric" class="prange__num prange__num--max" name="max_price" min="<?php echo esc_attr( $price_lo ); ?>" max="<?php echo esc_attr( $price_hi ); ?>" value="<?php echo ( '' !== $max_price ) ? esc_attr( (int) $max_price ) : ''; ?>" placeholder="<?php echo esc_attr( $price_hi ); ?>"></label>
							</div>
						</div>
					</details>
					<?php endif; // $show_price ?>

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
							echo '<input type="hidden" name="q" value="' . esc_attr( $search ) . '">';
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
