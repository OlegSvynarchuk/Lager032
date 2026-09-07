/* Lager032 — front-end interactions (header redesign 2026-06-12). */
(function () {
	'use strict';

	// "Svi proizvodi" is a link to the shop (/prodavnica). The mega-dropdown opens on
	// hover / keyboard focus via CSS (.shopcats:hover / :focus-within); clicking the
	// button navigates to the shop — no JS toggle needed.

	// Archive facets: checkbox toggles apply immediately (price/search use the button/Enter).
	document.querySelectorAll('.filters__form input[type="checkbox"]').forEach(function (cb) {
		cb.addEventListener('change', function () { cb.form.submit(); });
	});

	// Category nav: collapsible parent groups — the caret toggles the subcategory list.
	document.querySelectorAll('.catnav__toggle').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var group = btn.closest('.catnav__group');
			if (!group) return;
			btn.setAttribute('aria-expanded', group.classList.toggle('is-open') ? 'true' : 'false');
		});
	});

	// Price range: dual-handle slider kept in sync with the Od/Do inputs (the inputs submit).
	document.querySelectorAll('.prange').forEach(function (el) {
		var lo = +el.dataset.min, hi = +el.dataset.max;
		if (!(hi > lo)) return;
		var rMin = el.querySelector('.prange__range--min'),
			rMax = el.querySelector('.prange__range--max'),
			nMin = el.querySelector('.prange__num--min'),
			nMax = el.querySelector('.prange__num--max'),
			fill = el.querySelector('.prange__fill');
		function pct(v) { return ((v - lo) / (hi - lo)) * 100; }
		function paint() { fill.style.left = pct(+rMin.value) + '%'; fill.style.right = (100 - pct(+rMax.value)) + '%'; }
		function fromRange(which) {
			var a = +rMin.value, b = +rMax.value;
			if (a > b) { if (which === 'min') { rMax.value = a; b = a; } else { rMin.value = b; a = b; } }
			nMin.value = a > lo ? a : '';
			nMax.value = b < hi ? b : '';
			paint();
		}
		function fromNum() {
			var a = nMin.value === '' ? lo : Math.max(lo, Math.min(hi, +nMin.value || lo));
			var b = nMax.value === '' ? hi : Math.max(lo, Math.min(hi, +nMax.value || hi));
			if (a > b) { a = b; }
			rMin.value = a; rMax.value = b; paint();
		}
		var form = el.closest('form');
		function applyNow() { if (form) form.submit(); }
		rMin.addEventListener('input', function () { fromRange('min'); });   // live: drag updates thumb/fill/inputs
		rMax.addEventListener('input', function () { fromRange('max'); });
		rMin.addEventListener('change', applyNow);                          // release: apply the filter
		rMax.addEventListener('change', applyNow);
		nMin.addEventListener('change', function () { fromNum(); applyNow(); });
		nMax.addEventListener('change', function () { fromNum(); applyNow(); });
		paint();
	});

	// Archive: preserve scroll position across the filter/sort reload (GET reloads the page,
	// which would otherwise jump to top). Save on any filter change/submit; restore on load.
	var archiveEl = document.querySelector('.archive');
	if (archiveEl) {
		try {
			var sv = sessionStorage.getItem('lagerArcScroll');
			if (sv !== null) { sessionStorage.removeItem('lagerArcScroll'); window.scrollTo(0, parseInt(sv, 10) || 0); }
		} catch (e) {}
		var saveArcScroll = function () { try { sessionStorage.setItem('lagerArcScroll', String(Math.round(window.scrollY))); } catch (e) {} };
		// Capture phase so we save BEFORE the programmatic form.submit() fires.
		document.addEventListener('change', function (e) {
			if (e.target.closest && e.target.closest('.filters__form, .results__sort, #sortform')) saveArcScroll();
		}, true);
		document.addEventListener('submit', function (e) {
			if (e.target.closest && e.target.closest('.filters__form, #sortform')) saveArcScroll();
		}, true);
	}

	// Single product: quantity stepper.
	document.querySelectorAll('.qty').forEach(function (qty) {
		var input = qty.querySelector('input');
		qty.querySelectorAll('.qty__btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var v = parseInt(input.value, 10) || 1;
				v += parseInt(btn.getAttribute('data-dir'), 10);
				input.value = v < 1 ? 1 : v;
			});
		});
	});

	// ---- Live (AJAX) product search ----
	if (window.LagerSearch) {
		document.querySelectorAll('.searchbar').forEach(initSearch);
	}
	function initSearch(form) {
		var input = form.querySelector('input[type="search"]');
		if (!input) return;
		input.setAttribute('autocomplete', 'off');
		var box = document.createElement('div');
		box.className = 'searchresults';
		box.setAttribute('hidden', '');
		form.appendChild(box);
		var timer, controller, items = [], active = -1, lastQ = '', loaded = 0, total = 0, loading = false, curQ = '';

		input.addEventListener('input', function () {
			var q = input.value.trim();
			clearTimeout(timer);
			if (q.length < (LagerSearch.minLen || 2)) { hide(); return; }
			timer = setTimeout(function () { run(q); }, 250);
		});
		input.addEventListener('focus', function () {
			if (items.length && input.value.trim().length >= 2) show();
		});
		input.addEventListener('keydown', function (e) {
			if (box.hasAttribute('hidden')) return;
			var rows = box.querySelectorAll('.sr-row');
			if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, rows.length - 1); mark(rows); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, -1); mark(rows); }
			else if (e.key === 'Enter') { if (active >= 0 && rows[active]) { e.preventDefault(); window.location = rows[active].getAttribute('href'); } }
			else if (e.key === 'Escape') { hide(); }
		});
		document.addEventListener('click', function (e) { if (!form.contains(e.target)) hide(); });

		function run(q) {
			lastQ = q;
			if (controller) controller.abort();
			controller = new AbortController();
			box.removeAttribute('hidden');
			box.innerHTML = '<div class="sr-loading">…</div>';
			fetch(LagerSearch.ajax + '?action=lager_search&nonce=' + encodeURIComponent(LagerSearch.nonce) + '&q=' + encodeURIComponent(q), { signal: controller.signal })
				.then(function (r) { return r.json(); })
				.then(function (data) { if (q === lastQ) render(data, q); })
				.catch(function () {});
		}

		function render(data, q) {
			active = -1; items = data.results || [];
			var cats = data.categories || [];
			if (!items.length && !cats.length) {
				box.innerHTML = '<div class="sr-empty"><strong>' + LagerSearch.i18n.noResults + ' „' + esc(q) + '"</strong><span>' + LagerSearch.i18n.noResultsHint + '</span></div>';
				show(); return;
			}
			var html = '';
			if (cats.length) {
				html += '<div class="sr-head">Kategorije</div>';
				cats.forEach(function (c) {
					html += '<a class="sr-cat" href="' + c.url + '"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg><span>' + esc(c.name) + '</span><em>' + c.count + ' proizvoda</em></a>';
				});
			}
			if (items.length) { html += '<div class="sr-head">Proizvodi</div>'; }
			items.forEach(function (it) {
				html += '<a class="sr-row" href="' + it.url + '">'
					+ '<img class="sr-img" src="' + it.img + '" alt="" loading="lazy">'
					+ '<span class="sr-main">'
					+ (it.cat ? '<span class="sr-catname">' + hl(it.cat, q) + '</span>' : '')
					+ '<span class="sr-title">' + hl(it.title, q) + '</span>'
					+ '<span class="sr-meta">' + (it.sku ? 'Šifra: ' + esc(it.sku) : '') + '</span></span>'
					+ '<span class="sr-side"><span class="sr-price">' + esc(it.price) + '<small>' + (LagerSearch.i18n.withPdv || '') + '</small></span>'
					+ (it.inStock ? '<button type="button" class="sr-add" data-id="' + it.id + '" aria-label="' + LagerSearch.i18n.add + '">' + cartIcon() + '</button>' : '<span class="sr-out">' + LagerSearch.i18n.outStock + '</span>')
					+ '</span></a>';
			});
			html += '<a class="sr-all" href="' + data.viewAll + '">' + LagerSearch.i18n.viewAll + ' (' + data.total + ') ›</a>';
			box.innerHTML = html;
			loaded = items.length; total = data.total || 0; curQ = q;
			bindAdds(box);
			if (!box._scrollBound) { box._scrollBound = true; box.addEventListener('scroll', function () { if (box.scrollTop + box.clientHeight >= box.scrollHeight - 80) loadMore(); }); }
			show();
		}
		function rowHtml(it, q) {
			return '<a class="sr-row" href="' + it.url + '">'
				+ '<img class="sr-img" src="' + it.img + '" alt="" loading="lazy">'
				+ '<span class="sr-main">'
				+ (it.cat ? '<span class="sr-catname">' + hl(it.cat, q) + '</span>' : '')
				+ '<span class="sr-title">' + hl(it.title, q) + '</span>'
				+ '<span class="sr-meta">' + (it.sku ? 'Šifra: ' + esc(it.sku) : '') + '</span></span>'
				+ '<span class="sr-side"><span class="sr-price">' + esc(it.price) + '<small>' + (LagerSearch.i18n.withPdv || '') + '</small></span>'
				+ (it.inStock ? '<button type="button" class="sr-add" data-id="' + it.id + '" aria-label="' + LagerSearch.i18n.add + '">' + cartIcon() + '</button>' : '<span class="sr-out">' + LagerSearch.i18n.outStock + '</span>')
				+ '</span></a>';
		}
		function bindAdds(scope) {
			scope.querySelectorAll('.sr-add').forEach(function (btn) {
				if (btn._bound) return; btn._bound = true;
				btn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); quickAdd(btn); });
			});
		}
		function loadMore() {
			if (loading || !curQ || loaded >= total) return;
			loading = true;
			var reqQ = curQ, reqOffset = loaded;
			fetch(LagerSearch.ajax + '?action=lager_search&nonce=' + encodeURIComponent(LagerSearch.nonce) + '&q=' + encodeURIComponent(reqQ) + '&offset=' + reqOffset)
				.then(function (r) { return r.json(); })
				.then(function (data) {
					loading = false;
					if (reqQ !== curQ) return;
					var more = data.results || [];
					if (!more.length) { loaded = total; return; }
					var frag = ''; more.forEach(function (it) { frag += rowHtml(it, reqQ); });
					var allLink = box.querySelector('.sr-all');
					if (allLink) { allLink.insertAdjacentHTML('beforebegin', frag); } else { box.insertAdjacentHTML('beforeend', frag); }
					loaded += more.length;
					bindAdds(box);
				})
				.catch(function () { loading = false; });
		}

		function quickAdd(btn) {
			if (!LagerSearch.wcAdd) return;
			btn.classList.add('is-loading');
			var body = new URLSearchParams(); body.append('product_id', btn.getAttribute('data-id')); body.append('quantity', '1');
			fetch(LagerSearch.wcAdd, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					btn.classList.remove('is-loading');
					if (res && res.fragments) {
						Object.keys(res.fragments).forEach(function (sel) {
							document.querySelectorAll(sel).forEach(function (el) {
								var tmp = document.createElement('div'); tmp.innerHTML = res.fragments[sel];
								if (tmp.firstElementChild) el.replaceWith(tmp.firstElementChild);
							});
						});
					}
					btn.classList.add('is-added');
					setTimeout(function () { btn.classList.remove('is-added'); }, 1500);
				})
				.catch(function () { btn.classList.remove('is-loading'); });
		}

		function mark(rows) { rows.forEach(function (l, i) { l.classList.toggle('is-active', i === active); }); if (active >= 0) rows[active].scrollIntoView({ block: 'nearest' }); }
		function show() { box.removeAttribute('hidden'); }
		function hide() { box.setAttribute('hidden', ''); active = -1; }
	}
	function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
	function fold(s) { return s.toLowerCase().replace(/[žŽ]/g, 'z').replace(/[šŠ]/g, 's').replace(/[čćČĆ]/g, 'c').replace(/[đĐ]/g, 'd'); }
	function hl(text, q) { var e = esc(text), fe = fold(e), fq = fold(esc(q)), i = fq ? fe.indexOf(fq) : -1; return i < 0 ? e : e.slice(0, i) + '<mark>' + e.slice(i, i + fq.length) + '</mark>' + e.slice(i + fq.length); }
	function cartIcon() { return '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm10 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7.2 14h9.45a1 1 0 0 0 .96-.73L20 6H6.2l-.6-3H2v2h2l2.6 11.6A2 2 0 0 0 8.55 18H19v-2H8.42l.18-.8z"/></svg>'; }

	// Archive rows: quantity stepper + add that quantity to cart (AJAX, header badge updates).
	function lagerAddToCart(id, qty, btn) {
		if (!window.LagerSearch || !LagerSearch.wcAdd) return;
		if (btn) btn.classList.add('is-loading');
		var body = new URLSearchParams();
		body.append('product_id', id);
		body.append('quantity', qty || 1);
		fetch(LagerSearch.wcAdd, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (btn) btn.classList.remove('is-loading');
				if (res && res.fragments) {
					Object.keys(res.fragments).forEach(function (sel) {
						document.querySelectorAll(sel).forEach(function (el) {
							var t = document.createElement('div'); t.innerHTML = res.fragments[sel];
							if (t.firstElementChild) el.replaceWith(t.firstElementChild);
						});
					});
				}
				if (btn) { btn.classList.add('is-added'); setTimeout(function () { btn.classList.remove('is-added'); }, 1500); }
			})
			.catch(function () { if (btn) btn.classList.remove('is-loading'); });
	}
	// Generic steppers (single product, etc.). Archive rows run their own cart-aware
	// stepper below, so they are skipped here.
	document.querySelectorAll('.qtybox').forEach(function (box) {
		if (box.closest('.prow')) { return; }
		var input = box.querySelector('.qtybox__input');
		box.querySelectorAll('.qtybox__btn').forEach(function (b) {
			b.addEventListener('click', function () {
				var v = (parseInt(input.value, 10) || 1) + parseInt(b.getAttribute('data-dir'), 10);
				input.value = v < 1 ? 1 : v;
			});
		});
	});

	// Every module that WRITES to the cart announces it here; the product list, the
	// single-product buy box and the badges then re-read the server. Needed because a
	// change made in the drawer never navigates, so nothing else would hear about it —
	// the rows behind it would keep showing "U korpi" after the cart was emptied.
	function cartChanged() { document.dispatchEvent(new CustomEvent('lager:cart-updated')); }

	// Cart-count badges (header, mobile header, tab bar) share one class, so one pass
	// updates them all. Used after a bfcache restore, where the markup is stale.
	function syncCartBadges(items) {
		var total = 0;
		Object.keys(items || {}).forEach(function (k) { total += parseInt(items[k], 10) || 0; });
		document.querySelectorAll('.cartbtn__count').forEach(function (el) {
			el.textContent = total;
			if (total > 0) { el.removeAttribute('hidden'); } else { el.setAttribute('hidden', ''); }
		});
	}

	/**
	 * Archive rows — two states, so the stepper and the button never show different numbers:
	 *
	 *   not in cart : stepper = how many to add (local, min 1) · button "Dodaj u korpu" commits
	 *   in cart     : stepper = the live cart quantity (each +/- writes through, debounced)
	 *                 button reads "U korpi (N)" and always matches the stepper
	 *                 minus at 1 removes the item and returns the row to the first state
	 */
	(function () {
		var addBtns = document.querySelectorAll('.prow__add');
		if (!addBtns.length || !window.LagerSearch) return;

		function applyFragments(res) {
			if (res && res.fragments) {
				Object.keys(res.fragments).forEach(function (sel) {
					document.querySelectorAll(sel).forEach(function (el) {
						var t = document.createElement('div'); t.innerHTML = res.fragments[sel];
						if (t.firstElementChild) el.replaceWith(t.firstElementChild);
					});
				});
			}
		}

		function markRow(btn, qty) {
			var row = btn.closest('.prow'); if (!row) return;
			var inCart = qty > 0;
			row.classList.toggle('prow--incart', inCart);
			var label = btn.querySelector('span');
			if (label) {
				if (!btn.dataset.labelDefault) { btn.dataset.labelDefault = label.textContent; }
				label.textContent = inCart ? 'U korpi (' + qty + ')' : btn.dataset.labelDefault;
			}
			var inp = row.querySelector('.qtybox__input');
			if (inp) {
				inp.value = inCart ? qty : 1;
				// In cart, minus may go to 0 (= remove); before that, 1 is the floor.
				inp.min = inCart ? 0 : 1;
			}
		}

		var selfWrite = false;

		// Write a product's quantity to the cart. 0 removes it.
		// `addBtn` owns the product id; the .prow row is optional — related-product cards on
		// the single-product page reuse .prow__add but have no row around them.
		function setRowQty(addBtn, qty, btn) {
			if (!addBtn) return Promise.resolve();
			var row = addBtn.closest('.prow');
			if (btn) btn.classList.add('is-loading');
			if (row) row.classList.add('is-busy');
			var body = new URLSearchParams();
			body.append('product_id', addBtn.getAttribute('data-id'));
			body.append('quantity', qty);
			body.append('nonce', LagerSearch.nonce);
			return fetch(LagerSearch.setQty, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (btn) btn.classList.remove('is-loading');
					if (row) row.classList.remove('is-busy');
					applyFragments(res);
					markRow(addBtn, (res && typeof res.qty === 'number') ? res.qty : qty);
					selfWrite = true; cartChanged(); selfWrite = false;
				})
				.catch(function () {
					if (btn) btn.classList.remove('is-loading');
					if (row) row.classList.remove('is-busy');
					syncRows(); // desync is worse than a re-render: pull the truth back
				});
		}

		// Read the cart and mark EVERY row — including rows no longer in it, so a stale
		// "U korpi" can't survive (e.g. after removing the item on the cart page).
		function syncRows() {
			return fetch(LagerSearch.cartState + '&nonce=' + encodeURIComponent(LagerSearch.nonce))
				.then(function (r) { return r.json(); })
				.then(function (data) {
					var items = (data && data.items) || {};
					addBtns.forEach(function (btn) { markRow(btn, items[btn.getAttribute('data-id')] || 0); });
					syncCartBadges(items);
				}).catch(function () {});
		}
		syncRows();
		// Coming back via the browser's Back button restores the page from the bfcache, so
		// nothing re-runs on its own and the row would still claim to be in the cart.
		window.addEventListener('pageshow', function (e) { if (e.persisted) syncRows(); });
		// A write from anywhere else (drawer, checkout table) re-marks the rows. `selfWrite`
		// skips the round trip when this module was the one that made the change.
		document.addEventListener('lager:cart-updated', function () { if (!selfWrite) syncRows(); });

		// Cart-aware stepper. Once the row is in the cart, +/- IS the cart quantity, written
		// through after a short pause so rapid taps make one request.
		document.querySelectorAll('.prow .qtybox').forEach(function (box) {
			var row = box.closest('.prow');
			var input = box.querySelector('.qtybox__input');
			var timer = null;
			box.querySelectorAll('.qtybox__btn').forEach(function (b) {
				b.addEventListener('click', function () {
					var dir = parseInt(b.getAttribute('data-dir'), 10);
					var inCart = row.classList.contains('prow--incart');
					var v = (parseInt(input.value, 10) || 1) + dir;
					if (!inCart) { input.value = v < 1 ? 1 : v; return; }
					if (v < 0) { v = 0; }
					input.value = v;
					// Keep the button's number in step with the stepper while the write is pending.
					var addBtn = row.querySelector('.prow__add');
					var label = addBtn && addBtn.querySelector('span');
					if (label && v > 0) { label.textContent = 'U korpi (' + v + ')'; }
					clearTimeout(timer);
					timer = setTimeout(function () { setRowQty(row.querySelector('.prow__add'), v, b); }, 350);
				});
			});
			// Typing a quantity directly commits the same way.
			if (input) {
				input.addEventListener('change', function () {
					if (!row.classList.contains('prow--incart')) { return; }
					var v = parseInt(input.value, 10);
					if (isNaN(v) || v < 0) { v = 0; }
					setRowQty(row.querySelector('.prow__add'), v, null);
				});
			}
		});

		// "Dodaj u korpu" commits the chosen quantity.
		addBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var row = btn.closest('.prow');
				var inp = row ? row.querySelector('.qtybox__input') : null;
				var qty = inp ? (parseInt(inp.value, 10) || 1) : 1;
				if (qty < 1) { qty = 1; }
				setRowQty(btn, qty, btn).then(function () {
					btn.classList.add('is-added'); setTimeout(function () { btn.classList.remove('is-added'); }, 1200);
				});
			});
		});

		// Trash removes the item outright.
		document.querySelectorAll('.prow__remove').forEach(function (rb) {
			rb.addEventListener('click', function () {
				var row = rb.closest('.prow');
				if (row) setRowQty(row.querySelector('.prow__add'), 0, rb);
			});
		});
	})();

	// Pages with no product list still show a cart badge; refresh it after a bfcache restore.
	(function () {
		if (document.querySelector('.prow__add') || !window.LagerSearch || !LagerSearch.cartState) return;
		window.addEventListener('pageshow', function (e) {
			if (!e.persisted) return;
			fetch(LagerSearch.cartState + '&nonce=' + encodeURIComponent(LagerSearch.nonce))
				.then(function (r) { return r.json(); })
				.then(function (data) { syncCartBadges((data && data.items) || {}); })
				.catch(function () {});
		});
	})();

	// Single product: AJAX add/update + reflect cart state + remove.
	(function () {
		var addBtn = document.querySelector('.single__add');
		if (!addBtn || !window.LagerSearch) return;
		var form = addBtn.closest('.addcart');
		var qtyInput = form ? form.querySelector('input[name="quantity"]') : null;
		var removeBtn = document.querySelector('.single__remove');
		var labelEl = addBtn.querySelector('.single__add-label');
		var labelDefault = labelEl ? labelEl.textContent : 'Dodaj u korpu';
		var selfWrite = false;

		function applyFragments(res) {
			if (res && res.fragments) {
				Object.keys(res.fragments).forEach(function (sel) {
					document.querySelectorAll(sel).forEach(function (el) {
						var t = document.createElement('div'); t.innerHTML = res.fragments[sel];
						if (t.firstElementChild) el.replaceWith(t.firstElementChild);
					});
				});
			}
		}
		function mark(qty) {
			var inCart = qty > 0;
			if (labelEl) labelEl.textContent = inCart ? 'U korpi (' + qty + ')' : labelDefault;
			addBtn.classList.toggle('is-incart', inCart);
			if (qtyInput) qtyInput.value = inCart ? qty : 1;
			if (removeBtn) removeBtn.hidden = !inCart;
		}
		function setQty(qty, btn) {
			btn.classList.add('is-loading');
			var body = new URLSearchParams();
			body.append('product_id', addBtn.getAttribute('data-id'));
			body.append('quantity', qty);
			body.append('nonce', LagerSearch.nonce);
			fetch(LagerSearch.setQty, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					btn.classList.remove('is-loading');
					applyFragments(res);
					mark((res && typeof res.qty === 'number') ? res.qty : qty);
					selfWrite = true; cartChanged(); selfWrite = false;
				})
				.catch(function () { btn.classList.remove('is-loading'); });
		}
		// mark(0) when absent as well — otherwise a row that has since been emptied keeps
		// claiming "U korpi". Re-run on bfcache restore (Back from the cart) for the same reason.
		function syncSingle() {
			return fetch(LagerSearch.cartState + '&nonce=' + encodeURIComponent(LagerSearch.nonce))
				.then(function (r) { return r.json(); })
				.then(function (data) {
					var items = (data && data.items) || {};
					mark(items[addBtn.getAttribute('data-id')] || 0);
					syncCartBadges(items);
				}).catch(function () {});
		}
		syncSingle();
		window.addEventListener('pageshow', function (e) { if (e.persisted) syncSingle(); });
		document.addEventListener('lager:cart-updated', function () { if (!selfWrite) syncSingle(); });
		addBtn.addEventListener('click', function (e) { e.preventDefault(); setQty(qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1, addBtn); });
		if (removeBtn) removeBtn.addEventListener('click', function (e) { e.preventDefault(); setQty(0, removeBtn); });
	})();

	// Mini-cart drawer.
	(function () {
		// Every cart button opens the drawer: .cartbtn is the desktop util-bar one, .mobcart
		// the mobile header one. Both keep their href as the no-JS fallback.
		var cartBtns = document.querySelectorAll('.cartbtn, .mobcart');
		var drawer = document.querySelector('.minicart');
		var overlay = document.querySelector('.minicart-overlay');
		if (!cartBtns.length || !drawer || !overlay) return;

		function applyFragments(res) {
			if (res && res.fragments) {
				Object.keys(res.fragments).forEach(function (sel) {
					document.querySelectorAll(sel).forEach(function (el) {
						var t = document.createElement('div'); t.innerHTML = res.fragments[sel];
						if (t.firstElementChild) el.replaceWith(t.firstElementChild);
					});
				});
			}
		}
		function openCart() { drawer.hidden = false; overlay.hidden = false; requestAnimationFrame(function () { drawer.classList.add('is-open'); overlay.classList.add('is-open'); }); document.body.style.overflow = 'hidden'; }
		function closeCart() { drawer.classList.remove('is-open'); overlay.classList.remove('is-open'); document.body.style.overflow = ''; setTimeout(function () { drawer.hidden = true; overlay.hidden = true; }, 260); }

		cartBtns.forEach(function (btn) {
			btn.addEventListener('click', function (e) { e.preventDefault(); openCart(); });
		});
		overlay.addEventListener('click', closeCart);
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && drawer.classList.contains('is-open')) closeCart(); });

		// Update one product's cart quantity (0 removes), then refresh the drawer.
		function miniSet(id, qty, btn) {
			if (!window.LagerSearch || !LagerSearch.setQty) return;
			if (btn) btn.setAttribute('disabled', '');
			var body = new URLSearchParams();
			body.append('product_id', id);
			body.append('quantity', qty);
			body.append('nonce', LagerSearch.nonce);
			fetch(LagerSearch.setQty, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (r) { return r.json(); })
				.then(function (res) { applyFragments(res); cartChanged(); })
				.catch(function () { if (btn) btn.removeAttribute('disabled'); });
		}

		drawer.addEventListener('click', function (e) {
			if (e.target.closest('.minicart__close')) { closeCart(); return; }
			var item = e.target.closest('.minicart__item');
			// Remove a line.
			var rem = e.target.closest('.minicart__remove');
			if (rem && item) { miniSet(item.getAttribute('data-id'), 0, rem); return; }
			// Decrease / increase quantity (min 1; remove via the × button).
			var qbtn = e.target.closest('.qtybox__btn');
			if (qbtn && item) {
				var cur = parseInt(item.getAttribute('data-qty'), 10) || 1;
				var next = cur + parseInt(qbtn.getAttribute('data-dir'), 10);
				if (next < 1) next = 1;
				if (next !== cur) miniSet(item.getAttribute('data-id'), next, qbtn);
				return;
			}
			// Empty the whole cart.
			var clr = e.target.closest('.minicart__clear');
			if (clr && window.LagerSearch && LagerSearch.clearCart) {
				clr.setAttribute('disabled', '');
				var cbody = new URLSearchParams();
				cbody.append('nonce', LagerSearch.nonce);
				fetch(LagerSearch.clearCart, { method: 'POST', body: cbody, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
					.then(function (r) { return r.json(); })
					.then(function (res) { applyFragments(res); cartChanged(); })
					.catch(function () { clr.removeAttribute('disabled'); });
			}
		});
	})();

	// Checkout: editable quantity + remove on the order table (re-renders review).
	(function () {
		if (!document.body.classList.contains('woocommerce-checkout')) return;
		function apply(res) {
			if (res && res.fragments) {
				Object.keys(res.fragments).forEach(function (sel) {
					document.querySelectorAll(sel).forEach(function (el) {
						var t = document.createElement('div'); t.innerHTML = res.fragments[sel];
						if (t.firstElementChild) el.replaceWith(t.firstElementChild);
					});
				});
			}
		}
		function refresh() { if (window.jQuery) jQuery(document.body).trigger('update_checkout'); }
		function setQty(id, qty) {
			if (!window.LagerSearch || !LagerSearch.setQty) return;
			var body = new URLSearchParams();
			body.append('product_id', id); body.append('quantity', qty); body.append('nonce', LagerSearch.nonce);
			fetch(LagerSearch.setQty, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (r) { return r.json(); })
				.then(function (res) { apply(res); refresh(); })
				.catch(refresh);
		}
		document.addEventListener('click', function (e) {
			if (!e.target.closest('#order_review')) return;
			var rem = e.target.closest('.lo-remove');
			if (rem) { setQty(rem.getAttribute('data-id'), 0); return; }
			var qb = e.target.closest('.lo-qty .qtybox__btn');
			var row = e.target.closest('.cart_item');
			if (qb && row) {
				var cur = parseInt(row.getAttribute('data-qty'), 10) || 1;
				var next = cur + parseInt(qb.getAttribute('data-dir'), 10);
				if (next < 1) next = 1;
				if (next !== cur) setQty(row.getAttribute('data-id'), next);
			}
		});
	})();

	// Brands logo carousel — paged (4/3/2/1 per view), dash pagination, auto-advance, no arrows.
	(function () {
		var slider = document.querySelector('.brands__slider');
		if (!slider) return;
		var track = slider.querySelector('.brands__track');
		var dots = slider.querySelector('.brands__dots');
		var cells = Array.prototype.slice.call(track.children);
		if (!cells.length) return;
		var page = 0, timer = null;
		function perView() {
			var w = slider.clientWidth;
			// Phones/small tablets show 3 across (design handoff) — one logo per page left
			// 15 pagination dashes under a single logo.
			if (w < 700) return 3;
			if (w < 920) return 3;
			if (w < 1000) return 4;
			return 5;
		}
		function pageCount(pv) { return Math.max(1, Math.ceil(cells.length / pv)); }
		function render() {
			var pv = perView();
			var pages = pageCount(pv);
			if (page >= pages) { page = pages - 1; }
			// Center only when everything fits on one page; otherwise left-align so paging is exact.
			track.style.justifyContent = pages <= 1 ? 'center' : 'flex-start';
			var basis = 100 / pv;
			cells.forEach(function (c) { c.style.flex = '0 0 ' + basis + '%'; c.style.maxWidth = basis + '%'; });
			// Page by full views; the last page shows the remainder (e.g. 5 + 5 + 3).
			var offset = page * pv;
			track.style.transform = 'translateX(' + (-offset * basis) + '%)';
			dots.innerHTML = '';
			if (pages <= 1) { dots.style.display = 'none'; return; }
			dots.style.display = 'flex';
			for (var i = 0; i < pages; i++) {
				var b = document.createElement('button');
				b.type = 'button';
				b.setAttribute('aria-label', 'Strana ' + (i + 1));
				b.className = 'brands__dot' + (i === page ? ' is-active' : '');
				(function (idx) { b.addEventListener('click', function () { page = idx; render(); restart(); }); })(i);
				dots.appendChild(b);
			}
		}
		function nextPage() {
			var pages = pageCount(perView());
			if (pages > 1) { page = (page + 1) % pages; render(); }
		}
		function restart() { if (timer) { clearInterval(timer); } timer = setInterval(nextPage, 4500); }
		render();
		restart();
		slider.addEventListener('mouseenter', function () { if (timer) { clearInterval(timer); timer = null; } });
		slider.addEventListener('mouseleave', restart);
		var rt;
		window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(render, 150); });
	})();

	// Archive (mobile): one bar toggles the filter panel + category rail, which are
	// hidden by default below 980px so the product list is the first thing on screen.
	var fbar = document.querySelector('.filtersbar');
	if (fbar) {
		var fpanel = document.getElementById('archive-filters');
		fbar.addEventListener('click', function () {
			if (!fpanel) { return; }
			fbar.setAttribute('aria-expanded', fpanel.classList.toggle('is-open') ? 'true' : 'false');
		});
	}

	// ---- Mobile navigation (off-canvas drawer, per the design handoff) ----
	// Two stacked drawers: the main menu, and the category panel sliding over it.
	// Labels always stay links; the carets beside them do the opening.
	var masthead  = document.querySelector('.masthead');
	var toggle    = document.querySelector('.navtoggle');
	var scrim     = document.querySelector('.navscrim');
	var mega      = document.querySelector('.megamenu');
	var shopCaret = document.querySelector('.shopcats__caret');

	function closeCats() {
		if (mega) { mega.classList.remove('is-open'); }
		if (shopCaret) { shopCaret.setAttribute('aria-expanded', 'false'); }
		document.querySelectorAll('.megacat--has-sub.is-open').forEach(function (cat) {
			cat.classList.remove('is-open');
			var c = cat.querySelector('.megacat__caret');
			if (c) { c.setAttribute('aria-expanded', 'false'); }
		});
	}

	function openNav() {
		if (!masthead) { return; }
		masthead.classList.add('is-open');
		document.body.classList.add('nav-open');
		if (scrim) { scrim.hidden = false; }
		if (toggle) { toggle.setAttribute('aria-expanded', 'true'); }
	}

	function closeNav() {
		if (!masthead) { return; }
		masthead.classList.remove('is-open');
		document.body.classList.remove('nav-open');
		if (scrim) { scrim.hidden = true; }
		if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
		closeCats();
	}

	if (toggle && masthead) {
		toggle.addEventListener('click', function () {
			if (masthead.classList.contains('is-open')) { closeNav(); } else { openNav(); }
		});
	}
	if (scrim) { scrim.addEventListener('click', closeNav); }
	document.querySelectorAll('.mainnav__close, .megamenu__close').forEach(function (btn) {
		btn.addEventListener('click', closeNav);
	});
	var megaBack = document.querySelector('.megamenu__back');
	if (megaBack) { megaBack.addEventListener('click', closeCats); }
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape' || !masthead || !masthead.classList.contains('is-open')) { return; }
		if (mega && mega.classList.contains('is-open')) { closeCats(); } else { closeNav(); }
	});

	// "Prodavnica" label links to the shop; its caret opens the category panel.
	if (shopCaret && mega) {
		shopCaret.addEventListener('click', function () {
			shopCaret.setAttribute('aria-expanded', mega.classList.toggle('is-open') ? 'true' : 'false');
		});
	}
	// Each category label links to its page; its caret opens its subcategory list.
	document.querySelectorAll('.megacat__caret').forEach(function (caret) {
		caret.addEventListener('click', function () {
			var cat = caret.closest('.megacat--has-sub');
			if (!cat) { return; }
			caret.setAttribute('aria-expanded', cat.classList.toggle('is-open') ? 'true' : 'false');
		});
	});

	// Header search button reveals the util bar's existing search field (no duplicate input,
	// so the live-search typeahead keeps working) and focuses it.
	var mobSearch = document.querySelector('.mobsearch');
	var siteHeader = document.querySelector('.siteheader');
	if (mobSearch && siteHeader) {
		mobSearch.addEventListener('click', function () {
			var open = siteHeader.classList.toggle('is-searching');
			mobSearch.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) {
				var input = siteHeader.querySelector('.searchbar input');
				if (input) { input.focus(); }
			}
		});
	}
})();
