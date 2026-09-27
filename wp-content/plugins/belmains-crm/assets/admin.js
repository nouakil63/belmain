/* Belmains CRM — actions AJAX (metabox expédition, remboursement retour) */
(function ($) {
	'use strict';

	function post(data, $msg, $btn) {
		$msg.removeClass('is-error is-ok').text('…');
		$btn.prop('disabled', true);
		return $.post(BM_CRM.ajax, $.extend({ nonce: BM_CRM.nonce }, data))
			.done(function (r) {
				if (r && r.success) {
					$msg.addClass('is-ok').text(r.data.message || 'OK');
					if (r.data.reload) { setTimeout(function () { location.reload(); }, 600); }
				} else {
					$msg.addClass('is-error').text((r && r.data && r.data.message) || 'Erreur');
					$btn.prop('disabled', false);
				}
			})
			.fail(function () {
				$msg.addClass('is-error').text('Erreur réseau');
				$btn.prop('disabled', false);
			});
	}

	$(document).on('click', '.bm-mb .bm-act', function () {
		var $b = $(this), $mb = $b.closest('.bm-mb');
		post({ action: 'bm_shipment_action', act: $b.data('act'), order_id: $mb.data('order') }, $mb.find('.bm-act-msg'), $b);
	});

	$(document).on('click', '.bm-refund', function () {
		var $b = $(this);
		var amount = $('input[name="bm_refund_amount"]').val();
		var restock = $('input[name="bm_restock"]').is(':checked') ? 1 : 0;
		if (!amount || parseFloat(amount) <= 0) { alert('Indiquez un montant à rembourser.'); return; }
		if (!confirm('Rembourser ' + amount + ' € sur la commande ? Cette action est définitive.')) { return; }
		post({ action: 'bm_return_refund', return_id: $b.data('return'), amount: amount, restock: restock }, $b.siblings('.bm-act-msg'), $b);
	});

	$('#bm-all').on('change', function () {
		$('input[name="ids[]"]').prop('checked', this.checked);
	});
})(jQuery);
