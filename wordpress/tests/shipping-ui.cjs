const { JSDOM } = require('jsdom');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const base = path.resolve(__dirname, '..');
if (!process.argv[2]) throw new Error('Pass the synthetic shipping-ui-fixture.json produced by shipping-integration.php');
const fixture = JSON.parse(fs.readFileSync(path.resolve(process.argv[2]), 'utf8'));
const source = fs.readFileSync(path.join(base, 'belmains-crm/assets/admin.js'), 'utf8');
fixture.customer = '<img src=x onerror=alert(1)> Client test';
fixture.shipping_address = 'Client test\n10 rue de validation\n75001 Paris\nFrance';
const dom = new JSDOM('<!doctype html><html><body><div id="bcrm-app"></div></body></html>', { url: 'https://belmains.test/wp-admin/admin.php?page=belmains-crm', runScripts: 'outside-only', pretendToBeVisual: true });
const { window: w } = dom;
const requests = [], downloads = [], copies = [];
let checks = 0, failNextSave = false, printed = '';
const check = (value, message) => { assert.ok(value, message); checks++; console.log('PASS ' + message); };
const tick = () => new Promise((resolve) => setImmediate(resolve));
async function until(test) { for (let i = 0; i < 50; i++) { if (test()) return; await tick(); } throw new Error('UI did not reach expected state'); }
w.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
w.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new w.Event('close')); };
w.HTMLAnchorElement.prototype.click = function () { downloads.push({ name: this.download, href: this.href }); };
w.URL.createObjectURL = () => 'blob:local-test'; w.URL.revokeObjectURL = () => {};
w.navigator.clipboard = { writeText: async (s) => { copies.push(s); } };
w.open = () => ({ opener: null, document: { write: (html) => { printed = html; }, close() {} }, focus() {}, print() {} });
w.BelmainsCRM = { api: 'https://belmains.test/wp-json/belmains-crm/v1/', nonce: 'test-only', siteName: 'Belmains test', currency: 'EUR' };
const settings = { analytics_enabled: false, retention_days: 90, low_stock_threshold: 5, shipping_sla_days: 3 };
function list() {
  return { page: 1, pages: 1, total: fixture.shipping.parcels.length, metrics: { parcels: fixture.shipping.parcels.length, overdue: 1, returns: 1 }, statuses: fixture.shipping.statuses, carriers: fixture.shipping.carriers, warnings: [], items: fixture.shipping.parcels.map((p) => ({ order_id: fixture.id, order_number: fixture.number, customer: fixture.customer, parcel: p })) };
}
w.fetch = async (url, options = {}) => {
  const u = new URL(url), route = u.pathname.split('/belmains-crm/v1/')[1];
  const payload = options.body ? JSON.parse(options.body) : undefined;
  requests.push({ route, options, payload, query: Object.fromEntries(u.searchParams) });
  let data, status = 200;
  if (route === 'dashboard') data = { commerce: { metrics: {}, daily: [], warnings: [] }, audience: { enabled: false, daily: [] }, connections: {} };
  else if (route === 'settings') data = settings;
  else if (route === 'parcels') data = list();
  else if (route === `orders/${fixture.id}`) data = fixture;
  else if (route === `orders/${fixture.id}/parcels`) {
    if (failNextSave) { failNextSave = false; status = 409; data = { message: 'Le suivi a changé. Rechargez la commande.' }; }
    else { const old = fixture.shipping.parcels.find((p) => p.id === payload.parcel.id); if (old) Object.assign(old, payload.parcel); else fixture.shipping.parcels.push({ ...payload.parcel, id: 'ui-new-parcel', status_label: 'À préparer', source: 'manual' }); fixture.shipping.revision++; data = { success: true }; }
  } else if (route === 'shipping-export') data = { filename: 'colis.csv', csv: 'Commande;Colis\n1;QA\n' };
  else if (route.endsWith('/shipping-history')) data = { order_id: fixture.id, shipping: fixture.shipping };
  else throw new Error('Unexpected UI request ' + route);
  return { ok: status < 400, status, json: async () => JSON.parse(JSON.stringify(data)) };
};
const click = (selector) => { const node = w.document.querySelector(selector); assert.ok(node, selector); node.click(); return node; };
const field = (name, value) => { const node = w.document.querySelector(`dialog [name="${name}"]`); assert.ok(node, name); node.value = value; };
const submit = (selector) => w.document.querySelector(selector).dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));

(async () => {
  w.eval(source);
  await until(() => w.document.querySelector('.bcrm-integration-strip'));
  click('[data-route="shipments"]');
  await until(() => w.document.querySelector('[data-form="shipping-filter"]'));
  check(w.document.querySelectorAll('.bcrm-shipping-filters select').length === 4, 'shipping filters show carrier, state, direction and priority');
  check(!w.document.querySelector('img[onerror]'), 'untrusted customer text is escaped');
  check(w.document.body.textContent.includes('Colis retour'), 'return counter rendered');
  w.document.querySelector('[name="carrier"]').value = 'chronopost';
  submit('[data-form="shipping-filter"]');
  await until(() => requests.some((r) => r.route === 'parcels' && r.query.carrier === 'chronopost'));
  check(requests.some((r) => r.query.carrier === 'chronopost'), 'filter values passed to API');
  await until(() => w.document.querySelector('[data-action="order"]'));
  click('[data-action="order"]');
  await until(() => w.document.querySelector('.bcrm-parcel-list'));
  check(w.document.querySelectorAll('.bcrm-parcel-card').length >= 5, 'all parcels rendered in order drawer');
  check(w.document.body.textContent.includes('10 rue des oliviers de serres'), 'confirmed carrier return address rendered');
  check(w.document.activeElement.id === 'bcrm-dialog-title', 'dialog focus reaches labelled heading');
  click('[data-action="parcel-edit"]');
  await until(() => w.document.querySelector('[data-form="parcel"]'));
  check(w.document.querySelector('[name="label_url"]').value.includes('private-label'), 'private label stays in admin editor');
  check(w.document.querySelector('[name="expected_at"]'), 'expected delivery date editable');
  field('status', 'in_transit'); field('note', 'Modification validée depuis le formulaire');
  submit('[data-form="parcel"]');
  await until(() => w.document.querySelector('.bcrm-parcel-list'));
  const save = requests.find((r) => r.route.endsWith('/parcels') && r.payload);
  check(Number.isInteger(save.payload.revision) && save.payload.parcel.status === 'in_transit', 'save sends numeric revision and selected status');
  check(save.options.headers['X-WP-Nonce'] === 'test-only' && save.options.credentials === 'same-origin', 'write uses WordPress nonce and same-origin cookies');
  await until(() => !w.document.querySelector('.bcrm-content[aria-busy="true"]'));
  click('[data-action="parcel-edit"]'); failNextSave = true; field('note', 'Do not lose this edit'); submit('[data-form="parcel"]');
  await until(() => w.document.querySelector('.bcrm-dialog-notice')?.textContent.includes('Le suivi a changé'));
  check(w.document.querySelector('[name="note"]').value === 'Do not lose this edit', 'conflict preserves user input and displays retry guidance');
  click('dialog [data-action="order"]'); await until(() => w.document.querySelector('.bcrm-parcel-list'));
  click('[data-action="parcel-new"][data-direction="return"]');
  check(w.document.querySelector('dialog [name="direction"]').value === 'return', 'new return form selects return direction');
  field('number', 'QA-RETURN-UI'); field('carrier_code', 'colissimo'); submit('[data-form="parcel"]');
  await until(() => w.document.querySelector('.bcrm-parcel-list') && fixture.shipping.parcels.some((p) => p.id === 'ui-new-parcel'));
  check(fixture.shipping.parcels.some((p) => p.direction === 'return' && p.number === 'QA-RETURN-UI'), 'return form saves its own parcel');
  await until(() => !w.document.querySelector('.bcrm-content[aria-busy="true"]'));
  click('[data-action="copy-tracking"]'); await tick(); check(copies[0].startsWith('QA-'), 'tracking number can be copied');
  click('[data-action="shipping-message"]');
  const message = w.document.querySelector('[name="shipping_message"]').value;
  check(message.includes('QA-COLISSIMO') && !message.includes('private-label') && !message.includes('PRIVATE-NOTE'), 'customer message includes parcels and excludes private fields');
  click('[data-action="copy-message"]'); await tick(); check(copies.includes(message), 'message copy does not send email');
  click('dialog [data-action="order"]'); await until(() => w.document.querySelector('.bcrm-parcel-list'));
  click('[data-action="packing-slip"]');
  check(printed.includes('Bon de préparation') && printed.includes('10 rue de validation') && !printed.includes('PRIVATE-NOTE'), 'packing slip prints recipient and contents without private note');
  click('[data-action="export-history"]'); await until(() => downloads.some((d) => d.name.endsWith('.json')));
  check(downloads.some((d) => d.name.endsWith('.json')), 'full history download available');
  click('[data-action="close-dialog"]');
  await until(() => w.document.querySelector('[data-action="export-shipping"]'));
  click('[data-action="export-shipping"]'); await until(() => downloads.some((d) => d.name === 'colis.csv'));
  check(requests.find((r) => r.route === 'shipping-export').query.carrier === 'chronopost', 'CSV export respects current carrier filter');
  check(requests.every((r) => !/notify|send|mail/.test(r.route)), 'UI tests send no external notification');
  console.log(`${checks} shipping UI checks passed (DOM simulation, no browser visual test)`);
  w.close();
})().catch((e) => { console.error(e); w.close(); process.exitCode = 1; });
