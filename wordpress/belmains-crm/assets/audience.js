(() => {
  'use strict';
  const config = window.BelmainsAudience;
  if (!config) return;
  const panel = document.getElementById('bcrm-consent');
  const choices = document.getElementById('bcrm-privacy-choice');
  const read = name => document.cookie.split('; ').find(v => v.startsWith(name + '='))?.split('=').slice(1).join('=') || '';
  const write = (name, value, seconds) => { document.cookie = `${name}=${value};path=/;max-age=${seconds};SameSite=Lax${location.protocol === 'https:' ? ';Secure' : ''}`; };
  const randomId = () => {
    const bytes = new Uint8Array(16); crypto.getRandomValues(bytes);
    return Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
  };
  let started = false;
  let productSeen = false;
  let lastCart = 0;
  const source = () => {
    try {
      const utm = new URLSearchParams(location.search).get('utm_source');
      const ref = document.referrer ? new URL(document.referrer).hostname : '';
      const value = utm || (ref && ref !== location.hostname ? ref : 'direct');
      return value.toLowerCase().replace(/[^a-z0-9._-]/g, '').slice(0, 100) || 'direct';
    } catch (_) { return 'direct'; }
  };
  const track = event => {
    if (read('bcrm_consent') !== 'yes') return;
    try {
      if (!read('bcrm_v')) write('bcrm_v', randomId(), 30 * 86400);
      if (!read('bcrm_s')) {
        write('bcrm_s', randomId(), 1800);
        write('bcrm_src', source(), 1800);
        const campaign = (new URLSearchParams(location.search).get('utm_campaign') || '').replace(/[^a-zA-Z0-9._-]/g, '').slice(0,100);
        write('bcrm_cmp', campaign, 1800);
      }
      else write('bcrm_s', read('bcrm_s'), 1800);
      if (!read('bcrm_v') || !read('bcrm_s')) return;
      fetch(config.endpoint, {
        method: 'POST', credentials: 'same-origin', keepalive: true,
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: randomId(), event, path: location.pathname, source: read('bcrm_src') || source(), device: innerWidth < 768 ? 'mobile' : innerWidth < 1024 ? 'tablet' : 'desktop'})
      }).catch(() => {});
    } catch (_) { /* Cookie or browser restrictions: no measurement. */ }
  };
  const seeProduct = () => { if (!productSeen && read('bcrm_consent') === 'yes') { productSeen = true; track('product_view'); } };
  const start = () => {
    if (started) return;
    started = true;
    track('pageview');
    if (config.isProduct) seeProduct();
    const product = document.getElementById('fiche-produit');
    if (product && 'IntersectionObserver' in window) {
      const observer = new IntersectionObserver(entries => { if (entries.some(e => e.isIntersecting)) { seeProduct(); if (productSeen) observer.disconnect(); } }, {threshold: 0.15});
      observer.observe(product);
    }
    if (config.isCheckout) track('checkout');
  };
  const cartAdded = () => { if (Date.now() - lastCart > 800) { track('add_to_cart'); lastCart = Date.now(); } };
  // Only WooCommerce confirmations count; the old presentation cart is intentionally excluded.
  document.body.addEventListener('wc-blocks_added_to_cart', cartAdded);
  if (window.jQuery) window.jQuery(document.body).on('added_to_cart.bcrm', cartAdded);
  document.querySelectorAll('[data-bcrm-consent]').forEach(button => button.addEventListener('click', () => {
    const value = button.dataset.bcrmConsent;
    write('bcrm_consent', value, 180 * 86400);
    if (value === 'yes') start();
    else { ['bcrm_v','bcrm_s','bcrm_src','bcrm_cmp'].forEach(name => write(name, '', 0)); started = false; productSeen = false; }
    if (panel) panel.hidden = true;
    choices?.focus({preventScroll: true});
  }));
  choices?.addEventListener('click', () => { if (panel) { panel.hidden = false; panel.querySelector('button')?.focus(); } });
  if (read('bcrm_consent') === 'yes') start();
  else if (!read('bcrm_consent') && !(navigator.globalPrivacyControl || navigator.doNotTrack === '1')) { if (panel) panel.hidden = false; }
})();
