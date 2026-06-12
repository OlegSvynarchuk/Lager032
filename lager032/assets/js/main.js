/* Lager032 — front-end interactions (header redesign 2026-06-12). */
(function () {
	'use strict';

	// "Svi proizvodi" mega-dropdown: hover opens on desktop (CSS); click/tap toggles too.
	var shop = document.querySelector('.shopcats');
	if (shop) {
		var btn = shop.querySelector('.shopcats__btn');
		var menu = shop.querySelector('.megamenu');
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			var open = menu.classList.toggle('is-open');
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		document.addEventListener('click', function (e) {
			if (!shop.contains(e.target)) {
				menu.classList.remove('is-open');
				btn.setAttribute('aria-expanded', 'false');
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				menu.classList.remove('is-open');
				btn.setAttribute('aria-expanded', 'false');
			}
		});
	}

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
