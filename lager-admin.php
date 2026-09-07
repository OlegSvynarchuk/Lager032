<?php
/**
 * Plugin Name: Lager – Admin
 * Description: Restructures the WooCommerce admin for day-to-day catalogue management:
 *              trims dead columns from the Products list and surfaces the pricing chain
 *              (VP → marža → net) that actually drives the shelf price.
 * Author:      Pixels2Pixels
 *
 * Install: wp-content/mu-plugins/ . Requires WooCommerce.
 *
 * Sections:
 *   1. Products list — columns
 *   2. Products list — sorting
 *   3. Products list — column widths
 *   4. Reprice progress banner
 *   5. Products list — declutter (filters, buttons, bulk actions)
 *   6. Admin menu — hide unused items
 *   7. Admin menu — promote Orders to top level
 *   8. Orders list — columns
 *   9. Single order screen — declutter
 *  10. Products list — stock views
 *  11. Block user enumeration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ---------------------------------------------------------------------------
 * 1. Products list — columns
 * ---------------------------------------------------------------------------
 *
 * Removed, with the reason each one carries no signal on this catalogue:
 *   thumb       — no product has a featured image yet; 5k identical placeholders.
 *   product_tag — the product_tag taxonomy has 0 terms.
 *   featured    — 0 products are featured.
 *   date        — every product shares the bulk-import date, so it sorts nothing.
 *
 * Added: VP and marža. The shelf price is derived, not typed
 * (net = VP × (1 + marža/100), see lager-auto-reprice.php), so showing the inputs
 * next to the result makes a wrong price diagnosable from the list screen.
 *
 * Priority 20: lager-import-helpers.php adds `net_price` at the default 10, and we
 * reposition it here — so this must run after it.
 */
add_filter( 'manage_edit-product_columns', function ( $columns ) {

	unset(
		$columns['thumb'],
		$columns['product_tag'],
		$columns['featured'],
		$columns['date'],
		// "Brendovi proizvoda" — WP's automatic column for the product_brand
		// taxonomy, which has 0 terms and is not wired to anything.
		$columns['taxonomy-product_brand']
	);

	$columns['lager_vp']    = 'Велепродајна (ВП)';
	$columns['lager_marza'] = 'Маржа';

	// Relabel the column added by lager-import-helpers.php so the whole pricing
	// chain reads in one script rather than mixing Latin and Cyrillic.
	if ( isset( $columns['net_price'] ) ) {
		$columns['net_price'] = 'Нето цена (без ПДВ)';
	}

	// Left-to-right: what it is → where it sits → what it costs us → what it sells for.
	$order = array(
		'cb',
		'sku',
		'name',
		'product_cat',
		'lager_vp',
		'lager_marza',
		'net_price',
		'price',
		'is_in_stock',
	);

	$sorted = array();
	foreach ( $order as $key ) {
		if ( isset( $columns[ $key ] ) ) {
			$sorted[ $key ] = $columns[ $key ];
			unset( $columns[ $key ] );
		}
	}

	// Anything a future plugin adds stays visible, appended after the known set.
	return $sorted + $columns;
}, 20 );

/**
 * Render the two pricing-input columns. Both read the ACF fields defined in
 * lager-import-helpers.php (meta keys `vp` and `marza`).
 */
add_action( 'manage_product_posts_custom_column', function ( $column, $post_id ) {

	if ( 'lager_vp' === $column ) {
		$vp = get_post_meta( $post_id, 'vp', true );
		echo ( '' !== $vp && null !== $vp )
			? esc_html( number_format( (float) $vp, 2, ',', '.' ) )
			: '—';
		return;
	}

	if ( 'lager_marza' === $column ) {
		$marza = get_post_meta( $post_id, 'marza', true );
		if ( '' === $marza || null === $marza ) {
			echo '—';
			return;
		}
		// Margins are whole numbers in practice; don't pad them out to "25,00 %".
		$formatted = rtrim( rtrim( number_format( (float) $marza, 2, ',', '.' ), '0' ), ',' );
		echo esc_html( $formatted ) . '&nbsp;%';
	}
}, 10, 2 );

/**
 * ---------------------------------------------------------------------------
 * 2. Products list — sorting
 * ---------------------------------------------------------------------------
 * VP is the number the manager actually edits, so make it sortable to spot
 * outliers (a mistyped base price stands out at the top or bottom of the list).
 */
add_filter( 'manage_edit-product_sortable_columns', function ( $columns ) {
	$columns['lager_vp'] = 'lager_vp';
	return $columns;
} );

add_action( 'pre_get_posts', function ( $query ) {

	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( 'lager_vp' !== $query->get( 'orderby' ) ) {
		return;
	}

	$query->set( 'meta_key', 'vp' );
	$query->set( 'orderby', 'meta_value_num' );
} );

/**
 * ---------------------------------------------------------------------------
 * 3. Products list — column widths and alignment
 * ---------------------------------------------------------------------------
 * Every money column is right-aligned so the digits line up and the four figures
 * in a row (VP → marža → net → cena) can be read as one chain.
 *
 * The headers must be selected as `th.column-x` explicitly: WordPress ships
 * `.widefat th { text-align: left }`, which is one element-selector more
 * specific than a bare `.column-x` rule and silently wins. That is why the
 * headings sat left while their values sat right.
 */
add_action( 'admin_head-edit.php', function () {

	if ( ! isset( $_GET['post_type'] ) || 'product' !== $_GET['post_type'] ) {
		return;
	}

	echo '<style>
		table.wp-list-table th.column-lager_vp,    table.wp-list-table td.column-lager_vp,
		table.wp-list-table th.column-lager_marza, table.wp-list-table td.column-lager_marza,
		table.wp-list-table th.column-net_price,   table.wp-list-table td.column-net_price,
		table.wp-list-table th.column-price,       table.wp-list-table td.column-price {
			text-align: right;
		}
		table.wp-list-table th.column-lager_vp    { width: 120px; }
		table.wp-list-table th.column-lager_marza { width:  80px; }
		table.wp-list-table th.column-net_price   { width: 140px; }
		table.wp-list-table th.column-price       { width: 110px; }
		table.wp-list-table th.column-sku         { width: 120px; }
	</style>';
} );

/**
 * ---------------------------------------------------------------------------
 * 4. Reprice progress banner
 * ---------------------------------------------------------------------------
 *
 * Changing a category's marža is the single most consequential action in this
 * admin — it rewrites the shelf price of every product in that category. For
 * categories over 50 products (15 of 50 here, including Semering at 1,406 and
 * Ležaj at 611) the work is queued to Action Scheduler and runs in the
 * background, so the page reloads still showing the OLD prices.
 *
 * Without a signal, that reads as "the change didn't work". This banner keeps
 * the job visible until it is genuinely finished, and polls so the manager can
 * watch the number fall rather than guess.
 *
 * The job records are written by lager-auto-reprice.php. That file loads after
 * this one (mu-plugins load alphabetically), so every call is guarded.
 */

/** Screens where a running reprice is worth interrupting for. */
function lager_admin_is_reprice_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return false;
	}
	return in_array( $screen->id, array( 'edit-product', 'edit-product_cat', 'product' ), true );
}

add_action( 'admin_notices', function () {

	if ( ! current_user_can( 'manage_woocommerce' ) || ! lager_admin_is_reprice_screen() ) {
		return;
	}
	if ( ! function_exists( 'lager_reprice_jobs_get' ) ) {
		return;
	}

	$jobs = lager_reprice_jobs_get();
	if ( empty( $jobs ) ) {
		return;
	}

	echo '<div class="notice notice-warning" id="lager-reprice-notice"><p><strong>'
		. esc_html__( 'Preračunavanje cena je u toku', 'lager' )
		. '</strong><br>'
		. esc_html__( 'Cene se ažuriraju u pozadini. Sačekajte da se završi — nije potrebno ponovo čuvati kategoriju.', 'lager' )
		. '</p><ul id="lager-reprice-list" style="margin:0 0 8px 16px;list-style:disc;">';

	foreach ( $jobs as $job ) {
		printf(
			'<li><strong>%s</strong> — %s</li>',
			esc_html( $job['name'] ),
			esc_html( sprintf(
				/* translators: 1: products remaining, 2: total products */
				__( 'preostalo %1$d od %2$d proizvoda', 'lager' ),
				$job['remaining'],
				$job['total']
			) )
		);
	}

	echo '</ul></div>';
} );

/**
 * Poll for progress so the banner updates itself and disappears on completion.
 * Ten seconds is slow enough to be free and fast enough that the manager sees
 * movement while deciding whether to worry.
 */
add_action( 'admin_footer', function () {

	if ( ! current_user_can( 'manage_woocommerce' ) || ! lager_admin_is_reprice_screen() ) {
		return;
	}
	if ( ! function_exists( 'lager_reprice_jobs_get' ) || ! lager_reprice_jobs_get() ) {
		return;
	}

	$url   = esc_js( admin_url( 'admin-ajax.php' ) );
	$nonce = esc_js( wp_create_nonce( 'lager_reprice_status' ) );

	echo "<script>
	(function(){
		var notice = document.getElementById('lager-reprice-notice');
		if (!notice) return;
		var timer = setInterval(function(){
			var body = new FormData();
			body.append('action', 'lager_reprice_status');
			body.append('nonce', '{$nonce}');
			fetch('{$url}', {method:'POST', credentials:'same-origin', body:body})
				.then(function(r){ return r.json(); })
				.then(function(res){
					if (!res || !res.success) return;
					if (!res.data.jobs.length) {
						clearInterval(timer);
						notice.className = 'notice notice-success';
						notice.innerHTML = '<p><strong>" . esc_js( __( 'Cene su ažurirane.', 'lager' ) ) . "</strong></p>';
						return;
					}
					var list = document.getElementById('lager-reprice-list');
					if (list) list.innerHTML = res.data.jobs.map(function(j){
						return '<li><strong>' + j.name + '</strong> — ' + j.text + '</li>';
					}).join('');
				})
				.catch(function(){ clearInterval(timer); });
		}, 10000);
	})();
	</script>";
} );

/**
 * ---------------------------------------------------------------------------
 * 5. Products list — declutter
 * ---------------------------------------------------------------------------
 * The manager's job on this screen is: find an article, check its price, edit
 * stock or VP. Everything below is WooCommerce scaffolding for shops that don't
 * look like this one, and every extra control is a chance to break something.
 */

/**
 * Drop the "product type" and "brand" filter dropdowns.
 *
 * Both are registered through the same WooCommerce filter, so neither needs CSS.
 *   product_type  — all 5,073 products are 'simple'; the dropdown cannot narrow anything.
 *   product_brand — the taxonomy has 0 terms and nothing populates it.
 *
 * The category and stock-status filters are deliberately kept: those do real work.
 */
add_filter( 'woocommerce_products_admin_list_table_filters', function ( $filters ) {
	unset( $filters['product_type'], $filters['product_brand'] );
	return $filters;
}, 20 );

/**
 * Hide WooCommerce's Import / Export buttons next to the page title.
 *
 * These are injected client-side from the localized `woocommerce_admin.urls`
 * (class-wc-admin-assets.php), so there is no PHP hook to unhook — CSS is the
 * reliable lever, and it holds regardless of when the script inserts them.
 *
 * Importing is done through Proizvodi → Uvoz cenovnika, which understands the
 * client's Excel format, category codes and marža. Woo's generic CSV importer
 * would bypass all of that and could overwrite prices with raw values.
 */
add_action( 'admin_head-edit.php', function () {

	if ( ! isset( $_GET['post_type'] ) || 'product' !== $_GET['post_type'] ) {
		return;
	}

	echo '<style>
		.page-title-action[href*="page=product_importer"],
		.page-title-action[href*="page=product_exporter"],
		#wpbody-content .wc-products-export-selected { display: none !important; }
	</style>';
} );

/**
 * Relabel the "Bulk edit" bulk action.
 *
 * WordPress core emits _x( 'Bulk edit', 'verb' ) and the sr_RS pack has no
 * translation for it, so it sits in English among Cyrillic menu items.
 */
add_filter( 'bulk_actions-edit-product', function ( $actions ) {
	if ( isset( $actions['edit'] ) ) {
		$actions['edit'] = 'Групно уређивање';
	}
	return $actions;
}, 20 );

/**
 * ---------------------------------------------------------------------------
 * 6. Admin menu — hide unused items
 * ---------------------------------------------------------------------------
 *
 *   Коментари       — this is a catalogue, not a blog; nothing invites comments.
 *   Kategorije info — the `cat_guide` post type (guides at /vodic/{slug}/).
 *
 * NOTE: remove_menu_page() hides the menu entry, it does not revoke access or
 * delete anything. The one existing guide ("Ležajevi", ID 5020) stays published
 * and reachable on the storefront, and is still editable by direct URL:
 *   /wp-admin/post.php?post=5020&action=edit
 * Drop the second line below to bring the menu back.
 */
add_action( 'admin_menu', function () {

	/* Top-level */
	remove_menu_page( 'edit-comments.php' );
	remove_menu_page( 'edit.php?post_type=cat_guide' );

	/*
	 * Proizvodi submenu. None of these is wired to anything on this store:
	 *   Брендови  — product_brand taxonomy, 0 terms
	 *   Ознаке    — product_tag taxonomy, 0 terms
	 *   Атрибути  — no product attributes are used; all 5,073 products are 'simple'
	 *   Рецензије — reviews are not part of the catalogue workflow
	 */
	$product_menu = 'edit.php?post_type=product';

	remove_submenu_page( $product_menu, 'edit-tags.php?taxonomy=product_brand&amp;post_type=product' );
	remove_submenu_page( $product_menu, 'edit-tags.php?taxonomy=product_brand&post_type=product' );
	remove_submenu_page( $product_menu, 'edit-tags.php?taxonomy=product_tag&amp;post_type=product' );
	remove_submenu_page( $product_menu, 'edit-tags.php?taxonomy=product_tag&post_type=product' );
	remove_submenu_page( $product_menu, 'product_attributes' );
	remove_submenu_page( $product_menu, 'product-reviews' );
}, 999 );

/**
 * ---------------------------------------------------------------------------
 * 7. Admin menu — promote Orders to top level
 * ---------------------------------------------------------------------------
 *
 * Processing orders is the daily job, but HPOS registers the orders list as a
 * submenu of "WooCommerce" (PageController::register_menu), so the most-used
 * screen sits two clicks deep under a vendor name that means nothing to the
 * person using it. Croonus put "Narudžbeni" first in the sidebar; this restores
 * that.
 *
 * IMPORTANT — do not "tidy" this by calling remove_submenu_page( 'woocommerce',
 * 'wc-orders' ). WordPress resolves a plugin page's hook name by searching
 * $submenu for the slug and taking its parent (get_admin_page_parent() →
 * get_plugin_page_hookname()). Delete that entry and 'woocommerce_page_wc-orders'
 * can no longer be derived, the registered page hook stops matching, and the
 * orders screen dies with "Жао нам је, није вам дозвољено да приступите овој
 * страници." The submenu entry must stay registered; it is only hidden visually.
 */
define( 'LAGER_ORDERS_URL', 'admin.php?page=wc-orders' );

add_action( 'admin_menu', function () {

	// The processing-order bubble WooCommerce shows on its own menu; without it
	// the new entry would silently lose the "N orders waiting" signal.
	$bubble = '';
	if ( function_exists( 'wc_processing_order_count' ) ) {
		$count = (int) wc_processing_order_count();
		if ( $count > 0 ) {
			$bubble = sprintf(
				' <span class="awaiting-mod update-plugins count-%1$d"><span class="processing-count">%1$d</span></span>',
				$count
			);
		}
	}

	add_menu_page(
		'Наруџбине',
		'Наруџбине' . $bubble,
		'edit_shop_orders',
		LAGER_ORDERS_URL,   // a '.php' slug is rendered as a plain link
		'',
		'dashicons-cart',
		'55.4'              // WooCommerce sits at 55.5, Proizvodi just after it
	);
}, 999 );

/**
 * Hide the now-duplicated "Наруџбине" entry inside the WooCommerce submenu.
 * CSS rather than remove_submenu_page(), for the reason documented above — the
 * entry has to remain registered for the page to resolve at all.
 */
add_action( 'admin_head', function () {
	echo '<style>
		#toplevel_page_woocommerce .wp-submenu li:has(> a[href="admin.php?page=wc-orders"]),
		#toplevel_page_woocommerce .wp-submenu a[href="admin.php?page=wc-orders"] {
			display: none;
		}
	</style>';
} );

/**
 * Keep the sidebar highlight on the new top-level entry for every orders screen
 * — list, single order edit, and new order. Without this WordPress would still
 * try to light up the WooCommerce menu the submenu used to live under.
 */
add_filter( 'parent_file', function ( $parent_file ) {

	if ( isset( $_GET['page'] ) && 0 === strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'wc-orders' ) ) {
		return LAGER_ORDERS_URL;
	}

	return $parent_file;
} );

/**
 * ---------------------------------------------------------------------------
 * 8. Orders list — columns
 * ---------------------------------------------------------------------------
 *
 * Rebuilt to match the Croonus order overview the client worked in for years:
 *   #  Ime  Prezime  E-mail  Telefon  Grad  Broj artikala  Ukupno  Datum  Status
 *
 * WooCommerce packs the order number and the buyer's name into a single
 * "Order" column; Croonus kept them apart, which scans better when you are
 * looking someone up by surname. Telefon / Grad / Broj artikala already exist —
 * they are added by the theme in lager032/inc/admin-orders.php — and are only
 * relabelled to Cyrillic here so the header row reads in one script.
 *
 * Priority 20: must run after the theme's filter so its columns exist to place.
 */
add_filter( 'woocommerce_shop_order_list_table_columns', function ( $columns ) {

	$columns['order_number'] = '#';
	$columns['lager_row']    = 'Р. бр.';

	$columns['lager_first_name'] = 'Име';
	$columns['lager_last_name']  = 'Презиме';
	$columns['lager_email']      = 'Е-пошта';

	// Relabel the theme's columns (Latin) for consistency with the rest.
	$columns['lager_phone'] = 'Телефон';
	$columns['lager_city']  = 'Град';
	$columns['lager_items'] = 'Број артикала';

	$order = array(
		'cb',
		'lager_row',
		'order_number',
		'lager_first_name',
		'lager_last_name',
		'lager_email',
		'lager_phone',
		'lager_city',
		'lager_items',
		'order_total',
		'order_date',
		'order_status',
	);

	$sorted = array();
	foreach ( $order as $key ) {
		if ( isset( $columns[ $key ] ) ) {
			$sorted[ $key ] = $columns[ $key ];
			unset( $columns[ $key ] );
		}
	}

	// Anything else (billing/shipping address, wc_actions) stays after the known set.
	return $sorted + $columns;
}, 20 );

/**
 * Remove "Порекло" (order attribution).
 *
 * This one needs its own hook. OrderAttributionController registers the column
 * on `manage_{$screen_id}_columns` (OrderAttributionController::567), which WP
 * applies *after* `woocommerce_shop_order_list_table_columns` — so unsetting it
 * alongside the others above has no effect. Priority 99 to land last.
 *
 * Every order on this store reads "Директно", so the column is pure noise.
 */
add_filter( 'manage_woocommerce_page_wc-orders_columns', function ( $columns ) {
	unset( $columns['origin'] );
	return $columns;
}, 99 );

add_filter( 'manage_edit-shop_order_columns', function ( $columns ) {
	unset( $columns['origin'] );
	return $columns;
}, 99 );

/**
 * Remove two filter dropdowns above the orders list:
 *   "Сви канали продаје"          — ListTable::created_via_filter
 *   "Филтрирај по регистрованом…" — ListTable::customers_filter
 *
 * Every order here arrives through the storefront, and customers are guests
 * (there is one registered user on the site), so neither dropdown can narrow
 * anything. The date filter and the status links above it do the real work.
 *
 * Both are registered in ListTable::setup_hooks() as methods on a container-built
 * instance, so we match on the method name rather than the object — that holds
 * whether or not the container hands back a shared instance. Registration happens
 * on load-woocommerce_page_wc-orders, which fires before admin_head, so by the
 * time this runs the callbacks are present and the table has not yet rendered.
 */
add_action( 'admin_head', function () {

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'woocommerce_page_wc-orders' !== $screen->id ) {
		return;
	}

	global $wp_filter;

	$hook = 'woocommerce_order_list_table_restrict_manage_orders';
	if ( ! isset( $wp_filter[ $hook ] ) ) {
		return;
	}

	$drop = array( 'created_via_filter', 'customers_filter' );

	foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $id => $callback ) {

			if ( ! is_array( $callback['function'] ) || ! is_object( $callback['function'][0] ) ) {
				continue;
			}

			if ( in_array( $callback['function'][1], $drop, true ) ) {
				unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $id ] );
			}
		}
	}
}, 1 );

/**
 * Keep the row-number column narrow; it holds at most three digits.
 */
add_action( 'admin_head', function () {

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'woocommerce_page_wc-orders' !== $screen->id ) {
		return;
	}

	echo '<style>
		table.wp-list-table th.column-lager_row,
		table.wp-list-table td.column-lager_row { width: 48px; text-align: right; color: #646970; }
	</style>';
} );

/**
 * Strip the buyer name out of the "#" column — it now has columns of its own.
 *
 * Filtering the name rather than replacing the column keeps WooCommerce's own
 * rendering intact, so the order-edit link and the Preview button both survive
 * (ListTable::render_order_number_column). Scoped to the orders list so the name
 * is not blanked anywhere else it is used.
 */
add_filter( 'woocommerce_admin_order_buyer_name', function ( $buyer ) {

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( $screen && 'woocommerce_page_wc-orders' === $screen->id ) {
		return '';
	}

	return $buyer;
} );

add_action( 'woocommerce_shop_order_list_table_custom_column', function ( $column, $order ) {

	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order );
	}
	if ( ! $order ) {
		return;
	}

	switch ( $column ) {
		case 'lager_row':
			/*
			 * Running position in the list, continuing across pages: page 2 of a
			 * 20-per-page list starts at 21, not 1. Seeded once per request from
			 * the paging offset, then incremented per row.
			 */
			static $seq = null;

			if ( null === $seq ) {
				$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
				$per_page = (int) get_user_meta( get_current_user_id(), 'edit_shop_order_per_page', true );

				if ( $per_page < 1 ) {
					$per_page = 20; // WooCommerce's default for this screen.
				}

				$seq = ( $paged - 1 ) * $per_page;
			}

			echo (int) ++$seq;
			break;

		case 'lager_first_name':
			echo esc_html( $order->get_billing_first_name() );
			break;

		case 'lager_last_name':
			echo esc_html( $order->get_billing_last_name() );
			break;

		case 'lager_email':
			$email = $order->get_billing_email();
			if ( $email ) {
				printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
			} else {
				echo '—';
			}
			break;
	}
}, 10, 2 );

/**
 * ---------------------------------------------------------------------------
 * 9. Single order screen — declutter
 * ---------------------------------------------------------------------------
 */

/**
 * Remove the "Прилагођена поља" (Custom Fields) meta box.
 *
 * It dumps raw order meta — internal keys, checkout extras, attribution data —
 * as editable key/value rows. Nothing there is meant to be hand-edited, and a
 * stray edit corrupts the order silently.
 *
 * Registered as 'order_custom' in Orders\Edit::add_order_specific_meta_box().
 */
add_action( 'add_meta_boxes', function () {

	remove_meta_box( 'order_custom', 'woocommerce_page_wc-orders', 'normal' );
	remove_meta_box( 'postcustom', 'shop_order', 'normal' ); // legacy (non-HPOS) screen

	/*
	 * "Приписивање наруџбине" (Order attribution) in the right sidebar — marketing
	 * telemetry: traffic origin, device type, referrer, campaign, session page
	 * views. It answers "which ad brought this sale?", a question this store does
	 * not ask; every order records "Директно". Same feature as the Порекло column
	 * removed from the orders list.
	 *
	 * Registered as 'woocommerce-order-source-data' in Orders\Edit.
	 */
	remove_meta_box( 'woocommerce-order-source-data', 'woocommerce_page_wc-orders', 'side' );
	remove_meta_box( 'woocommerce-order-source-data', 'shop_order', 'side' );
}, 99 );

/**
 * Show the category image for order line items.
 *
 * No product carries a photo of its own (0 of 5,073 have a featured image), so
 * WooCommerce falls back to its grey placeholder here. The storefront does not:
 * the theme substitutes the product's category visual via
 * lager_product_category_image_id() — subcategory image, else parent category,
 * else the shared placeholder — in cart, search and archive.
 *
 * This applies the same rule in the admin, so an order line looks like what the
 * customer saw. A real product image always wins if one is ever uploaded.
 */
add_filter( 'woocommerce_admin_order_item_thumbnail', function ( $thumbnail, $item_id, $item ) {

	if ( ! function_exists( 'lager_product_category_image_id' ) ) {
		return $thumbnail;
	}

	$product = ( is_object( $item ) && method_exists( $item, 'get_product' ) ) ? $item->get_product() : null;

	// No product (deleted), or it has a genuine image — leave WooCommerce alone.
	if ( ! $product || $product->get_image_id() ) {
		return $thumbnail;
	}

	$image_id = (int) lager_product_category_image_id( $product->get_id() );
	if ( ! $image_id ) {
		return $thumbnail;
	}

	$image = wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'alt' => '' ) );

	return $image ? $image : $thumbnail;
}, 10, 3 );

/**
 * Hide the "Повраћај новца" (Refund) button under the line items.
 *
 * CSS because the button is printed inline in WooCommerce's
 * html-order-items.php view (line 324) with no hook around it.
 *
 * NOTE: this hides the control, it does not disable refunding. The REST API and
 * WP-CLI can still refund, and the button returns the moment this rule is
 * dropped. It is a guard against accidental clicks, not a permission.
 */
add_action( 'admin_head', function () {

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'shop_order' ), true ) ) {
		return;
	}

	echo '<style>
		#woocommerce-order-items button.refund-items { display: none !important; }
	</style>';
} );

add_action( 'wp_ajax_lager_reprice_status', function () {

	if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'lager_reprice_status', 'nonce', false ) ) {
		wp_send_json_error( array(), 403 );
	}
	if ( ! function_exists( 'lager_reprice_jobs_get' ) ) {
		wp_send_json_success( array( 'jobs' => array() ) );
	}

	$out = array();
	foreach ( lager_reprice_jobs_get() as $job ) {
		$out[] = array(
			'name' => $job['name'],
			'text' => sprintf(
				/* translators: 1: products remaining, 2: total products */
				__( 'preostalo %1$d od %2$d proizvoda', 'lager' ),
				$job['remaining'],
				$job['total']
			),
		);
	}

	wp_send_json_success( array( 'jobs' => $out ) );
} );

/**
 * ---------------------------------------------------------------------------
 * 10. Products list — stock views
 * ---------------------------------------------------------------------------
 *
 * Stock never blocks a sale here (inc/cart.php forces every product in stock and
 * allows backorders), which is deliberate: the client orders more from their
 * supplier once they see an item running short. But that removes the natural
 * signal — nothing stops, so nothing announces itself.
 *
 * WooCommerce's answer is the low-stock e-mail, which is useless on this
 * catalogue: 2,411 of 5,073 products sit at 1–2 units, so an alert would fire on
 * nearly every order and be ignored within a week. Those e-mails are now off.
 *
 * This replaces push with pull: two links above the Products list that the
 * client checks when it suits them.
 */
define( 'LAGER_LOW_STOCK_THRESHOLD', 2 );

/**
 * Count products in a stock range, straight from WooCommerce's lookup table —
 * far cheaper than a meta_query over 5,073 products just to render a number.
 */
function lager_stock_count( $view ) {
	global $wpdb;

	$table = $wpdb->prefix . 'wc_product_meta_lookup';

	if ( 'out' === $view ) {
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE stock_quantity <= 0" );
	}

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE stock_quantity > 0 AND stock_quantity <= %d",
			LAGER_LOW_STOCK_THRESHOLD
		)
	);
}

add_filter( 'views_edit-product', function ( $views ) {

	$current = isset( $_GET['lager_stock'] ) ? sanitize_text_field( wp_unslash( $_GET['lager_stock'] ) ) : '';

	foreach ( array(
		'out' => 'Нема на залихама',
		'low' => 'Ниске залихе',
	) as $key => $label ) {

		$count = lager_stock_count( $key );

		$views[ 'lager_stock_' . $key ] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg(
				array( 'post_type' => 'product', 'lager_stock' => $key ),
				admin_url( 'edit.php' )
			) ),
			$key === $current ? ' class="current" aria-current="page"' : '',
			esc_html( $label ),
			esc_html( number_format_i18n( $count ) )
		);
	}

	return $views;
} );

/**
 * Apply the stock filter to the products query.
 */
add_action( 'pre_get_posts', function ( $query ) {

	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'product' !== $query->get( 'post_type' ) ) {
		return;
	}
	if ( empty( $_GET['lager_stock'] ) ) {
		return;
	}

	$view = sanitize_text_field( wp_unslash( $_GET['lager_stock'] ) );

	if ( 'out' === $view ) {
		$meta = array(
			array(
				'key'     => '_stock',
				'value'   => 0,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			),
		);
	} elseif ( 'low' === $view ) {
		$meta = array(
			array(
				'key'     => '_stock',
				'value'   => array( 1, LAGER_LOW_STOCK_THRESHOLD ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			),
		);
	} else {
		return;
	}

	$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery
} );

/**
 * ---------------------------------------------------------------------------
 * 11. Block user enumeration
 * ---------------------------------------------------------------------------
 *
 * The site has one administrator, and its username was deliberately randomised
 * (admin_0k0gk07x) so it could not be guessed. Two WordPress defaults gave it
 * away anyway:
 *
 *   /wp-json/wp/v2/users  returned {"slug":"admin_0k0gk07x", ...} to anyone
 *   /?author=1            redirected to /author/admin_0k0gk07x/
 *
 * Handing out the username turns a login into a password-only guess. Closing
 * this restores the value of the random username.
 */

/** Hide the users REST route from anyone who cannot already list users. */
add_filter( 'rest_endpoints', function ( $endpoints ) {

	if ( is_user_logged_in() && current_user_can( 'list_users' ) ) {
		return $endpoints;
	}

	unset( $endpoints['/wp/v2/users'] );
	unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

	return $endpoints;
} );

/**
 * Kill ?author=N probing and author archives.
 *
 * Priority 0 so this runs before redirect_canonical(), which is what turns
 * ?author=1 into the username-revealing /author/<slug>/ URL. A catalogue has no
 * use for author archives, so both forms simply 404.
 */
add_action( 'template_redirect', function () {

	if ( is_admin() ) {
		return;
	}

	$probing = ! empty( $_GET['author'] ) || is_author();

	if ( ! $probing ) {
		return;
	}

	global $wp_query;

	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}, 0 );
