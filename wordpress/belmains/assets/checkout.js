/* Order page: quantity and removal controls of the order summary. */
(function ($) {
  'use strict';
  var config = window.BelmainsCheckout;
  if (!config || !$) { return; }
  var busy = false;
  var refocus = null;

  function announce(message, isError) {
    $('.belmains-cart-feedback').text(message || '').toggleClass('is-error', !!isError);
  }

  function send(key, quantity, focusOn) {
    if (busy || !key) { return; }
    busy = true;
    refocus = focusOn || null;
    announce('');
    var $summary = $('.woocommerce-checkout-review-order-table').addClass('is-updating');
    $.post(config.url, { nonce: config.nonce, key: key, quantity: quantity })
      .done(function (response) {
        if (!response || !response.success) { return fail(response); }
        if (response.data.empty) { window.location.assign(response.data.redirect); return; }
        announce(config.updated);
        $(document.body).trigger('update_checkout');
      })
      .fail(function (xhr) { fail(xhr.responseJSON); })
      .always(function () { busy = false; $summary.removeClass('is-updating'); });
  }

  function fail(response) {
    announce(response && response.data && response.data.message ? response.data.message : config.failed, true);
    // Put back what the server really holds.
    $(document.body).trigger('update_checkout');
  }

  function bounds($input) {
    return { min: parseInt($input.attr('min'), 10) || 1, max: parseInt($input.attr('max'), 10) || 1 };
  }

  $(document.body)
    .on('click', '.belmains-qty-step', function () {
      var $controls = $(this).closest('.belmains-line-controls');
      var $input = $controls.find('.belmains-qty-input');
      var limit = bounds($input);
      var step = parseInt($(this).data('step'), 10) || 0;
      var next = Math.min(limit.max, Math.max(limit.min, (parseInt($input.val(), 10) || limit.min) + step));
      if (String(next) === String($input.prop('defaultValue'))) { return; }
      $input.val(next);
      send($controls.data('cart-key'), next, step > 0 ? '[data-step="1"]' : '[data-step="-1"]');
    })
    .on('change', '.belmains-qty-input', function () {
      var $input = $(this);
      var limit = bounds($input);
      var typed = parseInt($input.val(), 10);
      var next = isNaN(typed) ? limit.min : Math.min(limit.max, Math.max(limit.min, typed));
      $input.val(next);
      if (String(next) === String($input.prop('defaultValue'))) { return; }
      send($input.closest('.belmains-line-controls').data('cart-key'), next, '.belmains-qty-input');
    })
    // Enter in the quantity field must never place the order.
    .on('keydown', '.belmains-qty-input', function (event) {
      if (event.key === 'Enter') { event.preventDefault(); $(this).trigger('change'); }
    })
    .on('click', '.belmains-line-remove', function () {
      send($(this).closest('.belmains-line-controls').data('cart-key'), 0, null);
    })
    // The summary is rebuilt after each refresh: hand the focus back to the control just used.
    .on('updated_checkout', function () {
      if (!refocus) { return; }
      var $target = $('.woocommerce-checkout-review-order-table .belmains-line-controls').first().find(refocus).first();
      if ($target.length && $target.prop('disabled')) { $target = $target.closest('.belmains-line-controls').find('.belmains-qty-input'); }
      refocus = null;
      if ($target.length) { $target.trigger('focus'); }
    });
})(window.jQuery);
