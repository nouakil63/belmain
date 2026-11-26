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
  // Two separate purposes, each with its own choice. A purpose that is not configured is never asked.
  const meta = config.meta && typeof config.meta === 'object' ? config.meta : null;
  const purposes = {};
  if (config.audience) purposes.audience = 'bcrm_consent';
  if (meta) purposes.ads = 'bcrm_ads';
  const allowed = purpose => Boolean(purposes[purpose]) && read(purposes[purpose]) === 'yes';
  let started = false;
  let productSeen = false;
  let productInView = false;
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
    if (!allowed('audience')) return;
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
  const seeProduct = () => { if (!productSeen && allowed('audience')) { productSeen = true; track('product_view'); } };
  const start = () => {
    if (started) return;
    started = true;
    track('pageview');
    if (config.isProduct || productInView) seeProduct();
    if (config.isCheckout) track('checkout');
  };
  const stop = () => { ['bcrm_v','bcrm_s','bcrm_src','bcrm_cmp'].forEach(name => write(name, '', 0)); started = false; productSeen = false; };

  // Meta: the official extension is decided on the server, request by request. A page served
  // before the accord contains nothing of it, so an accord reloads the page once; a withdrawal
  // silences the pixel at once and the next pages are served without it.
  const expire = name => {
    const parts = location.hostname.split('.');
    document.cookie = `${name}=;path=/;max-age=0`;
    for (let i = 0; i < parts.length - 1; i++) document.cookie = `${name}=;path=/;domain=.${parts.slice(i).join('.')};max-age=0`;
  };
  const stopMeta = () => {
    if (typeof window.fbq === 'function') window.fbq('consent', 'revoke');
    ['_fbp', '_fbc', 'wc_facebook_signals_state'].forEach(expire);
  };

  const product = document.getElementById('fiche-produit');
  if (product && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      productInView = entries.some(e => e.isIntersecting);
      if (!productInView) return;
      seeProduct();
      if (!purposes.audience || productSeen) observer.disconnect();
    }, {threshold: 0.15});
    observer.observe(product);
  }
  const cartAdded = () => { if (Date.now() - lastCart > 800) { track('add_to_cart'); lastCart = Date.now(); } };
  // Only WooCommerce confirmations count; the old presentation cart is intentionally excluded.
  document.body.addEventListener('wc-blocks_added_to_cart', cartAdded);
  if (window.jQuery) window.jQuery(document.body).on('added_to_cart.bcrm', cartAdded);

  const apply = () => {
    if (purposes.audience) { if (allowed('audience')) start(); else if (started) stop(); }
  };
  const save = decide => {
    Object.keys(purposes).forEach(purpose => {
      const value = decide(purpose) ? 'yes' : 'no';
      write(purposes[purpose], value, 180 * 86400);
      if (value === 'no' && purpose === 'audience') stop();
      if (value === 'no' && purpose === 'ads') stopMeta();
    });
    if (panel) panel.hidden = true;
    // The reloaded page is served with the official extension and does all the measuring itself.
    if (allowed('ads') && !meta.live && meta.reload) { location.reload(); return; }
    apply();
    choices?.focus({preventScroll: true});
  };
  const details = document.getElementById('bcrm-consent-purposes');
  const custom = panel?.querySelector('[data-bcrm-consent="custom"]');
  const boxes = () => Array.from(details?.querySelectorAll('[data-bcrm-purpose]') || []);
  const closeDetails = () => { if (details) details.hidden = true; if (custom) { custom.textContent = 'Personnaliser'; custom.setAttribute('aria-expanded', 'false'); } };
  document.querySelectorAll('[data-bcrm-consent]').forEach(button => button.addEventListener('click', () => {
    const value = button.dataset.bcrmConsent;
    if (value === 'custom') {
      if (details && details.hidden) {
        // Nothing is ticked in advance: only an earlier explicit "yes" is shown as such.
        boxes().forEach(box => { box.checked = allowed(box.dataset.bcrmPurpose); });
        details.hidden = false; button.textContent = 'Enregistrer mes choix'; button.setAttribute('aria-expanded', 'true');
        boxes()[0]?.focus();
        return;
      }
      const chosen = Object.fromEntries(boxes().map(box => [box.dataset.bcrmPurpose, box.checked]));
      closeDetails(); save(purpose => Boolean(chosen[purpose]));
      return;
    }
    closeDetails(); save(() => value === 'yes');
  }));
  choices?.addEventListener('click', () => { if (panel) { panel.hidden = false; panel.querySelector('button')?.focus(); } });
  apply();
  const pending = Object.keys(purposes).some(purpose => !read(purposes[purpose]));
  if (pending && !(navigator.globalPrivacyControl || navigator.doNotTrack === '1')) { if (panel) panel.hidden = false; }
})();
