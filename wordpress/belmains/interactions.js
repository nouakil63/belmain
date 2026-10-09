/* The server supplies content and prices; optional sections never block shopping. */
(() => {
 'use strict';
 const $ = (selector, root = document) => root?.querySelector(selector) || null;
 const $$ = (selector, root = document) => root ? [...root.querySelectorAll(selector)] : [];
 const on = (node, event, fn, options) => node?.addEventListener(event, fn, options);
 const motionMedia = matchMedia('(prefers-reduced-motion: reduce)');
 const shop = window.BelmainsShop || {};
 let reduced = motionMedia.matches, motionContext, quantity = 1;
 const header = $('[data-kd-menu]'), drawer = $('[data-kd-drawer]'), overlay = $('[data-kd-overlay]'), burger = $('[data-kd-burger]');
 let menuFocus;
 function menu(open) {
  if (!drawer || !burger || !overlay) return;
  if (open) menuFocus = document.activeElement;
  drawer.classList.toggle('is-open', open); overlay.classList.toggle('is-open', open);
  drawer.inert = !open; drawer.setAttribute('aria-hidden', String(!open)); drawer.setAttribute('aria-modal', String(open)); burger.setAttribute('aria-expanded', String(open)); document.documentElement.classList.toggle('menu-open', open);
  if (open) $('[data-kd-close]')?.focus(); else menuFocus?.focus();
 }
 if (drawer && burger) { drawer.inert = true; drawer.setAttribute('role', 'dialog'); drawer.setAttribute('aria-label', 'Navigation'); drawer.id = 'mobile-menu'; burger.setAttribute('aria-controls', drawer.id); }
 on(burger, 'click', () => menu(!drawer?.classList.contains('is-open')));
 on($('[data-kd-close]'), 'click', () => menu(false)); on(overlay, 'click', () => menu(false)); $$('a', drawer).forEach(a => on(a, 'click', () => menu(false)));
 on(document, 'keydown', event => {
  if (!drawer?.classList.contains('is-open')) return;
  if (event.key === 'Escape') menu(false);
  if (event.key === 'Tab') { const focusable = $$('a[href],button', drawer).filter(el => el.getClientRects().length); if (!focusable.length) return; if (event.shiftKey && document.activeElement === focusable[0]) { event.preventDefault(); focusable.at(-1).focus(); } else if (!event.shiftKey && document.activeElement === focusable.at(-1)) { event.preventDefault(); focusable[0].focus(); } }
 });
 let scrollQueued = false;
 function scrollState() { header?.classList.toggle('is-stuck', scrollY > 45); scrollQueued = false; }
 on(window, 'scroll', () => { if (!scrollQueued) { scrollQueued = true; requestAnimationFrame(scrollState); } }, { passive: true }); scrollState();
 on(window, 'resize', () => { if (innerWidth > 1100 && drawer?.classList.contains('is-open')) menu(false); });
 const stage = $('.product-stage'), views = $$('.product-view'), thumbs = $$('[data-gallery]');
 const film = $('#product-film'), filmToggle = $('#product-film-toggle');
 const saveData = !!navigator.connection?.saveData;
 let active = 0, filmInView = false, filmChoice = null;
 function filmState() {
  if (!film || !filmToggle) return;
  const playing = !film.paused;
  filmToggle.setAttribute('aria-label', playing ? 'Mettre la vidéo en pause' : 'Lire la vidéo');
  $('.product-film-icon', filmToggle).textContent = playing ? 'Ⅱ' : '▶'; $('.product-film-state', filmToggle).textContent = playing ? 'Pause' : 'Lire';
 }
 function syncFilm() {
  if (!film || !filmToggle) return;
  const shown = views[active] === film; filmToggle.hidden = !shown;
  if (shown && filmInView && !document.hidden && (filmChoice === true || (filmChoice !== false && !reduced && !saveData))) { const play = film.play(); play?.then(filmState).catch(filmState); }
  else { film.pause(); filmState(); }
 }
 function gallery(index) {
  if (!views.length) return;
  active = (index + views.length) % views.length;
  views.forEach((view, i) => { view.hidden = i !== active; view.classList.toggle('is-active', i === active); });
  thumbs.forEach((button, i) => button.setAttribute('aria-pressed', String(i === active)));
  const strip = $('.product-thumbs'), selected = thumbs[active];
  if (strip && selected) strip.scrollTo({ left: selected.offsetLeft - (strip.clientWidth - selected.offsetWidth) / 2, behavior: reduced ? 'instant' : 'smooth' });
  if ($('.gallery-label')) $('.gallery-label').textContent = `${active + 1} / ${views.length} · ${views[active].dataset.label || 'Vue du produit'}`;
  if ($('#gallery-expand')) $('#gallery-expand').hidden = views[active].tagName !== 'IMG';
  syncFilm();
  if (!reduced && window.gsap) gsap.fromTo(views[active], { opacity: .3, x: 12 }, { opacity: 1, x: 0, duration: .4, overwrite: true });
 }
 if (film && filmToggle) { film.controls = false; on(film, 'play', filmState); on(film, 'pause', filmState); on(filmToggle, 'click', () => { filmChoice = film.paused; syncFilm(); }); if (stage && 'IntersectionObserver' in window) new IntersectionObserver(entries => { filmInView = entries[0].isIntersecting; syncFilm(); }, { threshold: .2 }).observe(stage); }
 on(document, 'visibilitychange', syncFilm);
 thumbs.forEach(button => on(button, 'click', () => gallery(+button.dataset.gallery)));
 on($('[data-gallery-prev]'), 'click', () => gallery(active - 1)); on($('[data-gallery-next]'), 'click', () => gallery(active + 1));
 on(stage, 'keydown', event => { const action = { ArrowLeft: active - 1, ArrowRight: active + 1, Home: 0, End: views.length - 1 }; if (event.key in action) { event.preventDefault(); gallery(action[event.key]); } });
 let pointerStart;
 on(stage, 'pointerdown', event => { pointerStart = { x: event.clientX, y: event.clientY }; });
 on(stage, 'pointerup', event => { if (!pointerStart) return; const dx = event.clientX - pointerStart.x, dy = event.clientY - pointerStart.y; if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) gallery(active + (dx < 0 ? 1 : -1)); pointerStart = null; });
 on(stage, 'pointercancel', () => { pointerStart = null; });
 gallery(0);
 const cents = value => Math.max(0, Math.round(Number(value) || 0));
 const money = value => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: shop.currency || 'EUR' }).format(value / 100);
 function pricing(count) {
  const pairs = shop.duo_enabled ? Math.floor(count / 2) : 0, singles = count - pairs * 2;
  const single = cents(shop.single_cents), duo = cents(shop.duo_cents);
  return { current: pairs * duo + singles * single, previous: pairs * Math.max(duo, cents(shop.duo_regular_cents)) + singles * Math.max(single, cents(shop.regular_cents)) };
 }
 function setQty(value) {
  const input = $('#product-quantity'); if (!input) return;
  const maximum = Number(shop.max_quantity) || Number(input.max) || 10;
  quantity = Math.max(1, Math.min(maximum, Math.floor(Number(value) || 1))); input.value = quantity;
  if ($('[data-qty="minus"]')) $('[data-qty="minus"]').disabled = quantity === 1;
  if ($('[data-qty="plus"]')) $('[data-qty="plus"]').disabled = quantity === maximum;
  $$('[name="product-offer"]').forEach(option => { option.checked = +option.value === quantity; });
  if (!Object.hasOwn(shop, 'single_cents')) return;
  const price = pricing(quantity);
  $('#product-price-label').textContent = quantity + ' produit' + (quantity > 1 ? 's' : ''); $('#product-price').textContent = money(price.current);
  $('#product-compare-price').textContent = money(price.previous); $('#product-compare-price').hidden = price.previous <= price.current;
  if ($('#product-compare-label')) $('#product-compare-label').hidden = price.previous <= price.current;
 }
 $$('[name="product-offer"]').forEach(option => on(option, 'change', () => setQty(option.value)));
 $$('[data-qty]').forEach(button => on(button, 'click', () => setQty(quantity + (button.dataset.qty === 'plus' ? 1 : -1))));
 on($('#product-quantity'), 'change', event => setQty(event.target.value)); setQty(1);
 function showDialog(dialog) { if (!dialog) return; dialog.showModal(); document.body.style.overflow = 'hidden'; }
 $$('dialog').forEach(dialog => { on(dialog, 'close', () => { document.body.style.overflow = ''; }); on(dialog, 'click', event => { if (event.target !== dialog) return; const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close(); }); $$('[data-close]', dialog).forEach(button => on(button, 'click', () => dialog.close())); });
 const imageDialog = $('#gallery-dialog'), zoomImage = $('#gallery-dialog-image'), zoomToggle = $('#gallery-zoom-toggle');
 on($('#gallery-expand'), 'click', () => {
  const view = views[active]; if (view?.tagName !== 'IMG' || !zoomImage || !zoomToggle) return;
  zoomImage.src = view.currentSrc || view.src; zoomImage.alt = view.alt; $('#gallery-dialog-caption').textContent = view.dataset.label || '';
  zoomToggle.classList.remove('is-zoomed'); zoomToggle.setAttribute('aria-label', 'Zoomer sur l’image'); showDialog(imageDialog); $('.gallery-zoom-scroll')?.scrollTo(0, 0);
 });
 on(zoomToggle, 'click', () => { const zoomed = zoomToggle.classList.toggle('is-zoomed'); zoomToggle.setAttribute('aria-label', zoomed ? 'Réduire l’image' : 'Zoomer sur l’image'); });
 $$('[data-info]').forEach(button => on(button, 'click', () => { const content = $$('template[data-info-content]').find(t => t.dataset.infoContent === button.dataset.info); if (!content || !$('#info-dialog')) return; $('#info-title').textContent = content.dataset.title; $('#info-content').replaceChildren(content.content.cloneNode(true)); showDialog($('#info-dialog')); }));
 const purchaseForm = $('#belmains-product-form'), purchaseButton = $('#add-to-cart'), feedback = $('#purchase-feedback'); let adding = false;
 on(purchaseForm, 'submit', async event => {
  if (shop.preview || !shop.available) { event.preventDefault(); return; }
  if (!shop.ajax_url || !purchaseButton || !feedback) return;
  event.preventDefault(); if (adding) return; setQty($('#product-quantity').value); adding = true; purchaseButton.disabled = true; purchaseButton.setAttribute('aria-busy', 'true'); feedback.hidden = false; feedback.textContent = 'Ajout au panier…';
  try {
   const payload = new URLSearchParams({ nonce: shop.nonce, product_id: String(shop.product_id), quantity: String(quantity) });
   const response = await fetch(shop.ajax_url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: payload });
   const result = await response.json();
   if (!response.ok || !result.success) { const plain = document.createElement('div'); plain.innerHTML = result.data?.notices_html || ''; throw new Error(plain.textContent.trim() || result.data?.message || 'Impossible d’ajouter le produit. Actualisez la page et réessayez.'); }
   document.body.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart', { bubbles: true, detail: { preserveCartData: false } })); feedback.textContent = 'Produit ajouté. Ouverture de votre panier…'; location.assign(shop.cart_url);
  } catch (error) { feedback.textContent = error.message || 'La connexion a été interrompue. Vérifiez votre panier avant de réessayer.'; feedback.focus(); adding = false; purchaseButton.disabled = false; purchaseButton.removeAttribute('aria-busy'); }
 });
 $$('.faq-item').forEach((item, index) => {
  const button = $('.faq-trigger', item), body = $('.faq-body', item); if (!button || !body) return;
  body.id = 'faq-answer-' + index; button.setAttribute('aria-controls', body.id); body.inert = !item.classList.contains('open');
  function size() { body.style.maxHeight = item.classList.contains('open') ? body.scrollHeight + 'px' : '0px'; }
  size(); on(button, 'click', () => { const open = !item.classList.contains('open'); item.classList.toggle('open', open); button.setAttribute('aria-expanded', String(open)); body.inert = !open; size(); });
  if ('ResizeObserver' in window && $('p', body)) new ResizeObserver(size).observe($('p', body));
 });
 const track = $('[data-kd-track]'), cards = $$('article', track), dots = $$('[data-kd-dot]');
 function cardWidth() { return (cards[0]?.getBoundingClientRect().width || 0) + 24; }
 function testimonial(index) { track?.scrollTo({ left: Math.max(0, index) * cardWidth(), behavior: reduced ? 'instant' : 'smooth' }); }
 on($('[data-kd-prev]'), 'click', () => testimonial(Math.round(track.scrollLeft / cardWidth()) - 1)); on($('[data-kd-next]'), 'click', () => testimonial(Math.round(track.scrollLeft / cardWidth()) + 1));
 dots.forEach((button, index) => on(button, 'click', () => testimonial(index)));
 on(track, 'scroll', () => { const index = Math.round(track.scrollLeft / cardWidth()); dots.forEach((button, i) => { button.classList.toggle('is-active', index === i); button.setAttribute('aria-pressed', String(index === i)); }); }, { passive: true });
 const diapo = $('[data-diapo]'), photos = $$('.diapo__slide', diapo), photoDots = $$('[data-goto]', diapo), pause = $('[data-pause]', diapo);
 let photoIndex = 0, paused = false, visible = false, hover = false, focused = false, timer;
 function photoGo(index) { if (!photos.length) return; photoIndex = (index + photos.length) % photos.length; photos.forEach((slide, i) => { const selected = i === photoIndex; slide.classList.toggle('is-active', selected); slide.setAttribute('aria-hidden', String(!selected)); slide.inert = !selected; }); photoDots.forEach((button, i) => { button.classList.toggle('is-active', i === photoIndex); button.setAttribute('aria-selected', String(i === photoIndex)); button.tabIndex = i === photoIndex ? 0 : -1; }); }
 function autoplay() {
  clearInterval(timer); if (!diapo || !pause || photos.length < 2) return;
  diapo.dataset.paused = String(paused || reduced); pause.disabled = reduced; pause.setAttribute('aria-label', paused || reduced ? 'Lire le diaporama' : 'Mettre le diaporama en pause');
  if (!paused && !reduced && visible && !hover && !focused && !document.hidden) timer = setInterval(() => photoGo(photoIndex + 1), 6000);
 }
 on($('[data-prev]', diapo), 'click', () => { photoGo(photoIndex - 1); autoplay(); }); on($('[data-next]', diapo), 'click', () => { photoGo(photoIndex + 1); autoplay(); });
 photoDots.forEach((button, index) => { on(button, 'click', () => { photoGo(index); autoplay(); }); on(button, 'keydown', event => { if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') { event.preventDefault(); photoGo(photoIndex + (event.key === 'ArrowRight' ? 1 : -1)); photoDots[photoIndex]?.focus(); autoplay(); } }); });
 on(pause, 'click', () => { paused = reduced || !paused; autoplay(); }); on(diapo, 'mouseenter', () => { hover = true; autoplay(); }); on(diapo, 'mouseleave', () => { hover = false; autoplay(); }); on(diapo, 'focusin', () => { focused = true; autoplay(); }); on(diapo, 'focusout', event => { focused = diapo.contains(event.relatedTarget); autoplay(); });
 if (diapo && 'IntersectionObserver' in window) new IntersectionObserver(entries => { visible = entries[0].isIntersecting; autoplay(); }, { threshold: .2 }).observe(diapo);
 on(document, 'visibilitychange', autoplay); photoGo(0);
 function animations() {
  motionContext?.revert(); if (reduced) filmChoice = null; syncFilm(); document.documentElement.classList.toggle('motion-paused', reduced);
  const toggle = $('#motion-toggle'); if (toggle) { toggle.textContent = reduced ? 'Activer les animations' : 'Mettre les animations en pause'; toggle.setAttribute('aria-pressed', String(reduced)); } autoplay();
  if (reduced || !window.gsap || !window.ScrollTrigger) return;
  gsap.registerPlugin(ScrollTrigger); motionContext = gsap.context(() => {
   if (scrollY < 50 && $('.hero-ed-left')) { gsap.from('.hero-ed-left > *', { opacity: 0, y: 20, duration: .85, stagger: .1, ease: 'power2.out' }); if ($('.hero-ed-right')) gsap.from('.hero-ed-right', { opacity: 0, y: 24, duration: 1, delay: .15 }); }
   if ($('.hero-image img')) gsap.fromTo('.hero-image img', { scale: 1.045 }, { scale: 1, yPercent: 1.5, ease: 'none', scrollTrigger: { trigger: '.hero-editorial', start: 'top top', end: 'bottom top', scrub: 1.3 } });
   $$('.iwt-image-tag').forEach(el => gsap.fromTo(el, { scale: 1.06 }, { scale: 1, ease: 'none', scrollTrigger: { trigger: el.closest('.iwt-image-wrap') || el, start: 'top bottom', end: 'bottom top', scrub: 1.2 } }));
   $$('.product-gallery,.promesses-title,.faq-section .section-title,.iwt-image,.iwt-content,.promesse-card,.rich-text__heading').forEach(el => gsap.from(el, { opacity: 0, y: 24, duration: .8, ease: 'power2.out', scrollTrigger: { trigger: el, start: 'top 94%', once: true } }));
  }); document.fonts?.ready.then(() => ScrollTrigger.refresh());
 }
 on($('#motion-toggle'), 'click', () => { reduced = !reduced; animations(); }); on(motionMedia, 'change', () => { reduced = motionMedia.matches; animations(); }); animations();
})();
