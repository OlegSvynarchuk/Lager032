<?php
/**
 * Plugin Name: Lager — Uvoz cenovnika (Excel)
 * Description: Admin alat za uvoz/ažuriranje kataloga proizvoda iz .xlsx fajla.
 *              Uparuje po šifri (SKU), kategorije po šifri kategorije (marža se čuva),
 *              računa neto cenu preko lager_reprice_product(), a artikle kojih nema u fajlu
 *              trajno briše. Mesto: Proizvodi → Uvoz cenovnika.
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
 * Look up a product_cat by its Croonus code (term meta `sifra`).
 *
 * Deliberately a meta_query and not the `meta_key`/`meta_value` shorthand: on
 * WP 7.1 that shorthand silently returns an empty array for term queries, so
 * every code lookup here failed and fell through to matching by name. That is
 * why renaming a category in WordPress used to make the next import create a
 * duplicate instead of updating the existing one.
 *
 * @param string $code e.g. "01.15"
 * @return int Term ID, or 0 when no category carries that code.
 */
function lager_uvoz_term_by_sifra( $code ) {
	if ( '' === (string) $code ) {
		return 0;
	}

	$found = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'number'     => 1,
		'fields'     => 'ids',
		'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'key'     => 'sifra',
				'value'   => (string) $code,
				'compare' => '=',
			),
		),
	) );

	return ( ! is_wp_error( $found ) && $found ) ? (int) $found[0] : 0;
}

/**
 * Find (or create) a product_cat by its code (term meta `sifra`). New categories
 * are created under the parent implied by their code, with marža 0 and no image
 * (both set by hand afterwards). Marža is never overwritten. Returns [term_id, is_new] or null.
 */
function lager_uvoz_category( $code, $name, &$new_cats ) {
	static $cache = array();
	$ck = $code . '|' . $name;
	if ( isset( $cache[ $ck ] ) ) {
		return $cache[ $ck ];
	}
	$term_id = 0;

	if ( '' !== $code ) {
		$found = lager_uvoz_term_by_sifra( $code );
		if ( $found ) {
			$term_id = $found;
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
		/*
		 * Croonus codes carry the hierarchy: 01.15 belongs under 01.00. Created
		 * flat, a new subcategory lands at the top level beside "Ležaj" instead
		 * of inside it, and someone has to re-file it by hand afterwards.
		 */
		$parent = 0;
		if ( preg_match( '/^(\d+)\.(\d+)$/', (string) $code, $m ) && '00' !== $m[2] ) {
			$parent = lager_uvoz_term_by_sifra( $m[1] . '.00' );
		}

		$res = wp_insert_term(
			$name ? $name : ( 'Kategorija ' . $code ),
			'product_cat',
			$parent ? array( 'parent' => $parent ) : array()
		);
		if ( is_wp_error( $res ) ) {
			$cache[ $ck ] = null;
			return null;
		}
		$term_id = (int) $res['term_id'];
		if ( '' !== $code ) {
			update_term_meta( $term_id, 'sifra', $code );
		}

		/*
		 * Marža 0 on creation — the client's decision. It means the products go
		 * on sale straight away rather than waiting for someone to notice them,
		 * but at marža 0 the price equals VP, so they sell at cost until the real
		 * marža is entered on the category. Entering it re-prices every product
		 * in the category automatically (lager-auto-reprice.php).
		 *
		 * Stored as 0 rather than left empty deliberately: an empty marža yields
		 * no price at all, and the product would be orderable at 0 RSD.
		 *
		 * These categories are listed in the import preview so they do not sit
		 * unnoticed at zero margin.
		 */
		update_term_meta( $term_id, 'marza', 0 );

		// No image either — the Excel carries none, so it is set by hand later.
		$new_cats[ $code . '' ] = $name; // flag: needs marža + sličica
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
		/*
		 * A category with no marža cannot produce a price: lager_reprice_product()
		 * returns early, and the product would go live with an empty price —
		 * orderable for 0 RSD. New products in such a category start as drafts;
		 * the preview lists the categories still waiting for a marža, and setting
		 * it re-prices them (they still need publishing by hand, deliberately).
		 */
		$has_marza = $term_id && '' !== (string) get_term_meta( $term_id, 'marza', true );

		$product = new WC_Product_Simple();
		$product->set_sku( $row['sku'] );
		$product->set_status( $has_marza ? 'publish' : 'draft' );
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
						<tr><td style="color:#b00020;">Artikli van fajla → <strong>trajno se brišu</strong></td><td><strong style="color:#b00020;"><?php echo (int) $preview['discontinued']; ?></strong></td></tr>
						<tr><td>Nove kategorije (marža 0 — prodaja po nabavnoj ceni!)</td><td><strong><?php echo count( $preview['new_cats'] ); ?></strong></td></tr>
					</tbody>
				</table>
				<?php if ( $preview['new_cats'] ) : ?>
					<p style="background:#fff8e5;border-left:4px solid #dba617;padding:8px 12px;">
					<strong>Nove kategorije se kreiraju sa maržom 0 i bez sličice.</strong>
					Njihovi proizvodi su <em>odmah vidljivi</em> u prodavnici, ali se sa maržom 0
					<strong>prodaju po nabavnoj ceni</strong> (cena = veleprodajna cena + PDV).
					Postavite maržu na svakoj od ovih kategorija — cene svih njihovih proizvoda
					se tada automatski preračunavaju:</p>
					<ul style="list-style:disc;padding-left:22px;">
						<?php foreach ( $preview['new_cats'] as $code => $cn ) : ?>
							<li><?php echo esc_html( $cn . ( $code ? ' (' . $code . ')' : '' ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p style="color:#b00020;border-left:4px solid #b00020;padding:8px 12px;background:#fdf2f2;">
					<strong>Pažnja — brisanje je trajno.</strong> Svaki artikal kojeg nema u fajlu biće obrisan
					zajedno sa svojom adresom (URL), slikama i vezom ka ranijim porudžbinama. Ovo se ne može poništiti.
					Uverite se da je ovo <em>kompletan</em> katalog, a ne delimičan izvoz.
					Marža postojećih kategorija se ne menja.
				</p>

				<?php if ( (int) $preview['discontinued'] > 0 ) : ?>
					<p style="background:#fff8e5;border-left:4px solid #dba617;padding:8px 12px;">
						<label style="font-weight:600;">
							<input type="checkbox" id="lager-uvoz-confirm">
							Potvrđujem trajno brisanje <?php echo (int) $preview['discontinued']; ?> artikala kojih nema u fajlu.
						</label>
					</p>
				<?php endif; ?>

				<p>
					<button type="button" class="button button-primary" id="lager-uvoz-apply"<?php echo (int) $preview['discontinued'] > 0 ? ' disabled' : ''; ?>>Primeni izmene</button>
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
				var sums = { created:0, updated:0, errors:0, deleted:0 };
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
				// Deletion is permanent, so the button stays locked until the
				// count has been explicitly acknowledged.
				var confirmBox = document.getElementById('lager-uvoz-confirm');
				if (confirmBox){
					confirmBox.addEventListener('change', function(){ btn.disabled = !confirmBox.checked; });
				}

				// Delete in chunks; the server reports what is still outstanding.
				function purge(){
					return post({ action:'lager_uvoz_finish', nonce:nonce, confirm_delete: confirmBox && confirmBox.checked ? 1 : 0 })
						.then(function(res){
							if (!res || !res.success){ throw new Error(res && res.data ? res.data : 'Greška'); }
							sums.deleted += res.data.deleted;
							if (res.data.remaining > 0){
								statusEl.textContent = 'Brisanje artikala van fajla... obrisano ' + sums.deleted + ', preostalo ' + res.data.remaining;
								return purge();
							}
							return true;
						});
				}

				btn.addEventListener('click', function(){
					if (!confirm('Primeniti izmene? Artikli van fajla biće TRAJNO obrisani. Ovo se ne može poništiti.')) return;
					btn.disabled = true; barWrap.style.display='block';
					statusEl.textContent = 'Obrada...';
					step(0).then(function(){
						statusEl.textContent = 'Proizvodi gotovi. Brišem artikle van fajla...';
						return purge();
					}).then(function(){
						bar.style.width='100%';
						statusEl.innerHTML = '<strong style="color:#1a7a3c;">Uvoz završen.</strong> Novih ' + sums.created + ', ažurirano ' + sums.updated + ', obrisano ' + sums.deleted + (sums.errors? ', greške ' + sums.errors : '') + '.';
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
 * and how many existing products would be deleted (not in the file).
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
	$pairs = $wpdb->get_results(
		"SELECT p.ID, pm.meta_value AS sku
		   FROM {$wpdb->posts} p
		   INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sku'
		  WHERE p.post_type = 'product' AND p.post_status <> 'trash'"
	);

	$doomed = array();
	foreach ( $pairs as $row ) {
		if ( ! isset( $file_skus[ (string) $row->sku ] ) ) {
			$doomed[] = (int) $row->ID;
		}
	}

	$total   = count( $pairs );
	$confirm = ! empty( $_POST['confirm_delete'] );

	/*
	 * Refuse an implausible mass deletion unless it was explicitly confirmed.
	 * The failure this guards against is a partial export — one category
	 * exported by mistake, or a truncated file — which would otherwise wipe the
	 * rest of the catalogue with no way back. Deletion is permanent: product
	 * IDs, URLs, images and the links from past orders all go with it.
	 */
	if ( ! $confirm && $total > 0 && count( $doomed ) > ( $total * 0.30 ) ) {
		wp_send_json_error( sprintf(
			'Zaustavljeno radi sigurnosti: ovaj fajl bi trajno obrisao %d od %d proizvoda (preko 30%%). '
			. 'Ako je fajl zaista kompletan katalog, označite potvrdu za brisanje i pokušajte ponovo.',
			count( $doomed ),
			$total
		) );
	}

	// Deleting is far heavier than an update, so cap each call and let the
	// browser loop; the next call recomputes what is left, so it self-corrects.
	$deleted = 0;
	foreach ( array_slice( $doomed, 0, 100 ) as $id ) {
		$product = wc_get_product( $id );
		if ( $product && $product->delete( true ) ) {
			$deleted++;
		}
	}

	$remaining = max( 0, count( $doomed ) - $deleted );

	if ( 0 === $remaining ) {
		delete_transient( lager_uvoz_key() );
	}

	wp_send_json_success( array(
		'deleted'   => $deleted,
		'remaining' => $remaining,
	) );
} );
