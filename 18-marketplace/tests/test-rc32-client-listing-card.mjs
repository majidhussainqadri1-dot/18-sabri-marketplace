import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const script = readFileSync(new URL('../assets/js/marketplace.js', import.meta.url), 'utf8');
const site = 'https://sabrihomeopathy.com';
const items = [
  { url: 'javascript:alert(1)', title: 'bad' },
  { url: 'https://outside.example/marketplace/listing/999/', title: 'bad' },
  { url: '/admin/', title: 'bad' },
  null,
  { url: site + '/marketplace/listing/123/', title: '<img src=x onerror=1>',
    category: null, currency: 'PKR', price: '10 & 20', seller: null },
  { url: '/marketplace/listing/456/', title: 'Second', category: 'learning',
    currency: 'PKR', price: '100', seller: { store_name: 'Safe Shop' } },
];
function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[c]);
}
function element() {
  let content = '';
  return {
    className: '',
    set innerHTML(v) { content = String(v); },
    get innerHTML() { return content; },
    set textContent(v) { content = escapeHtml(v); },
    get textContent() { return content; },
  };
}
const appended = [];
const grid = { append(node) { appended.push(node.innerHTML); } };
let click = null;
const button = {
  dataset: { cursor: '0' }, textContent: 'More', disabled: false,
  setAttribute() {}, removeAttribute() {}, remove() {},
  addEventListener(type, listener) { if (type === 'click') click = listener; },
};
const document = {
  querySelector(selector) {
    if (selector === '[data-mkt-load-more]') return button;
    if (selector === '#mkt-listings') return grid;
    return null;
  },
  querySelectorAll() { return []; },
  createElement: element,
};
const context = vm.createContext({
  window: { MKT_APP: {
    restUrl: site + '/wp-json/marketplace/v1/',
    listingBase: site + '/marketplace/listing/',
    strings: { working: 'Working', error: 'Error', viewListing: 'تفصیل دیکھیں', otherCategory: 'دیگر' },
  } },
  document, location: { origin: site, search: '', href: site + '/marketplace/' },
  URL, URLSearchParams,
  fetch: async () => ({ ok: true, json: async () => ({ items, has_more: false }) }),
});
vm.runInContext(script, context, { filename: 'marketplace.js' });
assert.equal(typeof click, 'function', 'load more click handler registered');
await click({ currentTarget: button });
assert.equal(appended.length, 2, 'only two eligible, safe same-origin listing URLs render');
const html = appended.join('\n');
assert.ok(!html.includes('javascript:'), 'no javascript URLs');
assert.ok(!html.includes('outside.example'), 'no external URL');
assert.ok(!html.includes('href="/admin/"'), 'no nonlisting internal routes');
assert.ok(html.includes('&lt;img'), 'untrusted title escaped');
assert.ok(!html.includes('<img'), 'HTML injection suppressed');
assert.ok(html.includes('PKR 10 &amp; 20'), 'money text escaped');
assert.ok(html.includes('تفصیل دیکھیں'), 'localizable button label consumed');
assert.ok(html.includes('دیگر'), 'null category safely localized');
assert.ok(html.includes('https://sabrihomeopathy.com/marketplace/listing/123/'), 'canonical URL retained');
assert.ok(html.includes('https://sabrihomeopathy.com/marketplace/listing/456/'), 'relative canonical URL retained');
console.log('PASS RC32: 12 client-rendering safety and localization assertions');
