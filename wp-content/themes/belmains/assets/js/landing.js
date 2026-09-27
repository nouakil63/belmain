/* Belmains — animations de la page d'accueil (issu de la proposition v1, adapté au thème) */
(function () {
	'use strict';
	var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* textes défilants */
	function fillMarquees() {
		document.querySelectorAll('[data-marquee]').forEach(function (el) {
			var t = el.getAttribute('data-text') || '';
			var html = '';
			for (var i = 0; i < 6; i++) { html += '<span class="mq-item">' + t.replace(/</g, '&lt;') + ' <i>✦</i></span>'; }
			el.innerHTML = html + html; /* piste doublée pour la boucle -50% */
		});
	}
	fillMarquees();
	document.addEventListener('bm:marquee', fillMarquees);

	/* ombre header */
	var hdr = document.getElementById('hdr');
	if (hdr) { addEventListener('scroll', function () { hdr.classList.toggle('scrolled', scrollY > 8); }, { passive: true }); }

	/* sélection des lots → le bouton panier suit le lot choisi */
	function bindLots() {
		var buy = document.getElementById('bmBuy');
		document.querySelectorAll('.lot').forEach(function (b) {
			b.addEventListener('click', function () {
				document.querySelectorAll('.lot').forEach(function (x) { x.setAttribute('aria-pressed', 'false'); });
				b.setAttribute('aria-pressed', 'true');
				if (buy && b.dataset.url) { buy.setAttribute('href', b.dataset.url); }
			});
		});
	}
	bindLots();
	document.addEventListener('bm:rebind', bindLots);

	if (reduced) { return; /* tout le reste est du mouvement */ }

	/* révélations : on ne masque que ce qui est encore hors écran */
	var io = new IntersectionObserver(function (entries) {
		entries.forEach(function (e) {
			if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
		});
	}, { rootMargin: '0px 0px -8% 0px', threshold: .08 });
	function observeReveals(root) {
		(root || document).querySelectorAll('[data-r]').forEach(function (el) {
			if (el.classList.contains('in') || el.classList.contains('pre')) { return; }
			var r = el.getBoundingClientRect();
			if (r.top > innerHeight * 0.92) { el.classList.add('pre'); io.observe(el); }
		});
	}
	observeReveals();
	document.addEventListener('bm:rebind', function () { observeReveals(); });

	/* count-up */
	var fmt = function (n) { return n >= 1000 ? n.toLocaleString('fr-FR').replace(/ | /g, ' ') : String(n); };
	var cio = new IntersectionObserver(function (entries) {
		entries.forEach(function (e) {
			if (!e.isIntersecting) { return; }
			cio.unobserve(e.target);
			var end = +e.target.getAttribute('data-count'), t0 = null;
			if (!end) { return; }
			function step(t) {
				if (!t0) { t0 = t; }
				var p = Math.min((t - t0) / 1200, 1); p = 1 - Math.pow(1 - p, 3);
				e.target.textContent = fmt(Math.round(end * p));
				if (p < 1) { requestAnimationFrame(step); }
			}
			requestAnimationFrame(step);
		});
	}, { threshold: .6 });
	document.querySelectorAll('[data-count]').forEach(function (el) { cio.observe(el); });

	/* 360° piloté au scroll (12 vues si fournies, sinon compteur) */
	function initTurn() {
		var turn = document.getElementById('turn');
		if (!turn) { return; }
		var needle = document.getElementById('needle');
		var num = document.getElementById('turnNum'), deg = document.getElementById('turnDeg'), lbl = document.getElementById('turnLbl'), img = document.getElementById('turnImg');
		var frames = [];
		try { frames = JSON.parse(turn.getAttribute('data-frames') || '[]'); } catch (e) { frames = []; }
		frames.forEach(function (src) { var i = new Image(); i.src = src; });
		var names = ['face', 'trois-quarts droit', 'profil droit', 'arrière droit', 'dos', 'arrière gauche', 'profil gauche', 'trois-quarts gauche'];
		var last = -1;
		function onScroll() {
			var r = turn.getBoundingClientRect();
			var total = r.height - innerHeight;
			var p = Math.min(Math.max(-r.top / total, 0), 1);
			var angle = Math.round(p * 360);
			var frame = Math.min(Math.floor(p * 12) + 1, 12);
			if (needle) { needle.style.transform = 'rotate(' + angle + 'deg)'; }
			if (num) { num.textContent = frame; }
			if (deg) { deg.textContent = angle + '°'; }
			if (lbl) { lbl.textContent = names[Math.floor((angle % 360) / 45)] + ' — ' + angle + '°'; }
			if (img && frames.length && frame !== last) { img.src = frames[Math.min(frame, frames.length) - 1]; last = frame; }
		}
		addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}
	initTurn();

	/* parallaxe douce du visuel hero */
	var hp = document.getElementById('heroPhoto');
	if (hp) {
		addEventListener('scroll', function () {
			if (scrollY < 900) { hp.style.transform = 'translateY(' + (scrollY * 0.06) + 'px)'; }
		}, { passive: true });
	}
})();
