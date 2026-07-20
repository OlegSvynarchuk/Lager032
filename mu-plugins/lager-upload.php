<?php
/**
 * Plugin Name: Lager — Uvoz cenovnika (Excel)
 * Description: Admin alat za uvoz/ažuriranje kataloga proizvoda iz .xlsx fajla.
 *              Uparuje po šifri (SKU), kategorije po šifri kategorije (marža se čuva),
 *              računa neto cenu preko lager_reprice_product(), a nedostajuće artikle
 *              stavlja na stanje 0. Mesto: Proizvodi → Uvoz cenovnika.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ *
 *  Minimal .xlsx reader (ZipArchive + SimpleXML) — no dependencies.
 * ------------------------------------------------------------------ */

/** Column letters (e.g. "AB") -> 0-based index. */
function lager_xlsx_col_index( $letters ) {
	$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $letters ) );
	$n = 0;
	$len = strlen( $letters );
	for ( $i = 0; $i < $len; $i++ ) {
		$n = $n * 26 + ( ord( $letters[ $i ] ) - 64 );
	}
	return $n - 1;
}

/**
 * Read the first worksheet of an .xlsx into an array of rows (each row an array
 * indexed by 0-based column). Returns WP_Error on failure.
 *
 * @param string $path Absolute path to the .xlsx file.
 * @return array|WP_Error
 */
function lager_xlsx_rows( $path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'zip', 'PHP ekstenzija ZipArchive nije dostupna na serveru.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return new WP_Error( 'zip', 'Fajl nije validan .xlsx (ne može da se otvori).' );
	}

	// Shared strings table.
	$shared = array();
	$ss = $zip->getFromName( 'xl/sharedStrings.xml' );
	if ( false !== $ss ) {
		$x = @simplexml_load_string( $ss );
		if ( $x ) {
			foreach ( $x->si as $si ) {
				if ( isset( $si->t ) ) {
					$shared[] = (string) $si->t;
				} else {
					$t = '';
					foreach ( $si->r as $r ) {
						$t .= (string) $r->t;
					}
					$shared[] = $t;
				}
			}
		}
	}

	// Resolve the first sheet's target file via workbook rels (fallback sheet1.xml).
	$sheet_path = 'xl/worksheets/sheet1.xml';
	$wb = $zip->getFromName( 'xl/workbook.xml' );
	$rels = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
	if ( false !== $wb && false !== $rels ) {
		$wbx = @simplexml_load_string( $wb );
		$rx  = @simplexml_load_string( $rels );
		if ( $wbx && $rx ) {
			$wbx->registerXPathNamespace( 'r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
			$sheets = $wbx->sheets->sheet;
			if ( $sheets ) {
				$first = $sheets[0];
				$rid   = (string) $first->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )->id;
				foreach ( $rx->Relationship as $rel ) {
					if ( (string) $rel['Id'] === $rid ) {
						$target = ltrim( (string) $rel['Target'], '/' );
						$sheet_path = ( 0 === strpos( $target, 'xl/' ) ) ? $target : 'xl/' . $target;
						break;
					}
				}
			}
		}
	}

	$sheet = $zip->getFromName( $sheet_path );
	if ( false === $sheet ) {
		$sheet = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
	}
	$zip->close();
	if ( false === $sheet ) {
		return new WP_Error( 'sheet', 'Ne mogu da pročitam prvi list u fajlu.' );
	}
	$x = @simplexml_load_string( $sheet );
	if ( ! $x || ! isset( $x->sheetData ) ) {
		return new WP_Error( 'parse', 'Ne mogu da parsiram sadržaj lista.' );
	}

	$rows = array();
	foreach ( $x->sheetData->row as $row ) {
		$cells = array();
		foreach ( $row->c as $c ) {
			$ref = (string) $c['r'];
			$ci  = $ref ? lager_xlsx_col_index( $ref ) : count( $cells );
			$t   = (string) $c['t'];
			if ( 's' === $t ) {
				$idx = (int) $c->v;
				$val = isset( $shared[ $idx ] ) ? $shared[ $idx ] : '';
			} elseif ( 'inlineStr' === $t ) {
				$val = isset( $c->is->t ) ? (string) $c->is->t : '';
			} else {
				$val = isset( $c->v ) ? (string) $c->v : '';
			}
			$cells[ $ci ] = $val;
		}
		$rows[] = $cells;
	}
	return $rows;
}

/** Normalize a numeric cell (comma decimals, spaces). */
function lager_uvoz_num( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return 0.0;
	}
	$v = str_replace( array( ' ', "\xc2\xa0" ), '', $v );
	$v = str_replace( ',', '.', $v );
	return is_numeric( $v ) ? (float) $v : 0.0;
}

/**
 * Parse raw xlsx rows into normalized catalogue rows. Skips the header row and
 * any row without a SKU.
 *
 * @param array $raw Rows from lager_xlsx_rows().
 * @return array List of ['sku','code','catname','name','stock','vp'].
 */
function lager_uvoz_parse( $raw ) {
	$out = array();
	foreach ( $raw as $i => $r ) {
		if ( 0 === $i ) {
			continue; // header: IdBroj | KlBroj | KLNaziv | NazivId | Stanje | VP_Nova_Cena
		}
		$sku = trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) );
		if ( '' === $sku ) {
			continue;
		}
		$out[] = array(
			'sku'     => $sku,
			'code'    => trim( (string) ( isset( $r[1] ) ? $r[1] : '' ) ),
			'catname' => trim( (string) ( isset( $r[2] ) ? $r[2] : '' ) ),
			'name'    => trim( (string) ( isset( $r[3] ) ? $r[3] : '' ) ),
			'stock'   => lager_uvoz_num( isset( $r[4] ) ? $r[4] : 0 ),
			'vp'      => lager_uvoz_num( isset( $r[5] ) ? $r[5] : 0 ),
		);
	}
	return $out;
}

/* ------------------------------------------------------------------ *
 *  Category / product upsert helpers.
 * ------------------------------------------------------------------ */

/**
 * Find (or create) a product_cat by its code (term meta `sifra`). New categories
 * are created flat with an empty marža (flagged for manual entry). Marža is never
 * overwritten. Returns [term_id, is_new] or null.
 */
function lager_uvoz_category( $code, $name, &$new_cats ) {
	static $cache = array();
	$ck = $code . '|' . $name;
	if ( isset( $cache[ $ck ] ) ) {
		return $cache[ $ck ];
	}
	$term_id = 0;

	if ( '' !== $code ) {
		$found = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => 1,
			'meta_key'   => 'sifra',   // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => $code,     // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'     => 'ids',
		) );
		if ( ! is_wp_error( $found ) && $found ) {
			$term_id = (int) $found[0];
		}
	}
	if ( ! $term_id && '' !== $name ) {
		$by = get_term_by( 'name', $name, 'product_cat' );
		if ( $by ) {
			$term_id = (int) $by->term_id;
			if ( '' !== $code && '' === (string) get_term_meta( $term_id, 'sifra', true ) ) {
				update_term_meta( $term_id, 'sifra', $code );
			}
		}
	}
	if ( ! $term_id ) {
		$res = wp_insert_term( $name ? $name : ( 'Kategorija ' . $code ), 'product_cat' );
		if ( is_wp_error( $res ) ) {
			$cache[ $ck ] = null;
			return null;
		}
		$term_id = (int) $res['term_id'];
		if ( '' !== $code ) {
			update_term_meta( $term_id, 'sifra', $code );
		}
		$new_cats[ $code . '' ] = $name; // flag: needs marža
		$cache[ $ck ] = array( $term_id, true );
		return $cache[ $ck ];
	}
	$cache[ $ck ] = array( $term_id, false );
	return $cache[ $ck ];
}

/**
 * Upsert one product by SKU: title, category, stock, VP; recompute net price.
 * Returns 'created' | 'updated' | 'error'.
 */
function lager_uvoz_product( $row, $term_id ) {
	$existing = wc_get_product_id_by_sku( $row['sku'] );
	if ( $existing ) {
		$product = wc_get_product( $existing );
		if ( ! $product ) {
			return 'error';
		}
	} else {
		$product = new WC_Product_Simple();
		$product->set_sku( $row['sku'] );
		$product->set_status( 'publish' );
	}
	$product->set_name( $row['name'] );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( $row['stock'] );
	if ( $term_id ) {
		$product->set_category_ids( array( $term_id ) );
	}
	$id = $product->save();
	if ( ! $id ) {
		return 'error';
	}
	update_post_meta( $id, 'vp', $row['vp'] );
	if ( function_exists( 'lager_reprice_product' ) ) {
		lager_reprice_product( $id );
	}
	return $existing ? 'updated' : 'created';
}

/* ------------------------------------------------------------------ *
 *  Admin page (Proizvodi → Uvoz cenovnika).
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=product',
		'Увоз ценовника',
		'Увоз ценовника',
		'manage_woocommerce',
		'lager-uvoz',
		'lager_uvoz_render'
	);
} );

/** Transient key for the parsed rows of the current user. */
function lager_uvoz_key() {
	return 'lager_uvoz_' . get_current_user_id();
}

function lager_uvoz_render() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemate dozvolu.' );
	}
	$notice  = '';
	$preview = null;

	// Handle upload → parse → store → preview.
	if ( isset( $_POST['lager_uvoz_upload'] ) && check_admin_referer( 'lager_uvoz', 'lager_uvoz_nonce' ) ) {
		if ( empty( $_FILES['catalog']['tmp_name'] ) || ! is_uploaded_file( $_FILES['catalog']['tmp_name'] ) ) {
			$notice = '<div class="notice notice-error"><p>Niste izabrali fajl.</p></div>';
		} else {
			$name = isset( $_FILES['catalog']['name'] ) ? sanitize_file_name( $_FILES['catalog']['name'] ) : '';
			if ( ! preg_match( '/\.xlsx$/i', $name ) ) {
				$notice = '<div class="notice notice-error"><p>Dozvoljen je samo .xlsx fajl.</p></div>';
			} else {
				$raw = lager_xlsx_rows( $_FILES['catalog']['tmp_name'] );
				if ( is_wp_error( $raw ) ) {
					$notice = '<div class="notice notice-error"><p>' . esc_html( $raw->get_error_message() ) . '</p></div>';
				} else {
					$rows = lager_uvoz_parse( $raw );
					if ( ! $rows ) {
						$notice = '<div class="notice notice-error"><p>Fajl ne sadrži nijedan red sa šifrom.</p></div>';
					} else {
						set_transient( lager_uvoz_key(), $rows, 2 * HOUR_IN_SECONDS );
						$preview = lager_uvoz_analyze( $rows );
					}
				}
			}
		}
	}

	// Show preview again if rows are pending and no fresh upload.
	if ( null === $preview && isset( $_GET['pending'] ) ) {
		$rows = get_transient( lager_uvoz_key() );
		if ( $rows ) {
			$preview = lager_uvoz_analyze( $rows );
		}
	}
	?>
	<div class="wrap">
		<h1>Увоз ценовника (Excel)</h1>
		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<div class="card" style="max-width:820px;padding:16px 20px;">
			<h2 style="margin-top:0;">1. Izaberite .xlsx fajl</h2>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'lager_uvoz', 'lager_uvoz_nonce' ); ?>
				<input type="file" name="catalog" accept=".xlsx" required>
				<?php submit_button( 'Učitaj i pregledaj', 'primary', 'lager_uvoz_upload', false ); ?>
			</form>
		</div>

		<?php if ( $preview ) : ?>
			<div class="card" style="max-width:820px;padding:16px 20px;margin-top:18px;">
				<h2 style="margin-top:0;">2. Pregled izmena</h2>
				<table class="widefat striped" style="max-width:520px;">
					<tbody>
						<tr><td>Redova u fajlu</td><td><strong><?php echo (int) $preview['total']; ?></strong></td></tr>
						<tr><td>Novi proizvodi</td><td><strong><?php echo (int) $preview['new_products']; ?></strong></td></tr>
						<tr><td>Ažuriraju se</td><td><strong><?php echo (int) $preview['upd_products']; ?></strong></td></tr>
						<tr><td>Artikli van fajla → stanje 0</td><td><strong><?php echo (int) $preview['discontinued']; ?></strong></td></tr>
						<tr><td>Nove kategorije (bez marže!)</td><td><strong><?php echo count( $preview['new_cats'] ); ?></strong></td></tr>
					</tbody>
				</table>
				<?php if ( $preview['new_cats'] ) : ?>
					<p><strong>Nove kategorije kojima treba ručno postaviti maržu:</strong></p>
					<ul style="list-style:disc;padding-left:22px;">
						<?php foreach ( $preview['new_cats'] as $code => $cn ) : ?>
							<li><?php echo esc_html( $cn . ( $code ? ' (' . $code . ')' : '' ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p style="color:#b00020;"><strong>Napomena:</strong> svi artikli kojih nema u fajlu biće postavljeni na stanje 0.
				Marža postojećih kategorija se ne menja. Uverite se da je ovo <em>kompletan</em> katalog.</p>

				<p>
					<button type="button" class="button button-primary" id="lager-uvoz-apply">Primeni izmene</button>
					<span id="lager-uvoz-status" style="margin-left:12px;"></span>
				</p>
				<div id="lager-uvoz-bar-wrap" style="display:none;background:#e4ecf8;border-radius:6px;height:18px;max-width:520px;overflow:hidden;">
					<div id="lager-uvoz-bar" style="background:#1B3E7A;height:100%;width:0;transition:width .2s;"></div>
				</div>
				<pre id="lager-uvoz-log" style="display:none;background:#0f1c36;color:#cfe;padding:12px;border-radius:6px;max-width:800px;max-height:220px;overflow:auto;margin-top:12px;"></pre>
			</div>

			<script>
			(function(){
				var btn = document.getElementById('lager-uvoz-apply');
				if (!btn) return;
				var total = <?php echo (int) $preview['total']; ?>;
				var batch = 150;
				var nonce = '<?php echo esc_js( wp_create_nonce( 'lager_uvoz_apply' ) ); ?>';
				var ajax = '<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>';
				var statusEl = document.getElementById('lager-uvoz-status');
				var barWrap = document.getElementById('lager-uvoz-bar-wrap');
				var bar = document.getElementById('lager-uvoz-bar');
				var log = document.getElementById('lager-uvoz-log');
				var sums = { created:0, updated:0, errors:0, zeroed:0 };
				function post(data){
					var body = new URLSearchParams(data);
					return fetch(ajax, { method:'POST', body: body, credentials:'same-origin' }).then(function(r){ return r.json(); });
				}
				function logline(t){ log.style.display='block'; log.textContent += t + "\n"; log.scrollTop = log.scrollHeight; }
				function step(offset){
					return post({ action:'lager_uvoz_batch', nonce:nonce, offset:offset, batch:batch }).then(function(res){
						if (!res || !res.success){ throw new Error(res && res.data ? res.data : 'Greška'); }
						var d = res.data;
						sums.created += d.created; sums.updated += d.updated; sums.errors += d.errors;
						var done = Math.min(d.next, total);
						bar.style.width = Math.round(done/total*100) + '%';
						statusEl.textContent = 'Obrađeno ' + done + ' / ' + total + ' (novih ' + sums.created + ', ažurirano ' + sums.updated + (sums.errors? ', greške ' + sums.errors : '') + ')';
						if (d.next < total){ return step(d.next); }
						return true;
					});
				}
				btn.addEventListener('click', function(){
					if (!confirm('Primeniti izmene na sve proizvode? Ovo menja bazu.')) return;
					btn.disabled = true; barWrap.style.display='block';
					statusEl.textContent = 'Obrada...';
					step(0).then(function(){
						statusEl.textContent = 'Proizvodi gotovi. Postavljam stanje 0 za artikle van fajla...';
						return post({ action:'lager_uvoz_finish', nonce:nonce });
					}).then(function(res){
						if (res && res.success){ sums.zeroed = res.data.zeroed; }
						bar.style.width='100%';
						statusEl.innerHTML = '<strong style="color:#1a7a3c;">Uvoz završen.</strong> Novih ' + sums.created + ', ažurirano ' + sums.updated + ', na stanje 0 ' + sums.zeroed + (sums.errors? ', greške ' + sums.errors : '') + '.';
						logline('Gotovo.');
					}).catch(function(e){
						statusEl.innerHTML = '<strong style="color:#b00020;">Greška:</strong> ' + e.message;
						btn.disabled = false;
					});
				});
			})();
			</script>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Analyze parsed rows (no writes): counts of new/updated products, new categories,
 * and how many existing products would be zeroed (not in the file).
 */
function lager_uvoz_analyze( $rows ) {
	global $wpdb;
	$file_skus = array();
	foreach ( $rows as $r ) {
		$file_skus[ (string) $r['sku'] ] = true;
	}
	// Existing product SKUs.
	$existing = $wpdb->get_col( "SELECT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_sku' AND p.post_type = 'product' AND p.post_status != 'trash'" );
	$existing_set = array();
	foreach ( $existing as $s ) {
		$existing_set[ (string) $s ] = true;
	}
	// Existing category codes.
	$codes = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'sifra'" );
	$code_set = array();
	foreach ( $codes as $c ) {
		$code_set[ (string) $c ] = true;
	}

	$new_products = 0;
	$upd_products = 0;
	$new_cats     = array();
	foreach ( $rows as $r ) {
		if ( isset( $existing_set[ (string) $r['sku'] ] ) ) {
			$upd_products++;
		} else {
			$new_products++;
		}
		if ( '' !== $r['code'] && ! isset( $code_set[ (string) $r['code'] ] ) && ! isset( $new_cats[ $r['code'] ] ) ) {
			$new_cats[ $r['code'] ] = $r['catname'];
		}
	}
	$discontinued = 0;
	foreach ( $existing_set as $s => $_ ) {
		if ( ! isset( $file_skus[ $s ] ) ) {
			$discontinued++;
		}
	}
	return array(
		'total'        => count( $rows ),
		'new_products' => $new_products,
		'upd_products' => $upd_products,
		'new_cats'     => $new_cats,
		'discontinued' => $discontinued,
	);
}

/* ------------------------------------------------------------------ *
 *  AJAX: batched apply.
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_lager_uvoz_batch', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'lager_uvoz_apply', 'nonce', false ) ) {
		wp_send_json_error( 'Neovlašćeno.' );
	}
	@set_time_limit( 0 );
	$rows = get_transient( lager_uvoz_key() );
	if ( ! is_array( $rows ) ) {
		wp_send_json_error( 'Sesija je istekla, učitajte fajl ponovo.' );
	}
	$offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
	$batch  = isset( $_POST['batch'] ) ? min( 500, max( 1, (int) $_POST['batch'] ) ) : 150;
	$slice  = array_slice( $rows, $offset, $batch );

	$created = 0;
	$updated = 0;
	$errors  = 0;
	$new_cats = array();
	foreach ( $slice as $r ) {
		$cat = lager_uvoz_category( $r['code'], $r['catname'], $new_cats );
		$tid = $cat ? (int) $cat[0] : 0;
		$res = lager_uvoz_product( $r, $tid );
		if ( 'created' === $res ) {
			$created++;
		} elseif ( 'updated' === $res ) {
			$updated++;
		} else {
			$errors++;
		}
	}
	wp_send_json_success( array(
		'created' => $created,
		'updated' => $updated,
		'errors'  => $errors,
		'next'    => $offset + count( $slice ),
	) );
} );

add_action( 'wp_ajax_lager_uvoz_finish', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'lager_uvoz_apply', 'nonce', false ) ) {
		wp_send_json_error( 'Neovlašćeno.' );
	}
	@set_time_limit( 0 );
	$rows = get_transient( lager_uvoz_key() );
	if ( ! is_array( $rows ) ) {
		wp_send_json_error( 'Sesija je istekla.' );
	}
	$file_skus = array();
	foreach ( $rows as $r ) {
		$file_skus[ (string) $r['sku'] ] = true;
	}
	global $wpdb;
	$pairs = $wpdb->get_results( "SELECT p.ID, pm.meta_value AS sku FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sku' WHERE p.post_type = 'product' AND p.post_status = 'publish'" );
	$zeroed = 0;
	foreach ( $pairs as $row ) {
		if ( ! isset( $file_skus[ (string) $row->sku ] ) ) {
			$product = wc_get_product( (int) $row->ID );
			if ( $product && (float) $product->get_stock_quantity() !== 0.0 ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( 0 );
				$product->save();
				$zeroed++;
			}
		}
	}
	delete_transient( lager_uvoz_key() );
	wp_send_json_success( array( 'zeroed' => $zeroed ) );
} );
