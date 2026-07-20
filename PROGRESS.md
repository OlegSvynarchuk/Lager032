# Lager032 — Project Progress

> Working notes for the Lager032 WooCommerce migration. Commit this file so both
> laptops stay in sync. See [workflow.md](workflow.md) for the full SSH/deploy reference.
> Last updated: 2026-06-25.

---

## 1. What this project is

Migrating a product catalog from the **Croonus** admin into a **WordPress + WooCommerce**
site. Product list (with prices) was exported from Croonus to Excel, converted to
[products.json](products.json), and imported into WordPress.

- **Site:** https://lager032.pixels2pixels.ch — **remote-only** (WordPress + DB live on the server)
- **Server:** `pixelspi@162.55.0.170` port `22222`, key `~/.ssh/devkey`
- **Remote path:** `/home/pixelspi/public_html/lager032.pixels2pixels.ch`
- **WP:** core 7.0 · WP-CLI 2.12.0 · WooCommerce 10.8.1 · currency RSD, PDV 20%

---

## 2. The pricing model (important — read before touching prices)

Final shelf price is built in layers. The marža is baked into the stored price; PDV is added on top by Woo at display time.

```
net (Woo regular_price) = round( VP × (1 + marža/100), 2 )
displayed price         = net × 1.20      ← PDV 20% added by Woo on display
```

- **VP** (`vp`) — Veleprodajna cena = base price, ex-marža, ex-PDV. ACF field on the product.
- **Marža** (`marza`) — margin %, **set per category** (term meta), snapshotted onto each product.
- **net** — `VP × (1+marža/100)` → written to Woo's `regular_price` / `_price`.
- **PDV 20%** — NOT stored in the price; Woo adds it on display
  (`prices_include_tax=no`, `tax_display_shop=incl`).

The **category** marža governs the price. A product also has its own marža field, but the
reprice logic ignores it and overwrites it back to the category value.

---

## 3. Code map (all live as mu-plugins on the server)

These run from `wp-content/mu-plugins/` on the server. Local copies live in this folder.

| File | Role |
|---|---|
| [setup-woo-pricing.php](setup-woo-pricing.php) | One-time Woo setup: RSD, 0 decimals, 20% PDV rate, net-entry/incl-display. Run via `wp eval-file`. |
| [lager-category-fields.php](lager-category-fields.php) | ACF "Šifra" + "Marža (%)" fields on product categories; adds those columns to the category list. |
| [lager-import-helpers.php](lager-import-helpers.php) | ACF "VP" + "Marža" fields on products; decimal stock; "Net cena (bez PDV)" column on the products list. |
| [lager-auto-reprice.php](lager-auto-reprice.php) | Recomputes net price from VP × category marža. Triggers on category/product save. |
| [import-products.php](import-products.php) | The importer. Reads `products.json`, sets status **draft**, applies category marža → net price. |
| [create-categories.sh](create-categories.sh) | Creates the product categories from the Croonus codes. |
| [export_products.py](export_products.py) | Builds `products.json` from the Excel export. |
| [verify-import.php](verify-import.php) | Post-import sanity checks. |

---

## 4. Current state

- **4,934 products imported**, ALL in **draft** (4,931 in-stock, 3 out-of-stock).
  - **NOT published yet** — deliberate. Images are still WooCommerce placeholders.
- Products are well-formed: name, SKU, price, category, visibility all set.
- Active theme is `twentytwentyfive` — the custom `lager032` theme is **not built yet**.

---

## 5. Changes made 2026-06-09

1. **Reprice on any save path** — [lager-auto-reprice.php](lager-auto-reprice.php) now also hooks
   `woocommerce_update_product` + `woocommerce_new_product`, so editing the base price (VP)
   recalculates the net price no matter how the product is saved (product editor, Quick Edit,
   Bulk Edit, CLI/REST) — not just via the ACF product screen.
   *Verified live:* VP 3304.91 → net 4296.38; VP 3404.91 → net 4426.38 (auto), then restored.
2. **"Net cena (bez PDV)" column width** — [lager-import-helpers.php](lager-import-helpers.php) adds a
   CSS width/align rule so the column stops stretching the products table. Confirmed OK.

Both deployed to the server and PHP-lint clean.

---

## 6. How to deploy mu-plugin changes (current manual method)

No automated sync yet. After editing a file locally, upload it:

```powershell
scp -i "$env:USERPROFILE\.ssh\devkey" -P 22222 `
  "lager-auto-reprice.php" `
  "pixelspi@162.55.0.170:/home/pixelspi/public_html/lager032.pixels2pixels.ch/wp-content/mu-plugins/"
```

Then lint on the server:

```powershell
ssh -i "$env:USERPROFILE\.ssh\devkey" -p 22222 pixelspi@162.55.0.170 `
  "php -l /home/pixelspi/public_html/lager032.pixels2pixels.ch/wp-content/mu-plugins/lager-auto-reprice.php"
```

---

## 7. Open / next up

- [ ] Publish the 4,934 draft products (when images / data are ready). On hold by choice.
- [ ] Build the custom `lager032` theme (not started; `twentytwentyfive` active for now).
- [ ] Real product images (currently placeholders).
- [ ] Optional: a `sync-muplugins` helper so deploying isn't a manual `scp` each time.

---

## 8. Laptop / environment notes

- **SSH key** `~/.ssh/devkey` must exist on each laptop (it is NOT in this repo — keep it out of git).
- Local root differs per machine. Current laptop: `C:\Users\Pixels2Pixels\Projects\lager`
  (old laptop was `C:\Users\Harmonity\Local Sites\lager`). [workflow.md](workflow.md) paths are
  set to the current laptop.
- This folder holds the migration scripts only — there is no local WordPress/`wp-content` tree.

---

## 9. Planned: Excel upload tool (client workflow) — design + decisions

Client clarification (2026-06-09): the catalog is refreshed by uploading an Excel file
exported from their accounting (knjigovodstveni) program. Categories are (re)formed on
every upload. Price formula confirmed: **VP cena + marža kategorije + 20% PDV = prodajna cena**
— this already matches the built model exactly.

### Excel format (6 columns, confirmed against export_products.py)

| Col | Excel (client) | Field | Notes |
|---|---|---|---|
| 1 | Šifra artikla | SKU | **match key** for upsert |
| 2 | Šifra kategorije | category `sifra` (code) | links product → category |
| 3 | Naziv kategorije | category name | drives category create/update |
| 4 | Naziv artikla | product title | |
| 5 | Količina (stanje) | stock | decimal allowed |
| 6 | VP cena | base price (`vp`) | |

> **No marža column.** Marža lives only in WP (per category). The upload must
> **preserve existing marža** and only flag NEW categories as needing a margin.
> Never overwrite marža from an upload.

### Decisions (2026-06-09)

- **Mechanism:** custom **WordPress admin upload tool** (self-service in wp-admin) — chosen
  over CLI-only or full accounting sync.
- **Discontinued products** (in WP but absent from the new Excel): **set out-of-stock**
  (keep page + URL, stanje = 0). Not deleted, not drafted.
- **Marža source:** maintained **in WP** (per-category term meta) — already set for the
  current categories. Upload **preserves** it; never overwritten from a product file.
- **New category** appearing in a future upload (no marža yet): **create + flag** — create
  the category so products attach, leave marža empty, and list it in the upload summary as
  "needs marža". Operator sets it in WP before those products go live.
- **Accounting↔Woo API sync: DROPPED.** Client (2026-06-09) confirmed low turnover and
  does NOT want direct-from-program integration. The Excel upload tool is the whole solution.
- **Cadence / scale:** product types are stable; new categories appear ~once a year. Client
  uploads the Excel themselves; it must be a simple **2–3 minute** self-service procedure.
- **File format:** accept **.xlsx directly** (no CSV step). Use a single-file reader
  (**SimpleXLSX** by shuchkin, MIT) bundled in the plugin — no Composer needed.
- **New products:** **publish immediately** on upload (client wants upload-and-done).

### Tool spec (to build)

A small mu-plugin admin page ("Lager → Upload") that, on one .xlsx upload:
1. Upsert **categories** from cols 2–3 (create missing; **preserve marža**; mark new ones
   marža-empty for manual entry).
2. Upsert **products** by SKU (cols 1,3→cat,4,5,6): update name, category, stock, VP.
3. Recompute net price via existing reprice logic (`lager_reprice_product`).
4. **Discontinued → out-of-stock** (SKUs in WP but not in this file).
5. Show a summary: created / updated / new-categories-needing-marža / set-out-of-stock.

### Resolved / remaining

- xlsx parsing — RESOLVED: accept .xlsx via SimpleXLSX (single file, MIT).
- New-product status — RESOLVED: publish immediately.
- Header row — assume present (export_products.py skips row 0). Confirm vs real file.
- Number locale — VP/stock may use comma decimals; reader must normalize "," → "."
  (export_products.py already does this).
- STILL WANTED: the client's actual example .xlsx to lock exact column order + sheet name
  before/while building. Existing `Lager za WEB 13-Maj-26.xlsx` is assumed same format.

### Build plan (ready to start)

mu-plugin `lager-upload.php` + bundled `SimpleXLSX.php`:
1. Admin page **WooCommerce → Lager Upload** (or top-level) with a file field (cap: `manage_woocommerce`).
2. On submit: parse .xlsx → rows of {sku, code, catname, name, stock, vp}.
3. Categories: match by `sifra` (code); create-if-new (name from col 3), **preserve marža**,
   collect new-without-marža for the summary.
4. Products: upsert by SKU — set name, category, stock, vp; recompute net via
   `lager_reprice_product()`; **publish** (new + existing).
5. Discontinued: SKUs in WP (product_cat tree) not in this file → set out-of-stock.
6. Summary screen: created / updated / published / out-of-stock / categories-needing-marža.
7. Safety: dry-run preview toggle; chunked processing for ~5k rows; nonce + cap checks.

---

## 10. Theme: custom `lager032` (2026-06-11)

Custom **classic** WordPress theme built from the Figma "Home" design (file key
`rkOC41hpF2Dx1HR93xt0Fb`; see [FIGMA.md](FIGMA.md) for nodes / tokens / fetch helpers).
Minimal, no page builder; WooCommerce + ACF only.

**Structure:** `lager032/` — `style.css`, `functions.php`, `inc/{setup,icons,enqueue}.php`,
`header.php`, `front-page.php`, `footer.php`, `index.php`, `assets/{css,img}`. Tokens: navy
`#112955`, red `#D60000`, nav ink `#1C290D`; fonts Lato / Inter / Roboto Condensed.

**Homepage sections:**
- ✅ Utility bar, header/nav, hero, brand strip (SKF/Würth/NTN/SNR), "Naša ponuda" 4×3 category grid.
  - Category cards use a shared **placeholder image** as background until categories get real
    images: `categoryplaceholder.jpg` → WP Media (attachment **4948**), id stored in option
    `lager_cat_placeholder_id`; `front-page.php` falls back to it per card (real category
    thumbnail overrides). Verify via Coming-soon preview link (not public yet).
- ⏳ About ("Vaš partner u industrijskoj nabavci"), Contact ("Za sva pitanja kontaktirajte nas" + forma), full footer (simple version live for now).

**Deployed & live:** scp to `wp-content/themes/lager032/`, then `wp theme activate lager032`.
Created page **Početna** (ID 4947), set as static front page (`show_on_front=page`,
`page_on_front=4947`).

**Deploy gotchas (important — see also memory):**
1. scp from Windows makes server dirs `0700` → LiteSpeed returns **404 for every theme
   asset** (CSS/img). Fix after each scp:
   `find <theme> -type d -exec chmod 755 {} \;` and `… -type f -exec chmod 644 {} \;`.
2. Site is in **WooCommerce "Coming soon"** mode (`woocommerce_coming_soon=yes`,
   `store_pages_only=no`) — it replaces the whole front end with the coming-soon block, so
   the theme looks blank/broken but is fine. Preview privately via
   `?woo-share=<woocommerce_share_key>` after `woocommerce_private_link=yes`.
   Do **not** disable coming-soon without the client's OK (it exposes the live site).

**Next:** About + Contact + full footer; then product archive + single product
(Figma nodes `47:2256` / `47:2291`).

---

## 11. Catalog / shop UX direction (DECIDED 2026-06-11)

**Model: search-first, faceted, dense-list catalog** — the Digi-Key / Mouser / RS Components /
Misumi pattern for parts/MRO sites. NOT a photo-grid consumer storefront.

**Why (not just "modern for modern's sake"):** ~4,934 industrial parts identified by codes/specs,
no real photos; buyers already know the part (e.g. `6204 2RS`, `02872/02820 SKF`) and want to
find → check stock+price → order fast. Photos don't sell commodity parts; speed + accuracy do.
The competitors' "outdated" text-list paradigm is actually *fit-for-purpose* — they just execute
it poorly. The win is doing search-first **excellently**, not adding image-grid archives.

**Primary experience — search + filter + dense list:**
- **Instant/typeahead search** by name AND SKU (stock + price inline).
- **Smart matching:** partial codes, spacing/diacritics, brand synonyms/cross-refs (SKF↔FAG).
- **Facets/filters:** brand, in-stock-only, category (later: dimensions ID/OD/width).
- **Dense scannable results:** code · name · brand · stock · price · qty · add-to-cart;
  responsive (rows → cards on mobile). Small thumbnail optional/later — layout independent of photos.

**Secondary — category archives (browse fallback + SEO):**
- Hierarchical drill-down kept but NOT the centerpiece: Shop → parent category cards →
  child-category cards (if children) → product list (leaf). For users who don't know the code,
  and because category pages rank for "ležaj …" queries (real SEO traffic).
- `taxonomy-product_cat.php`: if term has children → child cards; else → product list.

**B2B conveniences (sales levers > imagery):** quick reorder, bulk/paste-list order,
downloadable price list, live stock qty, "list updated" date.

**Home:** a few visual category cards (existing `cat-grid`) as a friendly entry; real work on Shop.

**Verify** via Coming-soon preview link (`?woo-share=<key>`), front end hidden until launch (see §10).

### Category numbers (live, 2026-06-11)
50 product categories → **23 parents** (incl. default `Uncategorized`) + **27 subcategories**
(mostly under Ležaj ~22, Remen ~5). All 4,934 products currently **draft**.

---

## 12. Theme REDESIGN — new concept (2026-06-12)

Designer reworked the concept. New Figma nodes (file `rkOC41hpF2Dx1HR93xt0Fb`):
- **Header** `106:3461` · **"Svi proizvodi" mega-dropdown** `106:3506` · **Homepage** `110:2027`.

The new concept **strongly matches the parts-sale / search-first model** (§11): prominent
search by *name OR šifra*, "Svi proizvodi" category mega-menu, filter dropdowns, article counts.
Main deviation: homepage shows image product **cards** (grid), not a dense list — a marketing
showcase; placeholder-image repetition is a known caveat until real photos exist.

**New header:** utility bar (phones · lager032@gmail.com · Kneza Miloša 100, Čačak), logo
**LAGER STR ČAČAK·SRBIJA**, red **Svi proizvodi** mega-dropdown, nav (Početna · O Nama ·
Sertifikati · Kontakt), **Pretraži artikle** search, **Korpa** cart.

**Mega-dropdown:** 12 categories × 2 cols, each name + subcategory-summary subtitle, "Sve kategorije →".

**New homepage sections:** Hero → Kategorije (6 featured cards w/ counts: Ležajevi 1.200+,
Kućišta 400+, Semerinzi 320+, Lanci 240+, Remenje 180+, Masti 60+) → **Proizvodi** (search
"Pretraži po nazivu ili šifri…" + **3 filter dropdowns** + 12 product cards) → About ("25+",
LAGER magacin) → Brands ("Ovlašćeni distributer vodećih brendova") → Contact form → footer.

### Decisions (2026-06-12)
- **Shop archive + single-product designs:** not delivered yet → build home + chrome now;
  infer archive/detail from homepage card + filter style until designs arrive.
- **Brand filter:** create a new **`brand` taxonomy**; populate by parsing brand from product
  names (e.g. SKF/Würth/NTN) — ideally during the Excel upload.
- **Start: Phase 1 = site chrome** (header + mega-dropdown + footer).

### Reimplementation plan (phased)
- **P0 Reconcile data:** map design's 12 categories ↔ 23 WP parents (incl. new *Linearne
  tehnologije*, merged *KM Navrtke & MB Podloške*); dropdown **subtitle** source (child names
  or new ACF field); brand taxonomy; extract Figma assets (hero bg, category imgs, logos, icons).
- **P1 Chrome:** header.php · mega-dropdown (data-driven) · footer.php · CSS.  ← current
- **P2 Homepage:** rebuild front-page.php (Hero · Kategorije+counts · Proizvodi+search+filters ·
  About · Brands · Contact).
- **P3 Search/filter backend:** AJAX instant search by title+SKU; filters (category/brand/sort).
- **P4 Shop pages:** archive + single product (needs designs).

> The current front-page.php (hero/brand strip/cat-grid/placeholder, §10) is largely SUPERSEDED
> by this redesign; the category-placeholder wiring (§10) still applies to category card images.

---

## 13. Redesign build status (2026-06-12) — LIVE

New palette: navy `#1B3E7A` · ink `#0F1C36` · red `#C8001D` · muted `#5A6A8A` · panel `#E4ECF8`
· input `#EEF1F8`. Container widened to **1920px** max, `--gutter: clamp(16px,3.33vw,64px)`.

**✅ P1 Chrome** — `header.php` (util-bar + masthead + "Svi proizvodi" mega-dropdown, 12 cats),
`footer.php` (4-col dark footer). Header is **sticky** (`.siteheader` wrapper). Icons added to
`inc/icons.php` (grid/chevron/arrow/clock/check/box/truck).

**✅ P2 Homepage** — `front-page.php`: Hero (bg img + KPI band underneath, header+hero+KPI = 100vh,
content left-aligned to container) · Kategorije (6 cards + counts) · Proizvodi (search + 3 filters +
12 cards; empty until products published) · O Nama (25+, 4 features) · Brendovi (8 text cards) ·
Kontakt (info + working form → `inc/contact.php`, emails admin). Home images in `assets/img/home/`.

**✅ P4 Archive** — `archive-product.php` (covers shop + product_cat + product_brand): faceted
sidebar (search · Dostupnost · Kategorija w/ counts · Proizvođač · Cena Od/Do) + sort + dense
product rows + pagination. Custom WP_Query (tax+meta+search+sort). Checkbox facets auto-submit (JS).
**Brand facet uses native `product_brand`** (WC 10.8) — empty until populated.

**WooCommerce styling:** `inc/enqueue.php` **dequeues** woocommerce-general/layout/smallscreen on
shop/product-taxonomy/single-product (we style those ourselves); kept on cart/checkout/account.
Body is a sticky-footer flex column (no white gap under footer on short pages).

**Deploy:** scp each changed file to `wp-content/themes/lager032/…`, `chmod 644` files / `755` new
dirs (LiteSpeed 0700→404 trap), `php -l`, `wp cache flush`. Site **not** in coming-soon (public).

### Status update 2026-06-15
- **All 4,934 products PUBLISHED** — archive + homepage Proizvodi now populate; real category
  counts live (e.g. Ležaj 2,582). Site is a **dev link**, indexing **discouraged**
  (`blog_public=0`) → flip ON at production.

### Build status
- **✅ Single product page** (2026-06-15) — `single-product.php` + `template-parts/contact.php`.
  Data-first per eecart: breadcrumb · **category image as illustrative reference** ("Slika je
  ilustrativna") · title · šifra/brand · price + "bez PDV-a" · stock · qty + add-to-cart (in-stock
  only) · "pozovite za upit" CTA · **Specifikacija** table (grows w/ data) · Slični proizvodi (4) ·
  contact · **Product + BreadcrumbList JSON-LD** (SEO). Verified on in/out-of-stock products.

### Decision: brands SKIPPED for now (2026-06-15)
Brand isn't in the data (no Excel column); only ~40–48% of product names contain a brand token,
and short tokens (e.g. "INA") false-match Serbian words (mašINA). So: **Proizvođač facet stays
auto-hidden** (empty `product_brand`), homepage Brendovi cards remain **static trust content**.
Revisit only if the client adds a brand column to the Excel. No code change needed.

### Next 3 steps
1. **Data cleanup** — strip trailing `---` from product names.
2. **Pricing decision** — settle ex-VAT vs incl-VAT ("bez PDV-a") and apply consistently sitewide.
3. **Cart/checkout styling** — still WooCommerce default (Woo CSS kept there); design later.

---

## 14. URL architecture (DECIDED + APPLIED 2026-06-15)

**One domain, shop as a path — NO `shop.` subdomain** (subdomains split SEO authority; we unified
marketing + shop in one theme). On production everything lives on `lager032.rs`.

**Pretty permalinks (Serbian slugs) — APPLIED on dev:**
- `permalink_structure = /%postname%/`
- Shop (all products): **`/prodavnica/`** (shop page slug set to `prodavnica`)
- Category archives: **`/kategorija/{slug}/`** (nested for subcats) — `category_base=kategorija`
- Single product: **`/proizvod/{slug}/`** (flat — stable if re-categorized) — `product_base=proizvod`
- Search/filtered: `/prodavnica/?s=…&fcat=…`

**Where the product LIST renders:** `/prodavnica/` (all) and `/kategorija/{slug}/` (per category) —
both via `archive-product.php`.

**⚠️ Server gotcha (LiteSpeed subdomain docroot):** the subdomain docroot
(`…/lager032.pixels2pixels.ch/`) had **no `.htaccess`**, so pretty permalinks 404'd. Fix = create
`.htaccess` with the standard `# BEGIN WordPress … RewriteRule . /index.php [L]` block (RewriteBase `/`).
WP-CLI can't auto-write it here ("special configuration" warning). Now in place; all URLs 200.

**Production migration TODO:** 301-redirect old `shop.lager032.rs/...` URLs → new paths to keep rankings.

---

## 15. Session log — 2026-06-15

- **Products published:** all 4,934 drafts → publish (xargs batches). Archive (`/prodavnica/`) +
  homepage Proizvodi now populate; real category counts (Ležaj 2,582). Site is a **dev link** →
  set **`blog_public=0`** (discourage indexing); flip ON at production.
- **Single product page built** (`single-product.php` + `template-parts/contact.php`): data-first
  per eecart — breadcrumb · **category image as illustrative ref** · title · šifra/brand · price +
  "bez PDV-a" · stock · qty + add-to-cart (in-stock only) · CTA · **Specifikacija** table · Slični
  proizvodi (4) · contact · **Product + BreadcrumbList JSON-LD**. Verified in/out-of-stock.
- **Brands: SKIPPED** (decision) — brand not in data; ~40–48% of names contain a token + false
  positives ("INA" in mašINA). Proizvođač facet stays auto-hidden; homepage Brendovi = static.
- **URL architecture:** one domain (NO `shop.` subdomain). Applied **Serbian pretty permalinks**:
  `/prodavnica/` (shop), `/kategorija/{slug}/` (categories, nested), `/proizvod/{slug}/` (products,
  flat). **Created `.htaccess`** (subdomain docroot had none → pretty URLs 404'd; LiteSpeed). All 200.
- **SEO = standing principle** (memory `seo-best-practices`); titles/meta, JSON-LD, breadcrumbs, etc.
- **Archive top padding** 40px → 16px (removed near-white gap under sticky header).

**Template map:** `/` → front-page.php · `/prodavnica/` + `/kategorija/{slug}/` → archive-product.php
(same template; category just scopes the query + H1) · `/proizvod/{slug}/` → single-product.php.

### Open / next
1. **Data cleanup** — strip trailing `---` from product names.
2. **Pricing decision** — ex-VAT vs incl-VAT ("bez PDV-a"), apply consistently.
3. **Cart/checkout styling** — still Woo default; design later.
- Optional: category directory page at `/kategorija/` (bare path is currently 404); AJAX filters
  (currently GET reload); `rel=canonical`/`noindex` on filtered param URLs.

### Standing principle: SEO
Apply **SEO best practices** in every template/feature — titles/meta, single H1, Product +
BreadcrumbList JSON-LD, breadcrumbs, image alt, pretty permalinks, sitemap, internal linking.
(See memory `seo-best-practices`.) Footer **PIB/Matični broj** still mockup placeholders.

---

## 16. Session log — 2026-06-15 (this laptop — header & homepage polish)

Small UX/asset fixes on top of the redesign (all deployed; site is the public dev link):

1. **"Svi proizvodi" is now a link** → `/prodavnica/` (was a `<button>`). The mega-dropdown
   still opens on hover / keyboard focus (CSS `:hover`/`:focus-within`); removed the JS
   click-toggle so the click navigates. Chevron rotates on hover instead of `aria-expanded`.
   *Touch caveat:* tapping it navigates (doesn't open the dropdown) — acceptable since the
   shop lists all categories. Files: `header.php`, `assets/js/main.js`, `assets/css/main.css`.
2. **Archive padding removed** — `.archive` `padding: 16px 0 64px` → `0`, so the product list
   sits flush under the sticky header and at the bottom.
3. **Real logo in header** — replaced the text "L032" badge with the actual **LAGER** logo
   image (Figma node `106:2735`, exported PNG @scale 4 → `assets/img/logo.png`; `.brand__img`
   at 40px). NOTE: the **footer** still uses the text badge (`footbrand`); the colored logo
   isn't legible on the dark footer — needs a white/knockout version if we want it there.
4. **Homepage "Proizvodi" (Katalog) section removed** — its own search + 3 filter dropdowns +
   12-product grid + `wc_get_products()` query were redundant with `/prodavnica/`. Homepage now:
   Hero → KPI → Kategorije → O Nama → Brendovi → Kontakt. Leftover `.catalog*`/`.prodcard*` CSS
   is now dead (harmless; strip in a later cleanup).

Deploy per usual: scp changed file(s), `chmod 644`, `php -l`, `wp cache flush`.

---

## Session log — 2026-06-16

**Header polish**
- util-bar **right-aligned** (both phones now beside email + address).
- Nav **centered in container**: wrapped "Svi proizvodi" + nav in `.masthead__center`, absolutely
  centered on desktop (≥1024px); `display:contents` below that keeps the mobile hamburger nav intact.
  Logo alone left, search + cart right.
- Added **Katalog** nav item (→ shop). Nav item gaps widened to 19px (incl. before Svi proizvodi).
- **Subcategory flyouts** in the "Svi proizvodi" mega-dropdown: categories with children (Ležaj 22,
  Remen 5) show their **real subcategories on hover** (data-driven from `product_cat`), `›` caret,
  "Svi: {kategorija}" link; the other 10 link directly. `.megamenu` overflow set visible for the flyout.

**Phase 3 — Live AJAX search** (`inc/search.php` + `assets/js/main.js` + CSS) — DONE:
- Typeahead (debounce 250ms, abort stale), matches **title + SKU**, ranked (exact SKU → starts →
  title starts → contains), diacritic-insensitive.
- **Code normalization**: strip spaces/dashes/dots/slashes + lowercase → `6205-2RS` = `6205 2RS` =
  `62052rs` all match.
- **Category suggestions** collapsed to the matched parent (e.g. Ležaj 2.582, accurate incl-children
  count); subcategory-specific queries (e.g. "aksijalni") show the matching subtypes.
- Labeled groups **KATEGORIJE / PROIZVODI**, highlighted match, rows = thumb · naziv · šifra · price ·
  stock, **quick-add to cart** (AJAX `wc-ajax=add_to_cart` + cart-count fragment → header badge),
  "Prikaži sve rezultate (N) →" footer, keyboard nav (↑/↓/Enter/Esc), no-results state. 640px dropdown.
- Endpoint nonce-protected, published-only. Localized via `LagerSearch`.

**Pricing decision RESOLVED — show FULL price incl. PDV** (closes the §13/§15 open item):
- Was showing **net + "bez PDV-a"** (per Figma). Switched ALL templates (archive `.prow`, single
  product, related, search results) to **`$product->get_price_html()`**, which returns the incl-PDV
  price per Woo's `tax_display_shop=incl`. Dropped the "bez PDV-a" label → clean single number.
  Example: net 1.599 → **1.919 рсд**. Single-product JSON-LD now uses `wc_get_price_including_tax()`.
  Matches the old site (which showed sa-PDV) + Serbian consumer-price norm. (Site is **retail/B2C** — "no B2B".)

**Data note — "spojnica" gap (diagnostic):** 33 products are *named* "spojn*"; **9** are in the
**Spojnice** category, **24** are in **"Lanci i lančanici"** (all 24 — likely chain couplings). This is
source (Croonus) categorization, not a search bug — search finds all 33 by name regardless. Whether to
re-categorize the 24 is a client decision.

### Next
1. **Product name cleanup** — strip trailing `---` from product titles.
2. **Cart / checkout styling** — still WooCommerce default (Woo CSS kept there).
3. Optional: wire the live-search dropdown to the **homepage/shop** search boxes too; **spojnica
   re-categorization** (client decision); strip dead `.catalog*`/`.prodcard*`/`*__price small` CSS.

---

## Session log — 2026-06-17

**Product name cleanup — DONE** (closes prior "Next #1").
- `clean-titles.php` (dry-run / `apply`, like the other maintenance scripts): collapses runs of 3+
  dashes to a space + trims. **176 titles** cleaned (`BETA OSIGURAC 3X80 ---` → `BETA OSIGURAC 3X80`,
  `UCFL 001 --- AL` → `UCFL 001 AL`). post_title only — slugs/URLs unchanged. Verified 0 left.

**Header nav — responsive fix.** The centered nav was absolutely positioned (`left:50%; translate`),
so it overlapped the search input on smaller screens. Now **in normal flow** (`.masthead__center`
flex:1 + justify-center) → it can't overlap (search shrinks first). Hamburger breakpoint raised
**900 → 1199px** so the full horizontal nav only shows where it fits.

**Catalog filtering reworked — category is NOW navigation, not a filter** (resolves the empty-AND /
inconsistent-rail confusion):
- Removed the `fcat[]` category checkboxes. The left rail shows a **category panel of links**: on the
  shop → **top-level** ("Kategorije", browse in); on a category page → **only its subcategories**
  ("Potkategorije", drill down); on a **leaf** category → none. Never offers a sibling category, so you
  can't AND into empty results.
- **Breadcrumbs** added (visible + `BreadcrumbList` JSON-LD) as the up/cross navigation.
- Brand / availability / price / search stay as filters, **consistent on every archive page**.

**SEO — `inc/seo.php`** (new): product archives get a `rel=canonical` to the clean base; filtered /
sorted / searched `?param` permutations get **`noindex` + canonical-to-base** (no crawl-budget waste /
duplicates). Clean category/shop URLs stay self-canonical & indexable. (On the dev link `blog_public=0`
makes everything `noindex` anyway; the filter avoids a contradictory `nofollow, follow`.)

**Archive UX:** **active-filter chips** (availability/price/search) with one-click remove + "Poništi
sve"; **scroll position preserved** across the filter reload (sessionStorage) — no jump-to-top.

All deployed + verified on the dev link; PHP lint clean.

### Next
1. **Cart / checkout styling** — still WooCommerce default.
2. Mobile: collapse the category panel + filters into a "Filteri" drawer (currently stacks above results).
3. Optional: wire live-search into the homepage/shop search boxes; spojnica re-categorization (client);
   strip dead `.catalog*`/`.prodcard*` CSS.

---

## Session log — 2026-06-17 (cont.) — filtering consistency

- **Category sidebar = persistent collapsible tree** (links): every archive shows the full list;
  parents with subcategories collapse (active branch auto-opens). One click to any category.
- **Filteri card moved above** the category list (flex order).
- **Price filter = dual-handle slider** (real GROSS bounds), synced Od/Do inputs, **applies on drag
  release**. Bounds are **contextual** (reflect current category/search/availability, excl. price) and
  the filter **auto-hides when there's no range** (≤1 product / one price). Query converts gross→net.
- **Search param `s` → `q`** (own param). Killed WP's single-result "redirect to the product" behaviour
  (multiple mechanisms tried; `q` sidesteps all). Updated header search, sortform, viewAll, SEO keys.
- **Archive search unified with the typeahead** — `lager_search_product_ids()` (title + SKU, code
  normalization `6204-2RS`=`62042rs`); archive uses `post__in` + relevance order. No more mismatch.
- **`section.archive` not `.archive`** — bare `.archive` also hit `<body>` (WP archive body class),
  which had added stray padding above the sticky header. Padding: 24/48 top/bottom on the section only.
- Decisions locked: category click = **reset filters** (clean URL); sidebar search = **within category**;
  facet counts = **catalog totals**.
- **Per-row quick add-to-cart**: in-stock archive rows get a qty stepper + "Dodaj" that AJAX-adds the
  chosen quantity (reuses the typeahead's `wc-ajax=add_to_cart` + cart-count fragment). `.qtybox`/`.prow__add`.
- Single product page: scoped its wrapper to `section.single` (same `body.single` padding bug).

---

## Session log - 2026-06-18 - cart drawer, one-page checkout, Serbian dashboard

**Cart state + drawer**
- Product list + single product reflect the cart on load (cache-safe, applied client-side via `wc-ajax=lager_cart_state`); row/button SET quantity (not blind re-add), remove at 0, marked "U korpi (N)".
- Stock never blocks: `woocommerce_product_is_in_stock` + `..._backorders_allowed` => true (order any qty, incl. 0). Dropped out-of-stock UI; shows "Na stanju".
- Mini-cart fly-out drawer (footer `aside.minicart` + overlay), opened by the header cart icon. Server-rendered `lager_minicart_body_html()` registered as cart fragment `div.minicart__body` => live updates. Per-item +/- stepper, remove, and "Isprazni korpu" clear (`wc_ajax_lager_clear_cart`). inc/cart.php.
- Currency symbol forced to Latin "RSD" (was Cyrillic) via `woocommerce_currency_symbol`; "sa PDV-om" label by prices.

**One-page checkout** (inc/checkout.php + woocommerce/checkout/* overrides, new page.php)
- Cart + checkout merged to one page at **/korpa/** (checkout slug renamed; `template_redirect` sends a non-empty cart -> checkout, guarded vs empty-cart loop). Converted cart/checkout from Gutenberg blocks to classic shortcodes.
- Custom billing fields in two sections (`lager_section` customer/delivery): Ime, Prezime, E-mail, Mobilni telefon, Fiksni telefon / Ulica, Broj zgrade/kuce, Sprat, Broj stana, Interfon, Grad, Dostavna poruka. Extras saved to order meta + shown in admin + e-mails. Country fixed RS (hidden). gettext map + `default_address_fields` relabel kill English leaks (incl. address-i18n JS).
- Template overrides: form-checkout.php (two-column grid: forms left / order right, 1180px container), form-billing.php (sectioned headings + required note), review-order.php (table Sifra/Kategorija/Naziv/Cena/Kolicina + editable qty via `setQty` + `update_checkout`).
- Shipping NOT calculated: `woocommerce_cart_needs_shipping(_address)` => false; note "Troskove dostave placa kupac."
- Totals: Osnovica (net) / PDV (20%) / Ukupno za naplatu (gross). Removed per-line total column (duplicated grand total on 1-item carts); dropped Woo "(incl. VAT)" suffix (`woocommerce_countries_inc/ex_tax_or_vat` => '').
- **Duplicate-summary bug**: totals sat in a `<div>` outside the table; Woo's on-load `update_checkout` replaces `.woocommerce-checkout-review-order-table` and left the original summary behind => two blocks. Fixed by moving totals into the table `<tfoot>` (atomic swap).
- Page title hidden on cart/checkout; Woo default CSS dequeued on cart/checkout (fixed the blown-up header logo); full custom checkout CSS in main.css.

**Payments**
- Enabled "Uplatnica / bankovni transfer" (BACS) + "Placanje pouzecem" (COD); styled methods as selectable radio cards. Card online pending a Serbian gateway + merchant account. Bank account no. / shipping values are PLACEHOLDERS (need client).

**Serbian dashboard**
- WP admin set to Serbian for the manager: installed sr_RS core + WooCommerce packs, applied **per user** (`locale=sr_RS` user meta) so the storefront stays en_US/Latin. Cyrillic only (no official Latin pack); reversible per user.

**Verified**: 2 real test orders placed and landed in the dashboard.

---

## Session log — 2026-06-19

**Thank-you / order-received page** (`woocommerce/checkout/thankyou.php` override, Serbian Latin):
confirmation + order #/date/email/total/payment · **payment instructions** (BACS: account from
`woocommerce_bacs_accounts` + iznos + "poziv na broj" = order #; or COD note) · items table with
**image** (catalog placeholder when none) + šifra/qty/price · totals (Osnovica/PDV/Ukupno) · customer
& delivery cards. Verified via direct template render (TITLE/PAY/IMG/TOTALS).
- Note: guest visiting the order URL without their session hits WC's **email-verification gate**; the
  buyer sees the full page right after checkout. Bank account # is still a **placeholder** (client).

**Checkout fields tidy-up** (`inc/checkout.php`): removed Sprat / Broj stana / Interfon; renamed
"Broj zgrade/kuće" → **"Broj"** and made it **required** (delivery-critical). Thank-you tweaks: dropped
"Ukupno" from the meta list (kept in totals), product cell now shows name + category. Mini-cart drawer:
two redundant buttons → single **"Pogledaj korpu"**.

**Order e-mails — SMTP + Serbian + branded** (`inc/emails.php`, new):
- SMTP via Gmail (`smtp.gmail.com:587`, app password) — client set up; mail delivers.
- From **LAGER STR `<lager032@gmail.com>`**; site title set to **LAGER STR** (`blogname`).
- Serbian **subjects + headings** (per-email filters) and **body** (gettext map scoped to e-mail
  rendering only, so admin/storefront stay en_US) — greeting, intros, bank details, addresses, totals
  labels, country Srbija. Serbian footer line via `woocommerce_email_additional_content_*`.
- Branding: `woocommerce_email_base_color=#1B3E7A`, header image = logo.

**Admin orders list** (`inc/admin-orders.php`, new): added **Telefon · Grad · Broj artikala** columns
(after Ukupno) to match the old site's order overview — HPOS + legacy hooks.

### Open / next
- Real product images · real **bank account #** + PIB/Matični broj · **O Nama / Sertifikati / Kontakt**
  pages (nav `#`) · Serbian card-payment gateway · go-live (no-index off, 301 old URLs).

---

## Session log - 2026-06-24 - header + navigation redesign (Figma 47:2420)

Pulled the spec from Figma via the REST API (`.figma-token`, file key `rkOC41hpF2Dx1HR93xt0Fb`,
node `47:2420`) - layout, auto-layout gaps/padding, fonts, colors, text, X-positions.

**Structure** (`header.php`, full rewrite - still two rows, content rearranged):
- **Top bar** `.util-bar` (navy `#112955`): contacts (`.util-bar__phones` = one phone icon + both
  numbers, then e-mail, then address) **+ the search box + cart icon** - all right-clustered next to
  the search (`justify-content: flex-end`), matching the Figma X-positions.
- **Nav bar** `.masthead` (white, min-height 86): logo left (`.brand__img` height 56), then
  `.mainnav` right = links (Početna / O nama / Katalog / Sertifikati, Montserrat 600/13, navy, hover
  red) + red **"Prodavnica"** button `.shopcats__btn` (`#d60000`, Montserrat 700/12 uppercase).
- Search + cart **moved out of the masthead** up into the top bar. Cart is now a white icon + red
  count badge on the navy bar.
- Kept the **category mega-dropdown** attached to the "Prodavnica" button (hover), re-anchored to the
  right (`.megamenu { right: 0 }`). `masthead__center` wrapper removed.

**Type**: added **Montserrat** (500/600/700) to the Google Fonts enqueue (`inc/enqueue.php`).

**Colors**: header uses the Figma tokens **navy `#112955` / red `#d60000`** (FIGMA.md design tokens).
These differ slightly from the global `--c-navy #1B3E7A` / `--c-red #C8001D` - NOT yet rolled out
site-wide (decision pending). Search field bg `#eef1f8` (= `--c-input`).

**Responsive**: nav collapses to the hamburger < 1100px (`.mainnav` column, `.shopcats__btn` full
width); top bar wraps < 768px (address hidden, search goes fluid).

Note: rebased on top of the teammate's thank-you/e-mails/admin-columns commits - no conflicts (header
files were untouched by that work).

---

## Session log — 2026-06-25 — homepage redesign + product-group images

Continued the Figma redesign (file key `rkOC41hpF2Dx1HR93xt0Fb`), node by node, from header
through the whole homepage + footer; then wired the real category images into the storefront.

**Container proportions (relative, not absolute).** Design canvas = 1440px, left edge of logo
to page = 100px. Set `--gutter: 6.944vw` (= 100/1440) and `--container: none` so the inset
scales with the viewport instead of a fixed max-width. Container-width dividers use
`::before { left: var(--gutter); right: var(--gutter) }`.

**Homepage sections** (`front-page.php` + `assets/css/main.css`, each pulled from its Figma node):
- **Hero** (`185:2099`): image fills the hero height; header + KPI stats band extracted
  above/below; exact overlay `linear-gradient(105deg, rgba(27,62,122,.93) 7.74%, rgba(27,62,122,.8)
  54.23%, rgba(10,20,50,.65) 92.26%)`; `min-height: calc(100vh - 136px - 80px)`; eyebrow removed.
- **Category grid** (`47:2495`): 12 `.catcard`s, own images, `aspect-ratio:295/240`. Hover swaps the
  navy shade for a lighter red wash and reveals a `.catcard__cta` ("Preuzmi katalog"); removed the old
  box-shadow hover. Replaced a duplicate category card.
- **O nama** (`47:2434`): new copy + `about-magacin.jpg` (cache-busted `?v=filemtime`); badge removed;
  `.about__inner { padding-inline: calc(var(--gutter) + 100px) }` ("inside container" = keep gutter
  AND add 100px each side).
- **Brands** (`231:2260` + `227:2251`): JS carousel (`assets/js/main.js` IIFE) — responsive perView
  1/2/3/4, paging dots only when >1 page, auto-rotate 4500ms; single page centers cells (`justify-content:
  center; gap:30px`). 4 logos, no arrows.
- **Contact** (`231:2264`): extra space under the heading.
- **Footer** (`224:2152`): white, 4 columns (brand + cert badge, Navigacija, Kategorije, Kontakt) +
  legal bar; soft top shadow `0 -5px 20px rgba(15,28,54,.08)`; logo fills its 258px column (so its
  center = column center, cert badge centered under it); links gray `#94a3b8` → navy on hover.

**Logo crop.** The PNG had transparent padding → looked misaligned. Cropped to the pixel bbox
(System.Drawing LockBits) so the artwork fills the canvas; re-uploaded; header + footer use
`logo.png?v=filemtime` cache-busting.

**Mega-menu fixes.** Items with subcategories had double padding (wrapper + inner) → wrapper
`padding:0` so they align with the rest; chevron points **down** (`rotate(90deg)`); subcategory
submenu drops **down** under the parent (`.submenu { left:0; top:100%; grid-auto-flow:row }`),
not flying out to the right.

**Global color tokens** unified to the design: `--c-navy:#112955`, `--c-red:#d60000`,
`--c-red-dark:#b00000`, `--c-ink:#0F1C36`.

**Product-group images → category thumbnails.** 46 supplied images (`Slike za grupe proizvoda`,
mostly AVIF) mapped to categories/subcategories by fuzzy name match (e.g. `autoprogram`→`Auto
program`, `Kuglice`→`Kuglica`, the two kućišta variants). Server has GD with AVIF decode
(`imagecreatefromavif`) but ImageMagick has **no avif delegate** and System.Drawing can't decode
AVIF — so a temp server script (`_catimport.php`) decoded each via GD, flattened white, re-encoded
JPEG q88, `wp_upload_bits` → `wp_insert_attachment` → metadata, and
`update_term_meta($tid,'thumbnail_id',$att)`. **46/46 imported** (attachments 4961–5006). WooCommerce
category image = term meta `thumbnail_id` (NOT a post "featured image"). Temp files cleaned up.

**Products now show their category image** (they have no photos of their own). New shared helper
`lager_product_category_image_id($pid)` in `functions.php`: **subcategory image → parent category →
any category → `lager_cat_placeholder_id`** (returns attachment ID). Used in:
- `single-product.php` — main illustrative visual + "Slični proizvodi" cards (refactored off the old
  inline block).
- `archive-product.php` — the shop/category `.prow` rows (was `$product->get_image()` → Woo
  placeholder), now `wp_get_attachment_image(..., 'woocommerce_thumbnail')` with responsive srcset.

Verified live: `/prodavnica/` rows all render `kategorija-*.jpg`, **zero placeholders**; a sample
product shows its subcategory image. All deployed (scp → `php -l` → `chmod 644` → `wp cache flush`).

### Open / next
- Real **bank account #** + PIB/Matični broj (still placeholders) · O Nama / Sertifikati / Kontakt
  pages (nav `#`) · roll the design's navy/red tokens fully site-wide (header already uses them) ·
  go-live (indexing off, 301 old URLs).

---

## Session log — 2026-06-26 — archive/single/cart redesign + new pages (O nama · Katalog · Kontakt)

Pulled the teammate's commits, then a long polish/redesign pass off Figma (file `rkOC41hpF2Dx1HR93xt0Fb`),
node by node. All deployed (scp → `chmod 644` → `php -l` → `wp cache flush`); site is the public dev link.

**Product archive (`/prodavnica/`)**
- Removed the "Katalog" `sec-eyebrow` from the archive head (it's the shop, not a catalog).
- **Filter sidebar** restyled to design (node `106:2038`): navy "Filteri" header bar (Roboto Condensed),
  compact search input (12px, 4px radius), section titles, options (12px semibold), price Od/Do inputs;
  borders → `.8px rgba(27,62,122,.15)`, radius 10px. Style-only — structure unchanged. (scoped `--fl` var.)
- **Results bar** (node `106:2213`): white bordered card; two-tone count (bold dark numbers + muted
  "od…artikala", via `<strong>` in the printf); restyled Sortiraj label + select.
- **Product card `.prow`** rebuilt (node `231:2487`): top line = **brand badge + category** (subcategory
  precedence); **šifra moved under the title** (monospace); **description removed**; right column reordered
  to price · sa PDV · stock (green dot) · **Dodaj u korpu** button · qty −/＋ stepper **below** it. Image
  flush-left `object-contain`. Fixed the stepper so the **+** isn't clipped (`flex:none` btns, input `flex:1`).
- **Brand derivation** (no brand field in data): new `lager_extract_brand()` — takes the **trailing title
  token only if it matches a whitelist** (SKF/FAG/INA/SNR/WBW/ZVL/Gates/Würth…), strips it from the title;
  `product_brand` taxonomy still wins if ever populated. Filterable via `lager_brand_whitelist`. Plus
  `lager_product_primary_category_name()` (subcategory precedence). Both in `functions.php`.

**Header / nav**
- **"Svi proizvodi" mega-dropdown**: long subcategory lists (>8, i.e. Ležaj ~22) now wrap into **2 columns**
  sized to the parent dropdown width (540px), `minmax(0,1fr)` + column-gap so labels stop overlapping.
- **Cart icon badge**: count is now a **red circle absolutely on the icon's top-right corner** (navy ring),
  shown only when non-empty; cart icon bumped to 23px. **Live-updates** via a new `span.cartbtn__count`
  WooCommerce fragment (was only refreshing on reload).
- Mail **icon** swapped to a proper outlined **envelope** (was a solid rectangle) — header + footer.

**Cart drawer + cart page**
- Drawer items now show the **category/subcategory image** (`lager_product_category_image_id`) + the same
  **brand badge + category** meta as the list.
- Cart/checkout (`/korpa/` `review-order.php`): added a **category-image column** beside the name; **removed
  the Šifra column** (frees space); "Naziv" label sits above the name (own image column).

**Single product** (node `47:2291`) — restyled to the new design:
- Buy box: 36px navy title · **red 28px Oxygen price** · divider · label/value meta (Šifra/Kategorija/
  Proizvođač/Dostupnost, green when in stock) · **49px qty stepper + full navy "Dodaj u korpu"** · delivery
  note. **Specs table removed** (design dropped it; fields live in the meta).
- **Slični proizvodi** rebuilt as the design's vertical **ProductCard** (category badge, image on `#dde4f0`,
  green "Na stanju" pill, price + sa PDV, working navy **Dodaj**). Added the **Oxygen** font to the enqueue.

**O nama** (homepage About): extra side padding made **fluid** (`clamp`, full 100px ~1920 → 0 by ~1280) so
the image isn't crowded at ~1500; image locked to the design's **square-ish 500×402** (`aspect-ratio` +
`object-cover`).

**New pages** (all WordPress pages + `page-{slug}.php` templates, auto-applied; nav wired):
- **`/o-nama/`** (node `234:2793`) — `page-o-nama.php`: homepage hero reused with a **new image**
  (`hero-onama.jpg`, exported from Figma) + KPI band, then **two alternating About blocks** (image left,
  then `.about--reverse` image right). Page ID 5007.
- **`/katalog/`** — `page-katalog.php`: themed "Preuzmite katalog 2022/2023" panel (download icon) + **embedded
  PDF viewer** (iframe) + **Preuzmi PDF** button. PDF in **wp-content/uploads** (`katalog-2022-2023.pdf`,
  4.76 MB / 73 pp) so it's swappable without a deploy. Page ID 5008. Nav "Katalog" (header+footer) repointed
  here (was the shop). Added a `download` icon.
- **`/kontakt/`** — `page-kontakt.php`: contact info + the working `lager_contact` form (reused) + **Google
  Maps embed** (no API key) for Kneza Miloša 100, Čačak. Page ID 5009. Added "Kontakt" to header nav.

**Live search** (`inc/search.php` + `main.js`): result rows now use the **category/subcategory image** (same
as single product) and show the **category** in the meta line (`Kategorija · Šifra: …`).

**Footer**: "Navigacija" links fixed/extended — Početna · **Prodavnica** (new, under Početna) · Katalog ·
O Nama · Sertifikati · Kontakt; O Nama/Kontakt now point to the real pages.

**Site-wide link audit**: checked header, footer, homepage, all templates + live HTTP 200 on every target.
All valid **except `Sertifikati`** (header + footer still `#`) — **left as-is by decision** (no certificate
content yet; per-category "Preuzmi katalog" CTAs also intentionally inert).

### Closed this session
- O Nama / Katalog / Kontakt pages built + linked (were nav `#`).

### Open / next
- **Sertifikati** page (still `#`, on hold) · real **bank account #** + PIB/Matični broj · roll navy/red
  tokens fully site-wide · **mobile QA pass** (designs were desktop-only 1440 — mobile is best-judgment) ·
  go-live (indexing off, 301 old URLs).
