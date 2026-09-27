/* Belmains Builder — aperçu en direct (côté iframe du Customizer) */
(function ($) {
	'use strict';
	if (!window.wp || !wp.customize || !window.BM_PREVIEW) { return; }

	/* miroir de bm_fmt() (PHP) */
	function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
	function fmt(s, br) {
		var t = esc(s);
		t = t.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/\*(.+?)\*/g, '<em>$1</em>');
		t = t.replace(/ \?/g, '&nbsp;?').replace(/ !/g, '&nbsp;!').replace(/ :/g, '&nbsp;:');
		return br === false ? t : t.replace(/\r\n|\r|\n/g, '<br>');
	}

	$.each(BM_PREVIEW.fields, function (key, cfg) {
		wp.customize(key, function (value) {
			value.bind(function (v) {
				if (cfg.var) {
					document.documentElement.style.setProperty(cfg.var, v + (cfg.unit || ''));
					return;
				}
				if (cfg.type === 'title') {
					var lines = String(v).split(/\r\n|\r|\n/).filter(function (l) { return l.trim() !== ''; });
					$('[data-bm="' + key + '"]').html(lines.map(function (l) { return '<span class="hline"><span style="transform:none">' + fmt(l, false) + '</span></span>'; }).join(''));
					return;
				}
				if (key === 'marquee_text') {
					$('[data-marquee]').attr('data-text', v);
					document.dispatchEvent(new Event('bm:marquee'));
					return;
				}
				if (cfg.type === 'text' || cfg.type === 'rich') {
					$('[data-bm="' + key + '"]').html(fmt(v));
				}
			});
		});
	});

	/* après un rafraîchissement partiel de section : relancer les liaisons JS */
	if (wp.customize.selectiveRefresh) {
		wp.customize.selectiveRefresh.bind('partial-content-rendered', function () {
			document.dispatchEvent(new Event('bm:rebind'));
			document.dispatchEvent(new Event('bm:marquee'));
		});
	}
})(jQuery);
