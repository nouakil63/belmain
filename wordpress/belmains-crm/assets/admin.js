(function () {
  'use strict';

  const app = document.getElementById('bcrm-app');
  const boot = window.BelmainsCRM;
  if (!app || !boot || !boot.api) return;

  const esc = (value) => String(value == null ? '' : value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const number = (value) => value == null ? '—' : new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(Number(value));
  const money = (value, currency) => value == null ? '—' : new Intl.NumberFormat('fr-FR', { style: 'currency', currency: currency || boot.currency || 'EUR' }).format(Number(value));
  const localDate = (date) => {
    const d = new Date(date);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  };
  const dateText = (value, withTime) => {
    if (!value) return '—';
    const raw = String(value);
    const date = new Date(raw.length === 10 ? `${raw}T12:00:00` : raw.replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? raw : new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', ...(withTime ? { timeStyle: 'short' } : {}) }).format(date);
  };
  const safeUrl = (value) => {
    if (!value) return '#';
    try { const url = new URL(value, location.origin); return /^(https?:)$/.test(url.protocol) ? url.href : '#'; } catch (_) { return '#'; }
  };
  const icon = (name) => {
    const paths = {
      overview: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
      orders: '<path d="M7 3h10v3h3v15H4V6h3z"/><path d="M8 11h8M8 16h5"/>',
      customers: '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 5"/>',
      shipments: '<path d="M3 5h11v12H3zM14 9h4l3 4v4h-7"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
      stock: '<path d="m12 3 9 5v9l-9 5-9-5V8zM3 8l9 5 9-5M12 13v9M7 5.8l9 5"/>',
      marketing: '<path d="m3 10 15-6v16L3 14zM7 16l2 5h3M21 9v6"/>',
      tickets: '<path d="M4 14v-3a8 8 0 0 1 16 0v3M5 12H3v6h4v-6zM19 12h2v6h-4v-6zM17 19v2h-5"/>',
      integration: '<path d="M8 3v5M16 3v5M5 8h14v4a7 7 0 0 1-14 0zM12 19v3"/>',
      arrow: '<path d="M5 12h14m-5-5 5 5-5 5"/>',
      refresh: '<path d="M20 6v5h-5M4 18v-5h5M5.8 7a7 7 0 0 1 11.5-2L20 8M4 16l2.7 3A7 7 0 0 0 18.2 17"/>',
      download: '<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>',
      close: '<path d="m6 6 12 12M18 6 6 18"/>',
      search: '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>'
    };
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || paths.overview}</svg>`;
  };
  const routes = {
    overview: { label: 'Vue d’ensemble', eyebrow: 'PILOTAGE', description: 'Les chiffres utiles, les actions à venir.' },
    orders: { label: 'Commandes', eyebrow: 'COMMERCE', description: 'Chaque commande, du paiement au suivi.' },
    customers: { label: 'Clients', eyebrow: 'RELATION CLIENT', description: 'Vos clients et leurs achats sur la période.' },
    shipments: { label: 'Expéditions', eyebrow: 'LOGISTIQUE', description: 'Gardez un œil sur chaque étape de livraison.' },
    stock: { label: 'Stocks', eyebrow: 'CATALOGUE', description: 'Les disponibilités de votre catalogue WooCommerce.' },
    marketing: { label: 'Marketing', eyebrow: 'ACQUISITION', description: 'Vos dépenses et les ventes attribuées aux campagnes.' },
    tickets: { label: 'Service client', eyebrow: 'SAV', description: 'Centralisez les demandes et leur résolution.' },
    integration: { label: 'Connexions', eyebrow: 'RÉGLAGES', description: 'Vos outils, vos données et vos préférences.' }
  };
  const shipmentLabels = { pending: 'À expédier', shipped: 'Expédié', in_transit: 'En transit', relay: 'En point relais', delivered: 'Livré', exception: 'Incident', returned: 'Retourné' };
  const ticketLabels = { open: 'Ouvert', pending: 'En attente', closed: 'Résolu' };
  const priorityLabels = { low: 'Basse', normal: 'Normale', high: 'Haute', urgent: 'Urgente' };
  const orderLabels = { pending: 'En attente de paiement', processing: 'En cours', 'on-hold': 'En attente', completed: 'Terminée', cancelled: 'Annulée', refunded: 'Remboursée', failed: 'Échouée' };
  const orderLabel = (key, label) => orderLabels[String(key || '').replace(/^wc-/, '')] || label || key;
  const today = new Date();
  const first = new Date(today); first.setDate(first.getDate() - 29);
  const state = { route: 'overview', from: localDate(first), to: localDate(today), page: 1, search: '', status: '', loading: false, data: null, request: 0, dialog: null, busy: false, opener: null };
  let noticeTimer;

  async function api(endpoint, params, body, method) {
    const url = new URL(String(boot.api).replace(/\/?$/, '/') + endpoint, location.origin);
    if (url.origin !== location.origin) throw new Error('La connexion doit utiliser le même domaine que WordPress.');
    Object.entries(params || {}).forEach(([key, value]) => { if (value !== '' && value != null) url.searchParams.set(key, value); });
    const response = await fetch(url.href, { method: method || (body === undefined ? 'GET' : 'POST'), credentials: 'same-origin', headers: { 'X-WP-Nonce': boot.nonce, ...(body === undefined ? {} : { 'Content-Type': 'application/json' }) }, ...(body === undefined ? {} : { body: JSON.stringify(body) }) });
    let result;
    try { result = await response.json(); } catch (_) { throw new Error('Réponse WordPress illisible. Rechargez la page et réessayez.'); }
    if (!response.ok) throw new Error(result.message || 'La demande n’a pas pu aboutir.');
    return result;
  }

  function notice(message, error) {
    const node = app.querySelector('[data-notice]');
    clearTimeout(noticeTimer);
    node.className = `bcrm-notice ${error ? 'is-error' : 'is-success'}`;
    node.textContent = message;
    node.hidden = false;
    if (!error) noticeTimer = setTimeout(() => { node.hidden = true; }, 7000);
  }
  const button = (label, action, extra, primary) => `<button type="button" class="bcrm-button ${primary ? 'is-primary' : ''}" data-action="${esc(action)}" ${extra || ''}>${label}</button>`;
  const empty = (title, message) => `<div class="bcrm-empty"><span class="bcrm-empty-icon">${icon(state.route)}</span><h3>${esc(title)}</h3><p>${esc(message)}</p></div>`;
  const warnings = (items) => {
    const alerts = [], methodology = [];
    const methodPrefix = /^(Ventes hors taxes|La marge contributive|Clients identifiés|Attribution uniquement|Commandes regroupées|Configuration Iziship|Les mises à jour comptées|Le numéro de suivi|Cette version présente)/;
    (items || []).forEach((item) => { (methodPrefix.test(String(item)) ? methodology : alerts).push(item); });
    const list = (values) => `<ul class="bcrm-warning-list">${values.map((item) => `<li>${esc(item)}</li>`).join('')}</ul>`;
    return `${alerts.length ? `<div class="bcrm-callout" role="note">${list(alerts)}</div>` : ''}${methodology.length ? `<details class="bcrm-methodology"><summary>Comprendre les indicateurs</summary>${list(methodology)}</details>` : ''}`;
  };
  const badge = (key, label) => `<span class="bcrm-badge is-${esc(String(key || 'pending').replace(/[^a-z_-]/g, ''))}">${esc(label || key || 'À renseigner')}</span>`;
  const metric = (label, value, hint, accent) => `<article class="bcrm-stat ${accent ? 'is-accent' : ''}"><p class="bcrm-stat-label">${esc(label)}</p><strong>${esc(value)}</strong><p class="bcrm-stat-hint">${esc(hint || '')}</p></article>`;
  const table = (heads, rows) => `<div class="bcrm-table-scroll" tabindex="0" role="region" aria-label="Tableau défilant"><table class="bcrm-table"><thead><tr>${heads.map((h) => `<th scope="col">${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.join('')}</tbody></table></div>`;
  const options = (list, chosen, all) => `${all ? '<option value="">Tous les statuts</option>' : ''}${Object.entries(list).map(([key, value]) => `<option value="${esc(key)}" ${key === chosen ? 'selected' : ''}>${esc(value)}</option>`).join('')}`;
  const field = (label, name, value, type, attrs) => `<label class="bcrm-field"><span>${esc(label)}</span><input type="${type || 'text'}" name="${esc(name)}" value="${esc(value == null ? '' : value)}" ${attrs || ''}></label>`;
  const select = (label, name, values, selected) => `<label class="bcrm-field"><span>${esc(label)}</span><select name="${esc(name)}">${options(values, selected)}</select></label>`;
  const area = (label, name, value, attrs) => `<label class="bcrm-field"><span>${esc(label)}</span><textarea name="${esc(name)}" rows="3" ${attrs || ''}>${esc(value || '')}</textarea></label>`;
  const saveButton = (label) => `<button type="submit" class="bcrm-button is-primary">${esc(label || 'Enregistrer')}</button>`;

  function renderShell() {
    app.innerHTML = `<div class="bcrm-layout">
      <aside class="bcrm-sidebar"><div class="bcrm-brand"><span class="bcrm-brand-name">BELMAINS</span><span class="bcrm-brand-sub">Votre espace de pilotage</span></div>
        <nav aria-label="Navigation du CRM">${Object.entries(routes).map(([key, route]) => `<button type="button" data-action="route" data-route="${key}" class="bcrm-nav-item">${icon(key)}<span>${route.label}</span></button>`).join('')}</nav>
        <div class="bcrm-sidebar-foot"><span class="bcrm-local-dot"></span><div><strong>${esc(boot.siteName || 'Belmains')}</strong><span>Données dans votre WordPress</span></div></div>
      </aside>
      <main class="bcrm-main"><header class="bcrm-header"><div><p class="bcrm-eyebrow" data-eyebrow></p><h1 data-title></h1><p class="bcrm-lead" data-description></p></div>${button(`${icon('refresh')}<span>Actualiser</span>`, 'refresh', 'aria-label="Actualiser les données"')}</header>
        <form class="bcrm-period" data-form="period"><label class="bcrm-preset"><span>Période</span><select name="preset" aria-label="Période rapide"><option value="30">30 derniers jours</option><option value="7">7 derniers jours</option><option value="today">Aujourd’hui</option><option value="month">Ce mois-ci</option><option value="90">90 derniers jours</option><option value="custom">Personnalisée</option></select></label><label><span>Du</span><input type="date" name="from" value="${state.from}" required></label><label><span>Au</span><input type="date" name="to" value="${state.to}" required></label><button class="bcrm-button" type="submit">Appliquer</button><span class="bcrm-period-note">Dates de la boutique · 366 jours maximum</span></form>
        <div data-notice class="bcrm-notice" role="status" aria-live="polite" hidden></div>
        <div data-content class="bcrm-content" aria-live="polite" aria-busy="false"></div>
        <footer class="bcrm-footer"><span>Belmains · Pilotage de la boutique</span><span data-updated>Données issues de WordPress et WooCommerce</span></footer>
      </main></div>`;
  }

  function updateHeader() {
    const route = routes[state.route];
    app.querySelector('[data-eyebrow]').textContent = route.eyebrow;
    app.querySelector('[data-title]').textContent = route.label;
    app.querySelector('[data-description]').textContent = route.description;
    app.querySelectorAll('[data-route]').forEach((node) => { const active = node.dataset.route === state.route; node.classList.toggle('is-active', active); if (active) node.setAttribute('aria-current', 'page'); else node.removeAttribute('aria-current'); });
    app.querySelector('.bcrm-period').hidden = ['stock', 'tickets', 'integration'].includes(state.route);
  }

  async function load() {
    updateHeader();
    const request = ++state.request;
    const content = app.querySelector('[data-content]');
    state.loading = true;
    content.setAttribute('aria-busy', 'true');
    content.innerHTML = '<div class="bcrm-loading" role="status"><span class="bcrm-spinner"></span>Chargement des données…</div>';
    try {
      const params = { from: state.from, to: state.to, page: state.page, search: state.search, status: state.status };
      let data;
      if (state.route === 'integration') { const result = await Promise.all([api('integration'), api('settings')]); data = { integration: result[0], settings: result[1] }; }
      else if (state.route === 'stock' || state.route === 'shipments') { const result = await Promise.all([api(state.route, state.route === 'stock' ? { page: state.page, search: state.search } : params), api('settings')]); data = { ...result[0], settings: result[1] }; }
      else data = await api(state.route === 'overview' ? 'dashboard' : state.route, state.route === 'tickets' ? { status: state.status } : state.route === 'stock' ? { page: state.page, search: state.search } : params);
      if (request !== state.request) return;
      state.data = data;
      renderContent();
      app.querySelector('[data-updated]').textContent = `Actualisé à ${new Intl.DateTimeFormat('fr-FR', { timeStyle: 'short' }).format(new Date())}`;
    } catch (error) {
      if (request !== state.request) return;
      content.innerHTML = `<div class="bcrm-empty is-error" role="alert"><h3>Les données ne sont pas disponibles</h3><p>${esc(error.message)}</p>${button('Réessayer', 'refresh', '', true)}</div>`;
    } finally { if (request === state.request) { state.loading = false; content.setAttribute('aria-busy', 'false'); } }
  }

  function filterBar(statuses, exportType) {
    return `<form class="bcrm-filterbar" data-form="filter"><label class="bcrm-search">${icon('search')}<span class="bcrm-sr-only">Rechercher</span><input type="search" name="search" value="${esc(state.search)}" placeholder="${state.route === 'stock' ? 'Produit ou référence…' : state.route === 'customers' ? 'Nom ou e-mail…' : 'Commande, nom ou e-mail…'}"></label>${statuses ? `<select name="status" aria-label="Filtrer par statut">${options(statuses, state.status, true)}</select>` : ''}<button class="bcrm-button" type="submit">Filtrer</button>${state.search || state.status ? button('Effacer', 'clear-filters') : ''}${exportType ? button(`${icon('download')}<span>Exporter la période</span>`, 'export', `data-type="${exportType}" title="Toutes les données de la période, sans filtre de recherche ou de statut"`) : ''}</form>`;
  }
  function pager(data) {
    const pages = Math.max(1, Number(data.pages) || 1);
    return `<div class="bcrm-pagination"><span>${esc(number(data.total || 0))} résultat${Number(data.total) !== 1 ? 's' : ''}</span><div>${button('Précédent', 'page', `data-page="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''}`)}<span>Page ${esc(number(state.page))} sur ${esc(number(pages))}</span>${button('Suivant', 'page', `data-page="${state.page + 1}" ${state.page >= pages ? 'disabled' : ''}`)}</div></div>`;
  }

  function chart(daily, valueKey, isMoney) {
    if (!daily || !daily.length) return empty('Pas encore de données', 'La courbe apparaîtra lorsque des données seront disponibles sur la période.');
    const width = 700, height = 190, left = 12, top = 12, bottom = 168;
    const values = daily.map((item) => Number(item[valueKey]) || 0);
    const min = Math.min(...values, 0), max = Math.max(...values, 0), span = max - min || 1;
    const y = (value) => bottom - (bottom - top) * (value - min) / span;
    const points = values.map((value, index) => `${left + (width - left * 2) * index / Math.max(values.length - 1, 1)},${y(value)}`);
    const title = isMoney ? 'Chiffre d’affaires quotidien' : 'Visiteurs quotidiens';
    return `<div class="bcrm-chart-top"><span>Maximum quotidien : <strong>${esc(isMoney ? money(Math.max(...values)) : number(Math.max(...values)))}</strong></span>${min < 0 ? `<span>Minimum : ${esc(isMoney ? money(min) : number(min))}</span>` : ''}</div><svg class="bcrm-chart" viewBox="0 0 ${width} ${height}" role="img" aria-label="${esc(title)}. Détail des valeurs dans le tableau ci-dessous."><path d="M12 12H688M12 64H688M12 116H688M12 168H688" class="bcrm-chart-grid"/><polygon points="${left},${y(0)} ${points.join(' ')} ${left + (width - left * 2) * (values.length - 1) / Math.max(values.length - 1, 1)},${y(0)}" class="bcrm-chart-fill"/><polyline points="${points.join(' ')}" class="bcrm-chart-line"/>${values.length === 1 ? `<circle cx="12" cy="${y(values[0])}" r="4" fill="currentColor"/>` : ''}</svg><div class="bcrm-chart-dates"><span>${esc(dateText(daily[0].date))}</span><span>${esc(dateText(daily[daily.length - 1].date))}</span></div><details class="bcrm-chart-details"><summary>Voir les valeurs par jour</summary>${table(['Date', isMoney ? 'CA net' : 'Visiteurs'], daily.map((row) => `<tr><td>${esc(dateText(row.date))}</td><td>${esc(isMoney ? money(row[valueKey]) : number(row[valueKey]))}</td></tr>`))}</details>`;
  }
  function barList(items, key, value) {
    if (!items || !items.length) return '<p class="bcrm-muted">Aucune donnée sur cette période.</p>';
    const max = Math.max(1, ...items.map((item) => Number(item[value]) || 0));
    const label = (row) => {
      const raw = row[key];
      if (key === 'device') return ({ desktop: 'Ordinateur', mobile: 'Mobile', tablet: 'Tablette', unknown: 'Non identifié', other: 'Autre' })[String(raw).toLowerCase()] || raw;
      if (key === 'source') return ({ direct: 'Accès direct', '(direct)': 'Accès direct', unknown: 'Non identifiée', organic: 'Recherche naturelle', referral: 'Site référent', email: 'E-mail' })[String(raw).toLowerCase()] || raw;
      return raw;
    };
    return `<ul class="bcrm-bars">${items.map((item) => `<li><div><span>${esc(label(item) || 'Non renseigné')}</span><strong>${esc(number(item[value]))}</strong></div><span class="bcrm-bar"><span style="width:${Math.max(0, Math.min(100, (Number(item[value]) || 0) / max * 100))}%"></span></span></li>`).join('')}</ul>`;
  }

  function overview(data) {
    const commerce = data.commerce || {}, metrics = commerce.metrics || {}, audience = data.audience || {}, connections = data.connections || {};
    const enabled = audience.enabled === true;
    const live = commerce.available === true;
    return `${!live ? `<div class="bcrm-callout"><strong>WooCommerce doit être activé pour suivre les ventes.</strong><p>Les commandes, les clients et les stocks apparaîtront automatiquement ici après activation.</p>${boot.wooUrl ? `<a class="bcrm-button" href="${esc(safeUrl(boot.wooUrl))}">Ouvrir WooCommerce</a>` : ''}</div>` : ''}${warnings(commerce.warnings)}
      <div class="bcrm-stat-grid">${metric('Chiffre d’affaires net', live ? money(metrics.net_revenue, commerce.currency) : '—', 'Hors taxes · remboursements déduits', true)}${metric('Commandes payées', live ? number(metrics.orders_paid) : '—', live ? `${number(metrics.orders_total)} commandes au total` : 'WooCommerce requis')}${metric('Panier moyen', live ? money(metrics.average_order, commerce.currency) : '—', 'Commandes payées de la période')}${metric('Marge contributive', live ? money(metrics.profit, commerce.currency) : '—', metrics.profit == null ? 'Coûts à compléter dans les commandes' : 'Après coûts saisis · hors marketing')}${metric('Visiteurs mesurés', enabled ? number(audience.visitors) : '—', enabled ? 'Visiteurs ayant accepté la mesure' : 'Mesure désactivée')}${metric('Visiteurs devenus acheteurs (mesurés)', enabled && audience.conversion != null ? `${number(audience.conversion)} %` : '—', 'Avec une page vue et un achat payé suivi')}${metric('Clients', live ? number(metrics.customers) : '—', `${live ? number(metrics.returning_customers) : '—'} ayant acheté plusieurs fois dans la période`)}${metric('Articles vendus', live ? number(metrics.units_sold) : '—', `Remboursements : ${live ? money(metrics.refund_total, commerce.currency) : '—'}`)}</div>
      <p class="bcrm-data-note">Ventes regroupées par date de création des commandes, du ${esc(dateText(data.from || state.from))} au ${esc(dateText(data.to || state.to))}. La marge exige les quatre coûts de chaque commande. Couverture des coûts : ${esc(number(metrics.profit_coverage))} %.</p>
      <div class="bcrm-grid bcrm-grid-main"><section class="bcrm-panel"><div class="bcrm-panel-heading"><div><p class="bcrm-eyebrow">ÉVOLUTION</p><h2>Votre activité au fil des jours</h2></div><span class="bcrm-tag">CA net</span></div>${chart(commerce.daily, 'revenue', true)}</section><section class="bcrm-panel"><div class="bcrm-panel-heading"><div><p class="bcrm-eyebrow">À SUIVRE</p><h2>Les commandes</h2></div></div>${(commerce.statuses || []).length ? `<ul class="bcrm-status-list">${commerce.statuses.map((row) => `<li><span>${esc(orderLabel(row.key, row.label))}</span><strong>${esc(number(row.count))}</strong></li>`).join('')}</ul>` : '<p class="bcrm-muted">Aucune commande sur cette période.</p>'}${button(`Voir les commandes ${icon('arrow')}`, 'route', 'data-route="orders"')}</section></div>
      <div class="bcrm-section-heading"><div><p class="bcrm-eyebrow">AUDIENCE</p><h2>Comprendre le parcours d’achat</h2></div>${button('Régler la mesure', 'route', 'data-route="integration"')}</div>
      ${enabled ? `<div class="bcrm-funnel">${[['Sessions', audience.sessions], ['Vues produit', audience.product_views], ['Ajouts au panier', audience.add_to_cart], ['Passages au paiement', audience.checkout], ['Achats suivis', audience.tracked_orders]].map(([label, value], i) => `<div><span class="bcrm-step">0${i + 1}</span><strong>${esc(number(value))}</strong><span>${esc(label)}</span></div>`).join('')}</div>` : '<div class="bcrm-callout"><p>La mesure d’audience est désactivée. Activez-la dans Connexions pour compter les visites après consentement.</p></div>'}
      <p class="bcrm-data-note">${esc(audience.coverage_note || 'La mesure repose sur le consentement. Les visites de l’administration sont exclues.')} Le taux de conversion rapporte les visiteurs avec une page vue et un achat payé suivi aux visiteurs ayant vu une page dans la période. Les étapes comptent les événements, pas nécessairement les mêmes personnes.</p>
      <div class="bcrm-grid bcrm-grid-three"><section class="bcrm-panel"><h2>Origine des visiteurs</h2>${barList(enabled ? audience.sources : [], 'source', 'visitors')}</section><section class="bcrm-panel"><h2>Appareils</h2>${barList(enabled ? audience.devices : [], 'device', 'visitors')}<p class="bcrm-data-note">${enabled ? esc(number(audience.pageviews)) : '—'} pages consultées sur la période.</p></section><section class="bcrm-panel"><h2>Produits vendus</h2>${(commerce.products || []).length ? `<ul class="bcrm-product-list">${commerce.products.map((product) => `<li><span><strong>${esc(product.name)}</strong><small>${esc(number(product.quantity))} article${Number(product.quantity) > 1 ? 's' : ''}</small></span><b>${esc(money(product.revenue, commerce.currency))}</b></li>`).join('')}</ul>` : '<p class="bcrm-muted">Les produits vendus apparaîtront ici.</p>'}</section></div>
      <div class="bcrm-integration-strip"><span>${badge(connections.woocommerce ? 'delivered' : 'pending', connections.woocommerce ? 'WooCommerce actif' : 'WooCommerce à activer')}</span><span>${connections.iziship_last_sync ? `Dernière remontée de suivi : ${esc(dateText(connections.iziship_last_sync, true))}` : 'Iziship : aucune remontée externe observée'}</span>${button('Voir les connexions', 'route', 'data-route="integration"')}</div>`;
  }

  function ordersView(data, shipping) {
    const items = data.items || [];
    const shipmentMetrics = data.metrics || {};
    const sla = Number(data.settings && data.settings.shipping_sla_days) || 3;
    const overdue = (tracking) => tracking.shipped_at && ['shipped', 'in_transit', 'relay'].includes(tracking.status) && (Date.parse(`${localDate(new Date())}T12:00:00Z`) - Date.parse(`${String(tracking.shipped_at).slice(0, 10)}T12:00:00Z`)) / 86400000 > sla;
    const stats = shipping ? `<div class="bcrm-stat-grid is-small">${[['awaiting', 'À expédier'], ['shipped', 'Expédiés'], ['in_transit', 'En transit'], ['relay', 'En relais'], ['delivered', 'Livrés'], ['exception', 'Incidents'], ['returned', 'Retournés']].map(([key, label]) => metric(label, number(shipmentMetrics[key]), '')).join('')}</div><p class="bcrm-data-note">Le statut WooCommerce « Terminée » ne confirme pas une livraison. Les étapes de livraison proviennent du suivi enregistré. Le badge « Délai dépassé » indique plus de ${esc(sla)} jours calendaires après l’envoi sans livraison enregistrée ; ce repère interne ne confirme pas un incident.</p>` : '';
    return `${stats}<section class="bcrm-panel bcrm-panel-table">${filterBar(shipping ? shipmentLabels : orderLabels, shipping ? null : 'orders')}${warnings(data.warnings)}${items.length ? table(shipping ? ['Commande', 'Client', 'Transporteur / suivi', 'État du colis', 'Dates', 'Détail'] : ['Commande', 'Client', 'Statut', 'Articles', 'Montant', 'Détail'], items.map((item) => {
      const tracking = item.tracking || {};
      return `<tr><td><button class="bcrm-link" type="button" data-action="order" data-id="${esc(item.id)}">#${esc(item.number || item.id)}</button><small>${esc(dateText(item.date))}</small></td><td><strong>${esc(item.customer || 'Client invité')}</strong><small>${esc(item.email || '')}</small></td>${shipping ? `<td>${esc(tracking.carrier || 'Non renseigné')}<small>${tracking.url ? `<a href="${esc(safeUrl(tracking.url))}" target="_blank" rel="noopener noreferrer">${esc(tracking.number || 'Ouvrir le suivi')} ↗</a>` : esc(tracking.number || 'Aucun numéro')}</small></td><td>${badge(tracking.status, tracking.status_label || shipmentLabels[tracking.status] || 'À expédier')}${overdue(tracking) ? `<small>${badge('high', 'Délai dépassé')}</small>` : ''}</td><td><small>Envoi : ${esc(dateText(tracking.shipped_at))}</small><small>Livraison : ${esc(dateText(tracking.delivered_at))}</small></td>` : `<td>${badge(item.status, orderLabel(item.status, item.status_label))}</td><td>${esc(number(item.items_count))}</td><td class="bcrm-money">${esc(money(item.total, item.currency))}</td>`}<td>${button('Ouvrir', 'order', `data-id="${esc(item.id)}" aria-label="Ouvrir la commande ${esc(item.number || item.id)}"`)}</td></tr>`;
    })) : empty(shipping ? 'Aucune expédition à afficher' : 'Aucune commande à afficher', state.search || state.status ? 'Essayez un autre filtre ou élargissez la période.' : 'Les commandes de votre boutique apparaîtront ici.')}${pager(data)}</section>`;
  }
  function customersView(data) {
    return `<p class="bcrm-data-note">Achats et montants calculés sur la période sélectionnée, clients invités inclus. Cliquez sur un e-mail pour retrouver les commandes correspondantes.</p><section class="bcrm-panel bcrm-panel-table">${filterBar(null, 'customers')}${warnings(data.warnings)}${(data.items || []).length ? table(['Client', 'Commandes', 'Montant dépensé', 'Première / dernière commande', 'Segment'], data.items.map((customer) => `<tr><td><strong>${esc(customer.name || 'Client invité')}</strong><small><button class="bcrm-link" type="button" data-action="customer-orders" data-email="${esc(customer.email)}">${esc(customer.email || 'E-mail non renseigné')}</button></small>${customer.phone ? `<small>${esc(customer.phone)}</small>` : ''}</td><td>${esc(number(customer.orders))}</td><td class="bcrm-money">${esc(money(customer.spent))}</td><td><small>${esc(dateText(customer.first_order))}</small><small>${esc(dateText(customer.last_order))}</small></td><td>${badge('neutral', customer.segment)}</td></tr>`)) : empty('Aucun client sur cette période', 'Les profils se construisent automatiquement à partir des commandes WooCommerce.')}${pager(data)}</section>`;
  }
  function stockView(data) {
    const labels = { instock: 'En stock', outofstock: 'Rupture', onbackorder: 'Sur commande' };
    const threshold = Number(data.settings && data.settings.low_stock_threshold) || 5;
    return `<p class="bcrm-data-note">Stock actuel, indépendant de la période. Les modifications s’effectuent dans la fiche produit WooCommerce. Le repère « Stock bas » s’affiche à partir de ${esc(threshold)} articles restants ou moins.</p><section class="bcrm-panel bcrm-panel-table">${filterBar()}${warnings(data.warnings)}${(data.items || []).length ? table(['Produit', 'Référence', 'Disponible', 'État', 'Prix', 'Gestion'], data.items.map((product) => `<tr><td><strong>${esc(product.name)}</strong></td><td>${esc(product.sku || '—')}</td><td>${product.manage_stock ? esc(number(product.quantity)) : '<span class="bcrm-muted">Non suivi</span>'}${product.manage_stock && product.quantity != null && Number(product.quantity) > 0 && Number(product.quantity) <= threshold ? `<small>${badge('pending', 'Stock bas')}</small>` : ''}</td><td>${badge(product.status, labels[product.status] || product.status)}</td><td class="bcrm-money">${esc(money(product.price, product.currency))}</td><td><a class="bcrm-button" href="${esc(safeUrl(product.edit_url))}">Modifier<span class="bcrm-sr-only"> ${esc(product.name)}</span> ↗</a></td></tr>`)) : empty('Aucun produit trouvé', 'Ajoutez vos produits dans WooCommerce pour retrouver ici leur disponibilité.')}${pager(data)}</section>`;
  }
  function ticketsView(data) {
    const counts = data.counts || {};
    return `<div class="bcrm-stat-grid is-three">${metric('Demandes ouvertes', number(counts.open), 'À prendre en charge', true)}${metric('En attente', number(counts.pending), 'En cours de résolution')}${metric('Résolues', number(counts.closed), 'Dossiers clôturés')}</div><section class="bcrm-panel bcrm-panel-table"><div class="bcrm-filterbar"><label class="bcrm-inline-label">Statut <select name="ticket_status" aria-label="Filtrer les demandes">${options(ticketLabels, state.status, true)}</select></label>${button('+ Nouvelle demande', 'new-ticket', '', true)}</div><p class="bcrm-data-note bcrm-inset">Suivi interne uniquement. La création d’une demande ou l’ajout d’une note n’envoie aucun e-mail au client.</p>${(data.items || []).length ? table(['Demande', 'Client / commande', 'Priorité', 'Statut', 'Mise à jour', 'Détail'], data.items.map((ticket) => `<tr><td><strong>${esc(ticket.subject)}</strong><small>#${esc(ticket.id)}</small></td><td>${esc(ticket.customer_email || '—')}${ticket.order_id ? `<small><button type="button" class="bcrm-link" data-action="order" data-id="${esc(ticket.order_id)}">Commande #${esc(ticket.order_id)}</button></small>` : ''}</td><td>${badge(ticket.priority, priorityLabels[ticket.priority] || ticket.priority)}</td><td>${badge(ticket.status, ticketLabels[ticket.status] || ticket.status)}</td><td>${esc(dateText(ticket.updated_at, true))}</td><td>${button('Ouvrir', 'ticket', `data-id="${esc(ticket.id)}"`)}</td></tr>`)) : empty('Aucune demande à afficher', 'Créez une demande pour suivre une question client, un retour ou une difficulté de livraison.')}</section>`;
  }

  function marketingView(data) {
    const totals = data.totals || {};
    return `${warnings(data.warnings)}<div class="bcrm-stat-grid">${metric('Dépenses publicitaires', money(totals.spend), 'Coûts saisis manuellement · hors taxes', true)}${metric('CA attribué aux campagnes', money(totals.revenue), 'Commandes avec une source enregistrée')}${metric('Commandes attribuées', number(totals.orders), 'Sur la période sélectionnée')}${metric('ROAS', totals.roas == null ? '—' : `${number(totals.roas)} ×`, 'CA attribué / dépenses renseignées')}</div><p class="bcrm-data-note">L’attribution dépend des informations reçues avec les commandes. Les ventes sans attribution ne sont pas réparties artificiellement. Le ROAS ne constitue pas une mesure de bénéfice.</p><section class="bcrm-panel bcrm-panel-table"><div class="bcrm-panel-heading bcrm-table-title"><div><p class="bcrm-eyebrow">PERFORMANCE</p><h2>Sources et campagnes</h2></div></div>${(data.campaigns || []).length ? table(['Source', 'Campagne', 'Commandes', 'CA attribué', 'Dépenses HT', 'ROAS'], data.campaigns.map((row) => `<tr><td><strong>${esc(row.source || 'Non attribuée')}</strong></td><td>${esc(row.campaign || 'Sans campagne')}</td><td>${esc(number(row.orders))}</td><td class="bcrm-money">${esc(money(row.revenue))}</td><td class="bcrm-money">${esc(money(row.spend))}</td><td>${row.roas == null ? '—' : `${esc(number(row.roas))} ×`}</td></tr>`)) : empty('Aucune campagne sur cette période', 'Les sources attribuées aux commandes et les dépenses saisies apparaîtront ici.')}</section><section class="bcrm-panel bcrm-panel-table"><div class="bcrm-filterbar"><div><h2 class="bcrm-no-margin">Dépenses renseignées</h2></div>${button('+ Ajouter une dépense', 'new-cost', '', true)}</div><p class="bcrm-data-note bcrm-inset">Saisissez les montants facturés par vos plateformes publicitaires. Pour corriger une ligne, supprimez-la puis ajoutez le montant corrigé.</p>${(data.items || []).length ? table(['Date', 'Source / campagne', 'Montant HT', 'Note', 'Actions'], data.items.map((row) => `<tr><td>${esc(dateText(row.date))}</td><td><strong>${esc(row.source)}</strong><small>${esc(row.campaign || 'Sans campagne')}</small></td><td class="bcrm-money">${esc(money(row.amount, row.currency))}</td><td>${esc(row.note || '—')}</td><td>${button('Supprimer', 'remove-cost', `data-id="${esc(row.id)}" aria-label="Supprimer la dépense du ${esc(dateText(row.date))}"`)}</td></tr>`)) : empty('Aucune dépense saisie', 'Ajoutez vos coûts publicitaires pour calculer les indicateurs de campagne.')}</section>`;
  }

  function newMarketingCost() {
    showDialog('Ajouter une dépense publicitaire', `<p class="bcrm-data-note">Utilisez les mêmes noms de source et de campagne que dans vos liens de suivi, par exemple les paramètres utm_source et utm_campaign. Le nom de campagne distingue les majuscules des minuscules.</p><form data-form="marketing-cost"><div class="bcrm-form-grid">${field('Date de la dépense', 'date', localDate(new Date()), 'date', 'required min="1900-01-01"')}${field(`Montant hors taxes (${boot.currency || 'EUR'})`, 'amount', '', 'number', 'min="0.01" max="1000000000" step="0.01" required')}${field('Source', 'source', '', 'text', 'required maxlength="80" placeholder="Nom de la plateforme"')}${field('Campagne (facultatif)', 'campaign', '', 'text', 'maxlength="160"')}</div>${area('Note (facultatif)', 'note', '', 'maxlength="500"')}${saveButton('Enregistrer la dépense')}</form>`);
  }
  function removeMarketingCost(id) {
    const cost = (state.data.items || []).find((row) => String(row.id) === String(id));
    if (!cost) return;
    showDialog('Supprimer cette dépense ?', `<p>La dépense <strong>${esc(money(cost.amount, cost.currency))}</strong> du ${esc(dateText(cost.date))}, pour ${esc(cost.source)}${cost.campaign ? ` / ${esc(cost.campaign)}` : ''}, sera retirée des calculs.</p><form data-form="remove-cost" data-id="${esc(cost.id)}"><div class="bcrm-actions">${saveButton('Confirmer la suppression')}${button('Annuler', 'close-dialog')}</div></form>`);
  }

  function integrationView(data) {
    const integration = data.integration, settings = data.settings;
    const observed = Number(integration.external_updates) > 0;
    return `<div class="bcrm-grid bcrm-grid-two"><section class="bcrm-panel"><div class="bcrm-panel-heading"><div><p class="bcrm-eyebrow">EXPÉDITIONS</p><h2>Connexion Iziship</h2></div>${badge(observed ? 'delivered' : 'pending', observed ? 'Remontées reçues' : 'À connecter')}</div><p>Votre compte Iziship se raccorde à WooCommerce. Ce tableau de bord lit ensuite les informations de suivi enregistrées dans les commandes.</p>${!integration.site_public_https ? '<div class="bcrm-callout is-warning"><strong>Un site public en HTTPS est nécessaire.</strong><p>Iziship ne peut pas joindre une boutique locale. La connexion réelle pourra être finalisée dès sa mise en ligne.</p></div>' : '<p class="bcrm-connection-ok">Le site utilise une adresse publique en HTTPS.</p>'}<ol class="bcrm-steps"><li><span>1</span><div><strong>Préparer la boutique</strong><p>WooCommerce doit être actif, avec une adresse publique en HTTPS accessible à Iziship.</p></div></li><li><span>2</span><div><strong>Créer les accès dédiés dans WooCommerce</strong><p>Suivez les permissions et paramètres du guide Iziship. Conservez ces accès pour cette connexion uniquement.</p></div></li><li><span>3</span><div><strong>Finaliser avec Iziship</strong><p>Transmettez les accès par le canal sécurisé convenu avec Iziship, puis faites valider une première remontée de suivi.</p></div></li></ol><div class="bcrm-actions"><a class="bcrm-button is-primary" target="_blank" rel="noopener noreferrer" href="${esc(safeUrl(integration.guide_url || 'https://wiki.iziship.co/article/01-Votre-boutique-en-ligne/02-WooCommerce/01-guide-connexion'))}">Guide officiel Iziship ↗</a>${integration.rest_keys_url ? `<a class="bcrm-button" href="${esc(safeUrl(integration.rest_keys_url))}">Accès API WooCommerce</a>` : ''}</div><p class="bcrm-data-note">Aucune clé n’est créée, demandée ou transmise depuis cet écran.</p>${warnings(integration.warnings)}</section>
      <section class="bcrm-panel"><p class="bcrm-eyebrow">ÉTAT DE LA SYNCHRONISATION</p><h2>Les informations reçues</h2><dl class="bcrm-definitions"><div><dt>Méthode de suivi</dt><dd>Métadonnée <code>${esc(integration.method || 'tracking_number')}</code></dd></div><div><dt>Dernière remontée externe</dt><dd>${esc(dateText(integration.last_sync, true))}</dd></div><div><dt>Mises à jour externes observées</dt><dd>${esc(number(integration.external_updates))}</dd></div></dl>${!observed ? '<div class="bcrm-callout"><p>Aucune remontée externe observée pour le moment. Une saisie manuelle dans ce CRM ne prouve pas que la connexion Iziship fonctionne.</p></div>' : ''}<h3>Champs pris en charge</h3><ul class="bcrm-field-list">${(integration.fields || []).map((row) => `<li><code>${esc(row.name)}</code><span>${esc(row.description)}</span></li>`).join('')}</ul><p class="bcrm-data-note">La réception d’un numéro de suivi confirme son enregistrement, pas la livraison du colis. Les événements de transport doivent être transmis ou renseignés séparément.</p></section></div>
      <section class="bcrm-panel"><div class="bcrm-panel-heading"><div><p class="bcrm-eyebrow">PRÉFÉRENCES</p><h2>Mesure d’audience et alertes</h2></div></div><form data-form="settings"><label class="bcrm-toggle"><input type="checkbox" name="analytics_enabled" ${settings.analytics_enabled ? 'checked' : ''}><span><strong>Activer la mesure d’audience locale</strong><small>Le comptage commence uniquement après le consentement du visiteur. Les visites des administrateurs sont exclues.</small></span></label><div class="bcrm-form-grid is-three">${field('Conservation de l’audience (jours)', 'retention_days', settings.retention_days, 'number', 'min="30" max="365" required')}${field('Seuil de stock bas (articles)', 'low_stock_threshold', settings.low_stock_threshold, 'number', 'min="1" max="100" required')}${field('Délai de livraison de référence (jours)', 'shipping_sla_days', settings.shipping_sla_days, 'number', 'min="1" max="30" required')}</div><p class="bcrm-data-note">Le délai sert de repère interne. Il ne modifie pas les engagements du transporteur ni les commandes.</p>${saveButton('Enregistrer les préférences')}</form></section>`;
  }

  function renderContent() {
    const renderers = { overview, orders: (data) => ordersView(data, false), shipments: (data) => ordersView(data, true), customers: customersView, stock: stockView, marketing: marketingView, tickets: (data) => `${Number(data.total) > (data.items || []).length ? warnings([`Les ${(data.items || []).length} demandes les plus récemment mises à jour sont affichées sur ${number(data.total)} résultats. Utilisez le filtre de statut pour affiner la liste.`]) : ''}${ticketsView(data)}`, integration: integrationView };
    app.querySelector('[data-content]').innerHTML = renderers[state.route](state.data);
  }

  function showDialog(title, html, wide) {
    if (!state.dialog) {
      state.opener = document.activeElement;
      const dialog = document.createElement('dialog');
      dialog.className = 'bcrm-dialog';
      dialog.setAttribute('aria-labelledby', 'bcrm-dialog-title');
      document.body.appendChild(dialog);
      state.dialog = dialog;
      dialog.addEventListener('click', handleClick);
      dialog.addEventListener('submit', handleSubmit);
      dialog.addEventListener('close', () => { dialog.remove(); state.dialog = null; state.busy = false; if (state.opener && state.opener.isConnected) state.opener.focus(); });
      dialog.addEventListener('cancel', (event) => { if (state.busy) event.preventDefault(); });
      dialog.showModal();
    }
    state.dialog.classList.toggle('is-wide', !!wide);
    state.dialog.innerHTML = `<header class="bcrm-dialog-header"><div><p class="bcrm-eyebrow">BELMAINS · ESPACE PRIVÉ</p><h2 id="bcrm-dialog-title" tabindex="-1">${esc(title)}</h2></div><button type="button" class="bcrm-icon-button" data-action="close-dialog" aria-label="Fermer">${icon('close')}</button></header><div class="bcrm-dialog-notice" role="status" aria-live="polite" hidden></div><div class="bcrm-dialog-body">${html}</div>`;
    state.dialog.querySelector('#bcrm-dialog-title').focus();
  }
  function dialogNotice(message, error) {
    if (!state.dialog) { notice(message, error); return; }
    const node = state.dialog.querySelector('.bcrm-dialog-notice');
    node.className = `bcrm-dialog-notice bcrm-notice ${error ? 'is-error' : 'is-success'}`;
    node.textContent = message; node.hidden = false;
  }
  async function openOrder(id) {
    showDialog(`Commande #${id}`, '<div class="bcrm-loading"><span class="bcrm-spinner"></span>Chargement de la commande…</div>', true);
    try {
      const order = await api(`orders/${encodeURIComponent(id)}`);
      if (!state.dialog) return;
      renderOrder(order);
    } catch (error) { if (state.dialog) showDialog('Commande indisponible', `<div class="bcrm-empty is-error"><p>${esc(error.message)}</p>${button('Réessayer', 'order', `data-id="${esc(id)}"`)}</div>`); }
  }
  function renderOrder(order) {
    const costs = order.costs || {}, tracking = order.tracking || {};
    let address = order.shipping_address;
    if (address && typeof address === 'object') address = Object.values(address).filter(Boolean).join('\n');
    showDialog(`Commande #${order.number || order.id}`, `<div class="bcrm-order-summary"><div>${badge(order.status, orderLabel(order.status, order.status_label))}<p>${esc(dateText(order.date, true))}</p></div><strong>${esc(money(order.total, order.currency))}</strong></div><div class="bcrm-grid bcrm-grid-two"><section class="bcrm-detail-block"><h3>Client</h3><p><strong>${esc(order.customer || 'Client invité')}</strong><br>${esc(order.email || '')}${order.billing_phone ? `<br>${esc(order.billing_phone)}` : ''}</p>${order.customer_note ? `<p class="bcrm-data-note">Message client : ${esc(order.customer_note)}</p>` : ''}</section><section class="bcrm-detail-block"><h3>Adresse de livraison</h3><p class="bcrm-preserve-lines">${esc(address || 'Non renseignée')}</p></section></div>
      ${table(['Article', 'Quantité', 'Montant'], (order.items || []).map((item) => `<tr><td>${esc(item.name)}</td><td>${esc(number(item.quantity))}</td><td>${esc(money(item.total, order.currency))}</td></tr>`))}
      <section class="bcrm-detail-block"><h3>Coûts de la commande</h3><p class="bcrm-data-note">Saisissez les coûts totaux hors taxes de cette commande. Une case vide signifie « non renseigné » ; zéro signifie « aucun coût ». La marge reste indisponible tant qu’un coût manque.</p><form data-form="costs" data-id="${esc(order.id)}"><div class="bcrm-form-grid">${field('Produits (€)', 'goods', costs.goods, 'number', 'min="0" step="0.01"')}${field('Expédition (€)', 'shipping', costs.shipping, 'number', 'min="0" step="0.01"')}${field('Emballage (€)', 'packaging', costs.packaging, 'number', 'min="0" step="0.01"')}${field('Frais de paiement (€)', 'fees', costs.fees, 'number', 'min="0" step="0.01"')}</div>${saveButton('Enregistrer les coûts')}</form></section>
      <section class="bcrm-detail-block"><h3>Suivi de l’expédition</h3><p class="bcrm-data-note">Source : ${esc(tracking.source || 'Aucun suivi enregistré')}. La saisie ci-dessous est manuelle et n’envoie aucun e-mail.</p><form data-form="tracking" data-id="${esc(order.id)}"><div class="bcrm-form-grid">${field('Numéro de suivi', 'number', tracking.number)}${field('Transporteur', 'carrier', tracking.carrier)}${field('Lien de suivi HTTPS', 'url', tracking.url, 'url', 'placeholder="https://…"')}${select('État du colis', 'status', shipmentLabels, tracking.status || 'pending')}${field('Date d’expédition', 'shipped_at', tracking.shipped_at ? String(tracking.shipped_at).slice(0, 10) : '', 'date')}${field('Date de livraison', 'delivered_at', tracking.delivered_at ? String(tracking.delivered_at).slice(0, 10) : '', 'date')}</div>${saveButton('Enregistrer le suivi')}</form></section>
      <section class="bcrm-detail-block"><h3>Notes internes</h3>${(order.notes || []).length ? `<ol class="bcrm-notes">${order.notes.map((note) => `<li><time>${esc(dateText(note.date, true))}</time><p class="bcrm-preserve-lines">${esc(note.content)}</p></li>`).join('')}</ol>` : '<p class="bcrm-muted">Aucune note interne.</p>'}<form data-form="note" data-id="${esc(order.id)}">${area('Ajouter une note interne', 'note', '', 'required maxlength="5000"')}<div class="bcrm-actions">${saveButton('Ajouter la note')}<span class="bcrm-data-note">Visible par l’équipe, sans e-mail au client.</span></div></form></section>${order.edit_url ? `<a class="bcrm-button" href="${esc(safeUrl(order.edit_url))}">Ouvrir dans WooCommerce ↗</a>` : ''}`, true);
  }
  function newTicket() {
    showDialog('Nouvelle demande client', `<p class="bcrm-data-note">Cette demande reste dans votre espace de suivi. Aucun e-mail n’est envoyé.</p><form data-form="new-ticket">${field('Objet de la demande', 'subject', '', 'text', 'required maxlength="200"')}<div class="bcrm-form-grid">${field('E-mail du client (facultatif)', 'customer_email', '', 'email', 'maxlength="200"')}${field('Numéro interne de commande (facultatif)', 'order_id', '', 'number', 'min="1" step="1"')}${select('Statut', 'status', ticketLabels, 'open')}${select('Priorité', 'priority', priorityLabels, 'normal')}</div>${area('Détail de la demande', 'message', '', 'required maxlength="6000"')}${saveButton('Créer la demande')}</form>`);
  }
  function openTicket(id) {
    const ticket = (state.data.items || []).find((item) => String(item.id) === String(id));
    if (!ticket) { notice('La demande n’est plus disponible. Actualisez la liste.', true); return; }
    showDialog(`Demande #${ticket.id}`, `<h3>${esc(ticket.subject)}</h3><p>${esc(ticket.customer_email)}${ticket.order_id ? ` · Commande #${esc(ticket.order_id)}` : ''}</p><p class="bcrm-data-note">Créée le ${esc(dateText(ticket.created_at, true))}</p><div class="bcrm-ticket-message bcrm-preserve-lines">${esc(ticket.message)}</div><form data-form="ticket" data-id="${esc(ticket.id)}"><div class="bcrm-form-grid">${select('Statut', 'status', ticketLabels, ticket.status)}${select('Priorité', 'priority', priorityLabels, ticket.priority)}</div>${area('Ajouter une note interne (facultatif)', 'note', '', 'maxlength="3000"')}<p class="bcrm-data-note">Aucun e-mail ne sera envoyé au client.</p>${saveButton('Mettre à jour la demande')}</form>`);
  }

  async function exportData(type, node) {
    node.disabled = true;
    try {
      const result = await api('export', { type, from: state.from, to: state.to });
      const blob = new Blob([result.csv], { type: 'text/csv;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a'); link.href = url; link.download = String(result.filename || `belmains-${type}.csv`).replace(/[\\/]/g, '-'); document.body.appendChild(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
      notice('Export téléchargé. Il contient toutes les données de la période choisie.');
    } catch (error) { notice(error.message, true); } finally { node.disabled = false; }
  }
  function navigate(route, overrides) {
    if (!routes[route]) return;
    state.route = route; state.page = 1; state.search = ''; state.status = ''; Object.assign(state, overrides || {});
    app.querySelector('[data-notice]').hidden = true;
    load();
  }
  function handleClick(event) {
    const node = event.target.closest('[data-action]');
    if (!node || node.disabled) return;
    const action = node.dataset.action;
    if (action === 'route') navigate(node.dataset.route);
    else if (action === 'refresh') load();
    else if (action === 'page') { state.page = Math.max(1, Number(node.dataset.page) || 1); load(); }
    else if (action === 'clear-filters') { state.search = ''; state.status = ''; state.page = 1; load(); }
    else if (action === 'customer-orders') navigate('orders', { search: node.dataset.email });
    else if (action === 'order') openOrder(node.dataset.id);
    else if (action === 'new-ticket') newTicket();
    else if (action === 'ticket') openTicket(node.dataset.id);
    else if (action === 'new-cost') newMarketingCost();
    else if (action === 'remove-cost') removeMarketingCost(node.dataset.id);
    else if (action === 'close-dialog' && !state.busy) state.dialog.close();
    else if (action === 'export') exportData(node.dataset.type, node);
  }
  async function handleSubmit(event) {
    const form = event.target;
    if (!form.matches('form[data-form]')) return;
    event.preventDefault();
    const kind = form.dataset.form;
    const data = Object.fromEntries(new FormData(form).entries());
    if (kind === 'period') {
      const start = new Date(`${data.from}T12:00:00Z`), end = new Date(`${data.to}T12:00:00Z`);
      if (!data.from || !data.to || start > end || (end - start) / 86400000 > 365) { notice('Choisissez une période valide de 366 jours maximum.', true); return; }
      state.from = data.from; state.to = data.to; state.page = 1; load(); return;
    }
    if (kind === 'filter') { state.search = (data.search || '').trim(); state.status = data.status || ''; state.page = 1; load(); return; }
    if (state.busy) return;
    state.busy = true;
    const submits = [...form.querySelectorAll('button[type="submit"]')]; submits.forEach((node) => { node.disabled = true; node.setAttribute('aria-busy', 'true'); });
    try {
      if (kind === 'settings') {
        await api('settings', null, { analytics_enabled: data.analytics_enabled === 'on', retention_days: Number(data.retention_days), low_stock_threshold: Number(data.low_stock_threshold), shipping_sla_days: Number(data.shipping_sla_days) });
        notice('Préférences enregistrées.');
        await load();
      } else if (kind === 'marketing-cost') {
        data.amount = Number(data.amount);
        await api('marketing/costs', null, data);
        state.dialog.close(); notice('Dépense enregistrée. Elle apparaît dans la période correspondant à sa date.'); await load();
      } else if (kind === 'remove-cost') {
        await api(`marketing/costs/${encodeURIComponent(form.dataset.id)}`, null, undefined, 'DELETE');
        state.dialog.close(); notice('Dépense supprimée des calculs.'); await load();
      } else if (kind === 'new-ticket') {
        if (!data.order_id) data.order_id = 0; else data.order_id = Number(data.order_id);
        await api('tickets', null, data);
        state.dialog.close(); notice('Demande créée. Aucun e-mail envoyé.'); await load();
      } else if (kind === 'ticket') {
        await api(`tickets/${encodeURIComponent(form.dataset.id)}`, null, data);
        state.dialog.close(); notice('Demande mise à jour. Aucun e-mail envoyé.'); await load();
      } else if (['costs', 'tracking', 'note'].includes(kind)) {
        if (kind === 'costs') Object.keys(data).forEach((key) => { data[key] = data[key] === '' ? null : Number(data[key]); });
        if (kind === 'tracking' && data.url && !/^https:\/\//i.test(data.url)) throw new Error('Le lien de suivi doit commencer par https://.');
        await api(`orders/${encodeURIComponent(form.dataset.id)}/${kind}`, null, data);
        const order = await api(`orders/${encodeURIComponent(form.dataset.id)}`);
        renderOrder(order); dialogNotice(kind === 'costs' ? 'Coûts enregistrés.' : kind === 'tracking' ? 'Suivi enregistré.' : 'Note interne ajoutée.');
        await load();
      }
    } catch (error) { if (state.dialog) dialogNotice(error.message, true); else notice(error.message, true); }
    finally { state.busy = false; submits.forEach((node) => { node.disabled = false; node.removeAttribute('aria-busy'); }); }
  }
  app.addEventListener('click', handleClick);
  app.addEventListener('submit', handleSubmit);
  app.addEventListener('change', (event) => {
    const field = event.target;
    if (field.name === 'ticket_status') { state.status = field.value; load(); }
    if (field.name === 'from' || field.name === 'to') app.querySelector('[name="preset"]').value = 'custom';
    if (field.name === 'preset' && field.value !== 'custom') {
      const end = new Date(), start = new Date(end);
      if (field.value === 'month') start.setDate(1);
      else if (field.value !== 'today') start.setDate(start.getDate() - Number(field.value) + 1);
      app.querySelector('[name="from"]').value = localDate(start); app.querySelector('[name="to"]').value = localDate(end);
      state.from = localDate(start); state.to = localDate(end); state.page = 1; load();
    }
  });
  renderShell(); load();
})();
