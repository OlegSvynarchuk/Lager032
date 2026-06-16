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
