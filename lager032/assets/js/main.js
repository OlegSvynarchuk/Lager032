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
		var timer, controller, items = [], active = -1, lastQ = '';

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
					+ '<span class="sr-main"><span class="sr-title">' + hl(it.title, q) + '</span>'
					+ '<span class="sr-meta">' + (it.sku ? 'Šifra: ' + esc(it.sku) : '') + '</span></span>'
					+ '<span class="sr-side"><span class="sr-price">' + esc(it.price) + '<small>' + (LagerSearch.i18n.withPdv || '') + '</small></span>'
					+ (it.inStock ? '<button type="button" class="sr-add" data-id="' + it.id + '" aria-label="' + LagerSearch.i18n.add + '">' + cartIcon() + '</button>' : '<span class="sr-out">' + LagerSearch.i18n.outStock + '</span>')
					+ '</span></a>';
			});
			html += '<a class="sr-all" href="' + data.viewAll + '">' + LagerSearch.i18n.viewAll + ' (' + data.total + ') ›</a>';
			box.innerHTML = html;
			box.querySelectorAll('.sr-add').forEach(function (btn) {
				btn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); quickAdd(btn); });
			});
			show();
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
	function hl(text, q) { var e = esc(text), i = e.toLowerCase().indexOf(esc(q).toLowerCase()); return i < 0 ? e : e.slice(0, i) + '<mark>' + e.slice(i, i + q.length) + '</mark>' + e.slice(i + q.length); }
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
	document.querySelectorAll('.qtybox').forEach(function (box) {
		var input = box.querySelector('.qtybox__input');
		box.querySelectorAll('.qtybox__btn').forEach(function (b) {
			b.addEventListener('click', function () {
				var v = (parseInt(input.value, 10) || 1) + parseInt(b.getAttribute('data-dir'), 10);
				input.value = v < 1 ? 1 : v;
			});
		});
	});
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
			if (label) label.textContent = inCart ? 'U korpi (' + qty + ')' : 'Dodaj';
			var inp = row.querySelector('.qtybox__input');
			if (inp) inp.value = inCart ? qty : 1;
		}
		// On load: reflect the current cart on the list (highlight + quantity).
		fetch(LagerSearch.cartState + '&nonce=' + encodeURIComponent(LagerSearch.nonce))
			.then(function (r) { return r.json(); })
			.then(function (data) {
				var items = (data && data.items) || {};
				addBtns.forEach(function (btn) {
					var id = btn.getAttribute('data-id');
					if (items[id]) markRow(btn, items[id]);
				});
			}).catch(function () {});
		// Click: SET the cart to the stepper quantity (add / update), keeping list ↔ cart in sync.
		addBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var row = btn.closest('.prow');
				var inp = row ? row.querySelector('.qtybox__input') : null;
				var qty = inp ? (parseInt(inp.value, 10) || 1) : 1;
				btn.classList.add('is-loading');
				var body = new URLSearchParams();
				body.append('product_id', btn.getAttribute('data-id'));
				body.append('quantity', qty);
				body.append('nonce', LagerSearch.nonce);
				fetch(LagerSearch.setQty, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						btn.classList.remove('is-loading');
						applyFragments(res);
						markRow(btn, (res && typeof res.qty === 'number') ? res.qty : qty);
						btn.classList.add('is-added'); setTimeout(function () { btn.classList.remove('is-added'); }, 1200);
					})
					.catch(function () { btn.classList.remove('is-loading'); });
			});
		});

		// Remove-from-cart button (visible on in-cart rows) — sets the quantity to 0.
		document.querySelectorAll('.prow__remove').forEach(function (rb) {
			rb.addEventListener('click', function () {
				var row = rb.closest('.prow');
				var addBtn = row ? row.querySelector('.prow__add') : null;
				rb.classList.add('is-loading');
				var body = new URLSearchParams();
				body.append('product_id', rb.getAttribute('data-id'));
				body.append('quantity', '0');
				body.append('nonce', LagerSearch.nonce);
				fetch(LagerSearch.setQty, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						rb.classList.remove('is-loading');
						applyFragments(res);
						if (addBtn) markRow(addBtn, (res && typeof res.qty === 'number') ? res.qty : 0);
					})
					.catch(function () { rb.classList.remove('is-loading'); });
			});
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
				.then(function (res) { btn.classList.remove('is-loading'); applyFragments(res); mark((res && typeof res.qty === 'number') ? res.qty : qty); })
				.catch(function () { btn.classList.remove('is-loading'); });
		}
		fetch(LagerSearch.cartState + '&nonce=' + encodeURIComponent(LagerSearch.nonce))
			.then(function (r) { return r.json(); })
			.then(function (data) { var items = (data && data.items) || {}; var id = addBtn.getAttribute('data-id'); if (items[id]) mark(items[id]); }).catch(function () {});
		addBtn.addEventListener('click', function (e) { e.preventDefault(); setQty(qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1, addBtn); });
		if (removeBtn) removeBtn.addEventListener('click', function (e) { e.preventDefault(); setQty(0, removeBtn); });
	})();

	// Mini-cart drawer.
	(function () {
		var cartBtn = document.querySelector('.cartbtn');
		var drawer = document.querySelector('.minicart');
		var overlay = document.querySelector('.minicart-overlay');
		if (!cartBtn || !drawer || !overlay) return;

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

		cartBtn.addEventListener('click', function (e) { e.preventDefault(); openCart(); });
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
				.then(function (res) { applyFragments(res); })
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
					.then(function (res) { applyFragments(res); })
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
			if (w < 480) return 1;
			if (w < 700) return 2;
			if (w < 920) return 3;
			if (w < 1000) return 4;
			return 5;
		}
		function pageCount(pv) { return Math.max(1, Math.ceil(cells.length / pv)); }
		function render() {
			var pv = perView();
			var pages = pageCount(pv);
			if (page >= pages) { page = pages - 1; }
			var basis = 100 / pv;
			cells.forEach(function (c) { c.style.flex = '0 0 ' + basis + '%'; c.style.maxWidth = basis + '%'; });
			// Clamp so the final page still shows a full row of logos (no scrolling past the end).
			var offset = Math.min(page * pv, Math.max(0, cells.length - pv));
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

	// Mobile nav toggle.
	var toggle = document.querySelector('.navtoggle');
	var masthead = document.querySelector('.masthead');
	if (toggle && masthead) {
		toggle.addEventListener('click', function () {
			var open = masthead.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}
})();
