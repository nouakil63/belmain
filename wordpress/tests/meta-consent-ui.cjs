// Consent banner in front of the official Meta extension, in jsdom. The extension itself is
// decided on the server: this script only records the choice, asks for one reload after an
// accord and silences the pixel on withdrawal. It must never load anything from Meta itself.
// Usage: node wordpress/tests/meta-consent-ui.cjs   (jsdom 30, see README-shipping.md for NODE_PATH)
const { JSDOM, VirtualConsole } = require('jsdom');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const base = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(base, 'belmains-crm/assets/audience.js'), 'utf8');
const both = '<aside id="bcrm-consent" class="bcrm-consent bcrm-consent--ads" hidden><div><strong>Vos choix</strong><p>Texte</p></div>'
  + '<div class="bcrm-consent-purposes" id="bcrm-consent-purposes" hidden><label><input type="checkbox" data-bcrm-purpose="audience"></label><label><input type="checkbox" data-bcrm-purpose="ads"></label></div>'
  + '<div class="bcrm-consent-actions"><button type="button" data-bcrm-consent="yes">Tout accepter</button><button type="button" data-bcrm-consent="no">Tout refuser</button><button type="button" data-bcrm-consent="custom" aria-expanded="false">Personnaliser</button></div></aside>'
  + '<button type="button" id="bcrm-privacy-choice">Gérer mes choix de confidentialité</button>';
const single = '<aside id="bcrm-consent" class="bcrm-consent" hidden><div class="bcrm-consent-actions"><button type="button" data-bcrm-consent="yes">Accepter</button><button type="button" data-bcrm-consent="no">Refuser</button></div></aside><button type="button" id="bcrm-privacy-choice">Mes choix</button>';
const waiting = { live: false, reload: true }; // page served before the accord
const running = { live: true, reload: true };  // page served with the official extension

let checks = 0;
const check = (value, message) => { assert.ok(value, message); checks++; console.log('PASS ' + message); };

function visit({ markup = both, audience = '1', meta = waiting, cookies = [], gpc = false, product = false, officialPixel = false } = {}) {
  // jsdom does not navigate: a reload request surfaces as a "not implemented" error, counted here.
  let reloads = 0;
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', error => { if (/navigation/i.test(String(error && error.message))) reloads++; });
  const dom = new JSDOM(`<!doctype html><html><head></head><body>${product ? '<section id="fiche-produit"></section>' : ''}${markup}</body></html>`, { url: 'https://belmains.test/', runScripts: 'outside-only', pretendToBeVisual: true, virtualConsole });
  const w = dom.window;
  cookies.forEach(cookie => { w.document.cookie = cookie + ';path=/'; });
  if (gpc) Object.defineProperty(w.navigator, 'globalPrivacyControl', { value: true });
  // What the official extension would have put in a page served after the accord.
  if (officialPixel) { const queue = w.fbq = function () { queue.queue.push(arguments); }; queue.queue = []; }
  const posts = []; let observed = null;
  w.fetch = async (url, options) => { posts.push(JSON.parse(options.body)); return { ok: true }; };
  w.IntersectionObserver = class { constructor(callback) { observed = callback; } observe() {} disconnect() { observed = null; } };
  w.BelmainsAudience = { audience, meta, endpoint: 'https://belmains.test/wp-json/belmains-crm/v1/collect', cookiePath: '/', isProduct: '', isCheckout: '' };
  w.eval(source);
  const cookie = name => (w.document.cookie.split('; ').find(v => v.startsWith(name + '=')) || '').split('=').slice(1).join('=');
  return {
    w, posts, cookie,
    reloads: () => reloads,
    calls: () => (w.fbq ? Array.from(w.fbq.queue, args => Array.from(args)) : []),
    scripts: () => Array.from(w.document.querySelectorAll('script[src]'), node => node.src),
    click: selector => w.document.querySelector(selector).click(),
    hidden: () => w.document.getElementById('bcrm-consent').hidden,
    see: () => observed && observed([{ isIntersecting: true }]),
  };
}
const untouched = page => page.scripts().length === 0 && typeof page.w.fbq === 'undefined';

// New visitor.
let page = visit({ product: true });
check(!page.hidden() && untouched(page) && page.reloads() === 0, 'new visitor: banner shown, nothing from Meta, no reload');
page.see();
check(untouched(page) && page.posts.length === 0, 'viewing the product before any choice measures nothing');
page.click('[data-bcrm-consent="no"]');
check(page.cookie('bcrm_ads') === 'no' && page.cookie('bcrm_consent') === 'no' && page.hidden() && untouched(page) && page.reloads() === 0, 'refusing everything records both refusals, loads nothing and does not reload');

// Customise: nothing ticked, advertising alone.
page = visit({ product: true });
page.click('[data-bcrm-consent="custom"]');
check(!page.w.document.getElementById('bcrm-consent-purposes').hidden && page.w.document.querySelectorAll('[data-bcrm-purpose]:checked').length === 0, 'customising shows unticked purposes');
check(page.w.document.querySelector('[data-bcrm-consent="custom"]').textContent === 'Enregistrer mes choix' && !page.cookie('bcrm_ads') && page.reloads() === 0, 'opening the purposes saves nothing yet');
page.w.document.querySelector('[data-bcrm-purpose="ads"]').checked = true;
page.click('[data-bcrm-consent="custom"]');
check(page.cookie('bcrm_ads') === 'yes' && page.cookie('bcrm_consent') === 'no' && !page.cookie('bcrm_v'), 'advertising accepted alone keeps audience refused');
check(page.reloads() === 1 && untouched(page), 'the accord asks for one reload and loads nothing from Meta itself');
page.see();
check(page.posts.length === 0, 'the refused audience is not measured');

// Accept everything: the reloaded page does the counting, so nothing is counted twice.
page = visit();
page.click('[data-bcrm-consent="yes"]');
check(page.cookie('bcrm_ads') === 'yes' && page.cookie('bcrm_consent') === 'yes' && page.reloads() === 1 && page.hidden(), 'accepting everything records both accords and reloads once');
check(page.posts.length === 0 && untouched(page), 'no local page view is counted before the reload');

// Where a reload would lose the visitor's input (checkout form), the accord applies from the next page.
page = visit({ meta: { live: false, reload: false } });
page.click('[data-bcrm-consent="yes"]');
check(page.cookie('bcrm_ads') === 'yes' && page.reloads() === 0 && untouched(page) && page.posts.some(post => post.event === 'pageview'), 'no reload on the checkout form: the audience starts, Meta waits for the next page');

// Page served after the accord: nothing to do, and never a reload on load.
page = visit({ meta: running, cookies: ['bcrm_ads=yes', 'bcrm_consent=yes'], officialPixel: true });
check(page.hidden() && page.reloads() === 0 && page.calls().length === 0 && page.posts.some(post => post.event === 'pageview'), 'returning visitor: no prompt, no reload, the official pixel is left alone');
// A cached page served without the extension to a visitor who already agreed must not loop.
page = visit({ meta: waiting, cookies: ['bcrm_ads=yes', 'bcrm_consent=no'] });
check(page.hidden() && page.reloads() === 0 && untouched(page), 'an accord given earlier never triggers a reload on load');
// Re-accepting on a page that already runs the extension changes nothing.
page = visit({ meta: running, cookies: ['bcrm_ads=yes', 'bcrm_consent=yes'], officialPixel: true });
page.click('#bcrm-privacy-choice');
check(!page.hidden(), 'footer link reopens the choices');
page.click('[data-bcrm-consent="yes"]');
check(page.reloads() === 0 && page.calls().length === 0, 'confirming an accord on a running page neither reloads nor touches the pixel');

// Withdrawal through the footer link.
page = visit({ meta: running, cookies: ['bcrm_ads=yes', 'bcrm_consent=yes', '_fbp=fb.1.1.1', '_fbc=fb.1.1.abc', 'wc_facebook_signals_state=active'], officialPixel: true });
page.click('#bcrm-privacy-choice');
page.click('[data-bcrm-consent="no"]');
const last = page.calls().pop();
check(page.cookie('bcrm_ads') === 'no' && last && last[0] === 'consent' && last[1] === 'revoke', 'withdrawal revokes the official pixel at once');
check(!page.cookie('_fbp') && !page.cookie('_fbc') && !page.cookie('wc_facebook_signals_state') && page.reloads() === 0, 'withdrawal removes the Meta cookies without reloading');
page = visit({ meta: waiting, cookies: ['bcrm_ads=yes', 'bcrm_consent=no', '_fbp=fb.1.1.1'] });
page.click('#bcrm-privacy-choice');
page.click('[data-bcrm-consent="no"]');
check(page.cookie('bcrm_ads') === 'no' && !page.cookie('_fbp') && untouched(page), 'withdrawal works on a page without the pixel');
// Partial withdrawal through the purposes.
page = visit({ meta: running, cookies: ['bcrm_ads=yes', 'bcrm_consent=yes'], officialPixel: true });
page.click('#bcrm-privacy-choice');
page.click('[data-bcrm-consent="custom"]');
check(page.w.document.querySelector('[data-bcrm-purpose="ads"]').checked && page.w.document.querySelector('[data-bcrm-purpose="audience"]').checked, 'earlier accords are shown ticked');
page.w.document.querySelector('[data-bcrm-purpose="ads"]').checked = false;
page.click('[data-bcrm-consent="custom"]');
check(page.cookie('bcrm_ads') === 'no' && page.cookie('bcrm_consent') === 'yes' && page.calls().some(call => call[0] === 'consent' && call[1] === 'revoke') && page.reloads() === 0, 'advertising withdrawn alone keeps the audience accord');

// Earlier audience answer, advertising offered later: the new purpose must be asked.
page = visit({ cookies: ['bcrm_consent=yes'] });
check(!page.hidden() && untouched(page) && page.reloads() === 0 && page.posts.some(post => post.event === 'pageview'), 'new advertising purpose is asked without assuming the earlier audience answer');
page.click('[data-bcrm-consent="custom"]');
check(page.w.document.querySelector('[data-bcrm-purpose="audience"]').checked && !page.w.document.querySelector('[data-bcrm-purpose="ads"]').checked, 'only the earlier explicit yes is shown ticked');

// Privacy signal, advertising not offered, malformed configuration, audience off.
page = visit({ gpc: true });
check(page.hidden() && untouched(page) && page.reloads() === 0, 'privacy signal: no prompt, nothing loaded');
page = visit({ markup: single, meta: null });
page.click('[data-bcrm-consent="yes"]');
check(page.cookie('bcrm_consent') === 'yes' && !page.cookie('bcrm_ads') && page.reloads() === 0 && untouched(page), 'without the official extension the audience-only banner never touches advertising');
page = visit({ markup: single, meta: 'javascript:alert(1)' });
page.click('[data-bcrm-consent="yes"]');
check(!page.cookie('bcrm_ads') && page.reloads() === 0 && untouched(page), 'malformed advertising configuration is ignored');
page = visit({ markup: single, audience: '' });
page.click('[data-bcrm-consent="yes"]');
check(page.cookie('bcrm_ads') === 'yes' && !page.cookie('bcrm_consent') && page.posts.length === 0 && page.reloads() === 1, 'advertising alone never starts the local audience');

console.log(checks + ' consent interface checks passed.');
