/* AffilStack Blog: header dropdowns and the mobile menu. */
(function () {
	var toggles = document.querySelectorAll('.afs-nav__toggle');

	function closeAll(except) {
		toggles.forEach(function (button) {
			if (button === except) { return; }
			button.setAttribute('aria-expanded', 'false');
			var menu = document.getElementById(button.getAttribute('aria-controls'));
			if (menu) { menu.classList.remove('is-open'); }
		});
	}

	toggles.forEach(function (button) {
		button.addEventListener('click', function (event) {
			event.stopPropagation();
			var open = button.getAttribute('aria-expanded') === 'true';
			closeAll(button);
			button.setAttribute('aria-expanded', open ? 'false' : 'true');
			var menu = document.getElementById(button.getAttribute('aria-controls'));
			if (menu) { menu.classList.toggle('is-open', !open); }
		});
	});

	document.addEventListener('click', function (event) {
		if (!event.target.closest('.afs-nav__item') && !event.target.closest('.afs-menu')) { closeAll(null); }
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') { closeAll(null); }
	});

	var burger = document.querySelector('.afs-burger');
	var mobile = document.getElementById('afs-mobile');

	if (burger && mobile) {
		burger.addEventListener('click', function () {
			var open = burger.getAttribute('aria-expanded') === 'true';
			burger.setAttribute('aria-expanded', open ? 'false' : 'true');
			mobile.classList.toggle('is-open', !open);
		});
	}
})();
