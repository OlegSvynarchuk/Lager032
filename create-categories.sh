#!/usr/bin/env bash
# Create WooCommerce product categories (product_cat) for Lager032
# Generated from Kategorije.xlsx. Idempotent: safe to re-run.
# Sets term meta: sifra, marza (+ ACF reference keys).
set -euo pipefail

# Path to the WordPress install on the server:
WP_PATH="${WP_PATH:-/home/pixelspi/public_html/lager032.pixels2pixels.ch}"
WP="wp --path=$WP_PATH"

# ACF field keys (must match the field group in lager-category-fields.php)
FK_SIFRA="field_lager_sifra"
FK_MARZA="field_lager_marza"

ensure_term() {  # name slug parent_id  -> echoes term_id
  local name="$1" slug="$2" parent="$3" id
  id=$($WP term list product_cat --slug="$slug" --field=term_id --hide_empty=false 2>/dev/null | head -n1)
  if [ -z "$id" ]; then
    if [ -n "$parent" ]; then
      id=$($WP term create product_cat "$name" --slug="$slug" --parent="$parent" --porcelain)
    else
      id=$($WP term create product_cat "$name" --slug="$slug" --porcelain)
    fi
    echo "  + created  $slug -> $id" >&2
  else
    echo "  = exists   $slug -> $id" >&2
  fi
  printf "%s" "$id"
}

set_meta() {  # term_id sifra marza
  $WP term meta update "$1" sifra "$2"  >/dev/null
  $WP term meta update "$1" _sifra "$FK_SIFRA" >/dev/null
  $WP term meta update "$1" marza "$3"  >/dev/null
  $WP term meta update "$1" _marza "$FK_MARZA" >/dev/null
}

echo "Creating product categories in $WP_PATH ..."

# ---- 01.00  Ležaj ----
PID=$(ensure_term "Ležaj" "lezaj" "")
set_meta "$PID" "01.00" "60"
CID=$(ensure_term "Ležaj - konusno valjkasti" "lezaj-konusno-valjkasti" "$PID")
set_meta "$CID" "01.01" "60"
CID=$(ensure_term "Ležaj - aksijalni" "lezaj-aksijalni" "$PID")
set_meta "$CID" "01.02" "60"
CID=$(ensure_term "Ležaj - zglobni" "lezaj-zglobni" "$PID")
set_meta "$CID" "01.03" "60"
CID=$(ensure_term "Ležaj - igličasti" "lezaj-iglicasti" "$PID")
set_meta "$CID" "01.04" "60"
CID=$(ensure_term "Ležaj - buričasti" "lezaj-buricasti" "$PID")
set_meta "$CID" "01.05" "60"
CID=$(ensure_term "Ležaj - ravno valjkasti" "lezaj-ravno-valjkasti" "$PID")
set_meta "$CID" "01.06" "60"
CID=$(ensure_term "Ležaj - linearni" "lezaj-linearni" "$PID")
set_meta "$CID" "01.07" "60"
CID=$(ensure_term "Ležaj - samopodesivi kuglični" "lezaj-samopodesivi-kuglicni" "$PID")
set_meta "$CID" "01.08" "60"
CID=$(ensure_term "Ležaj - klima" "lezaj-klima" "$PID")
set_meta "$CID" "01.09" "70"
CID=$(ensure_term "Ležaj - dvoredni sa kosim dodirom" "lezaj-dvoredni-sa-kosim-dodirom" "$PID")
set_meta "$CID" "01.10" "60"
CID=$(ensure_term "Ležaj - dvoredni kruti" "lezaj-dvoredni-kruti" "$PID")
set_meta "$CID" "01.11" "60"
CID=$(ensure_term "Ležaj - sa kosim dodirom" "lezaj-sa-kosim-dodirom" "$PID")
set_meta "$CID" "01.12" "60"
CID=$(ensure_term "Ležaj - igličasti aksijalni" "lezaj-iglicasti-aksijalni" "$PID")
set_meta "$CID" "01.13" "60"
CID=$(ensure_term "Ležaj - jednosmerni" "lezaj-jednosmerni" "$PID")
set_meta "$CID" "01.14" "60"
CID=$(ensure_term "Ležaj - auto program" "lezaj-auto-program" "$PID")
set_meta "$CID" "01.15" "70"
CID=$(ensure_term "Ležaj - kućišta" "lezaj-kucista" "$PID")
set_meta "$CID" "01.16" "60"
CID=$(ensure_term "Ležaj - igličasti jednosmerni" "lezaj-iglicasti-jednosmerni" "$PID")
set_meta "$CID" "01.17" "0"
CID=$(ensure_term "Ležaj - male dimenzije" "lezaj-male-dimenzije" "$PID")
set_meta "$CID" "01.18" "60"
CID=$(ensure_term "Ležaj za kućišta" "lezaj-za-kucista" "$PID")
set_meta "$CID" "01.19" "60"
CID=$(ensure_term "Ležaj - colovni kuglični" "lezaj-colovni-kuglicni" "$PID")
set_meta "$CID" "01.20" "60"
CID=$(ensure_term "Ležaj - druk ležajevi" "lezaj-druk-lezajevi" "$PID")
set_meta "$CID" "01.21" "60"
CID=$(ensure_term "Ležaj - za balirke" "lezaj-za-balirke" "$PID")
set_meta "$CID" "01.22" "60"

# ---- 02.00  Remen ----
PID=$(ensure_term "Remen" "remen" "")
set_meta "$PID" "02.00" "60"
CID=$(ensure_term "Remen - zupčasti" "remen-zupcasti" "$PID")
set_meta "$CID" "02.01" "70"
CID=$(ensure_term "Remen - POLY V" "remen-poly-v" "$PID")
set_meta "$CID" "02.02" "70"
CID=$(ensure_term "Remen - pljosnati" "remen-pljosnati" "$PID")
set_meta "$CID" "02.03" "70"
CID=$(ensure_term "Remen - varijator" "remen-varijator" "$PID")
set_meta "$CID" "02.04" "50"
CID=$(ensure_term "Remenica" "remenica" "$PID")
set_meta "$CID" "02.10" "40"

# ---- 03.00  Semering ----
PID=$(ensure_term "Semering" "semering" "")
set_meta "$PID" "03.00" "70"

# ---- 06.00  Ostalo ----
PID=$(ensure_term "Ostalo" "ostalo" "")
set_meta "$PID" "06.00" "50"

# ---- 07.00  Seger ----
PID=$(ensure_term "Seger" "seger" "")
set_meta "$PID" "07.00" "50"

# ---- 08.00  Klin ----
PID=$(ensure_term "Klin" "klin" "")
set_meta "$PID" "08.00" "50"

# ---- 09.00  Osigurač ----
PID=$(ensure_term "Osigurač" "osigurac" "")
set_meta "$PID" "09.00" "30"

# ---- 10.00  Masti ----
PID=$(ensure_term "Masti" "masti" "")
set_meta "$PID" "10.00" "40"

# ---- 11.00  Elastična Čivija ----
PID=$(ensure_term "Elastična Čivija" "elasticna-civija" "")
set_meta "$PID" "11.00" "50"

# ---- 12.00  Kuglica ----
PID=$(ensure_term "Kuglica" "kuglica" "")
set_meta "$PID" "12.00" "50"

# ---- 13.00  Navrtka ----
PID=$(ensure_term "Navrtka" "navrtka" "")
set_meta "$PID" "13.00" "50"

# ---- 14.00  Lanci i lančanici ----
PID=$(ensure_term "Lanci i lančanici" "lanci-i-lancanici" "")
set_meta "$PID" "14.00" "60"

# ---- 16.00  Mazalice ----
PID=$(ensure_term "Mazalice" "mazalice" "")
set_meta "$PID" "16.00" "50"

# ---- 18.00  Hilzna ----
PID=$(ensure_term "Hilzna" "hilzna" "")
set_meta "$PID" "18.00" "70"

# ---- 19.00  WURTH ----
PID=$(ensure_term "WURTH" "wurth" "")
set_meta "$PID" "19.00" "30"

# ---- 20.00  Krst Kardana ----
PID=$(ensure_term "Krst Kardana" "krst-kardana" "")
set_meta "$PID" "20.00" "60"

# ---- 21.00  Loctite ----
PID=$(ensure_term "Loctite" "loctite" "")
set_meta "$PID" "21.00" "30"

# ---- 22.00  Alati ----
PID=$(ensure_term "Alati" "alati" "")
set_meta "$PID" "22.00" "30"

# ---- 23.00  Spojnice ----
PID=$(ensure_term "Spojnice" "spojnice" "")
set_meta "$PID" "23.00" "40"

# ---- 24.00  Ulja ----
PID=$(ensure_term "Ulja" "ulja" "")
set_meta "$PID" "24.00" "30"

# ---- 25.00  Valjčić ----
PID=$(ensure_term "Valjčić" "valjcic" "")
set_meta "$PID" "25.00" "40"

# ---- 26.00  Iglice ----
PID=$(ensure_term "Iglice" "iglice" "")
set_meta "$PID" "26.00" "40"

echo "Done. Flushing rewrite + cache..."
$WP rewrite flush >/dev/null 2>&1 || true
$WP cache flush   >/dev/null 2>&1 || true
echo "All categories created."
