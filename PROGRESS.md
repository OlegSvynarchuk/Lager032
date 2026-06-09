# Lager032 — Project Progress

> Working notes for the Lager032 WooCommerce migration. Commit this file so both
> laptops stay in sync. See [workflow.md](workflow.md) for the full SSH/deploy reference.
> Last updated: 2026-06-09.

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

### Tool spec (to build)

A small mu-plugin admin page ("Lager → Upload") that, on one .xlsx upload:
1. Upsert **categories** from cols 2–3 (create missing; **preserve marža**; mark new ones
   marža-empty for manual entry).
2. Upsert **products** by SKU (cols 1,3→cat,4,5,6): update name, category, stock, VP.
3. Recompute net price via existing reprice logic (`lager_reprice_product`).
4. **Discontinued → out-of-stock** (SKUs in WP but not in this file).
5. Show a summary: created / updated / new-categories-needing-marža / set-out-of-stock.

### Open questions before building

- **xlsx parsing in PHP:** WordPress has no built-in reader. Either bundle PhpSpreadsheet
  (heavier) or accept **CSV** (client does "Save As CSV" — trivial, no dependency). DECIDE.
- Does the Excel have a header row? (export_products.py skips row 0 → assumes yes.)
- New-product status on upload: draft (stage) or publish directly?
- Get the client's actual example file to lock column order / number locale.
