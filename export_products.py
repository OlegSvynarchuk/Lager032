# -*- coding: utf-8 -*-
"""Export products from the xlsx to a clean UTF-8 JSON for the WP importer.
Emits raw source fields only; the importer computes net price from the
category's marža (single source of truth)."""
import json
import openpyxl

SRC = "Lager za WEB 13-Maj-26.xlsx"
OUT = "products.json"

wb = openpyxl.load_workbook(SRC, read_only=True, data_only=True)
ws = wb["Sheet1"]

def num(x):
    if x is None:
        return None
    try:
        return float(str(x).replace(",", "."))
    except ValueError:
        return None

out = []
for i, row in enumerate(ws.iter_rows(values_only=True)):
    if i == 0:
        continue
    idb, code, kln, name, stanje, vp = row
    if idb is None or str(idb).strip() == "":
        continue
    out.append({
        "sku":   str(idb).strip(),
        "code":  str(code).strip(),       # KlBroj -> matches category `sifra`
        "name":  str(name).strip(),
        "stock": num(stanje),
        "vp":    num(vp),                 # wholesale base price
    })

with open(OUT, "w", encoding="utf-8") as f:
    json.dump(out, f, ensure_ascii=False)

frac = sum(1 for p in out if p["stock"] is not None and p["stock"] != int(p["stock"]))
nullvp = sum(1 for p in out if p["vp"] is None)
print(f"Wrote {OUT}: {len(out)} products | fractional-stock: {frac} | null-vp: {nullvp}")
