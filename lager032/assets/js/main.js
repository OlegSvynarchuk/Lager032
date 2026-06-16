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
					+ '<span class="sr-side"><span class="sr-price">' + esc(it.price) + '</span>'
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
