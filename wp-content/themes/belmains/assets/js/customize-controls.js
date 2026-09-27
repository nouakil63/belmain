/* Belmains Builder — contrôle « sections » (tri + visibilité) */
(function ($) {
	'use strict';
	wp.customize.bind('ready', function () {
		$('.bm-sortable').each(function () {
			var $list = $(this), $input = $list.siblings('.bm-sortable-value');
			function sync() {
				var out = [];
				$list.children('li').each(function () {
					out.push($(this).data('id') + ':' + ($(this).find('input').is(':checked') ? '1' : '0'));
				});
				$input.val(out.join(',')).trigger('change');
			}
			$list.sortable({ handle: '.bm-handle', axis: 'y', update: sync });
			$list.on('change', 'input', sync);
		});
	});
})(jQuery);
