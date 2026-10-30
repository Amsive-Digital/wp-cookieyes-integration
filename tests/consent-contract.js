/* Execute the actual PHP-emitted initializer in isolated JavaScript contexts.
 * No vendor script, browser, network, cookies or real WordPress execution. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const vm = require('node:vm');

const html = execFileSync('php', [path.join(__dirname, 'plugin-contract.php'), '--emit-frontend'], { encoding: 'utf8' });
const match = html.match(/<script id="cookieyes-integration-consent-defaults">([\s\S]*?)<\/script>/);
assert.ok(match, 'Actual frontend output must contain the initializer');
assert.ok(html.indexOf(match[0]) < html.indexOf('id="cookieyes"'), 'Defaults must run before CookieYes');
const code = match[1];
const expected = {
    ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
    analytics_storage: 'denied', functionality_storage: 'denied',
    personalization_storage: 'denied', security_storage: 'granted', wait_for_update: 2000
};
function run(window) { vm.runInNewContext(code, { window }); return window; }
function commands(window) { return Array.from(window.dataLayer, entry => Array.from(entry)); }
function plain(value) { return JSON.parse(JSON.stringify(value)); }
function gtagCommand() { return arguments; }

const fresh = run({});
assert.deepEqual(plain(commands(fresh)), [['consent', 'default', expected]]);
run(fresh);
assert.equal(fresh.dataLayer.length, 1, 'Repeated script execution must not reset consent');
// A normal later gtag bootstrap can redeclare gtag without losing the queued default.
const originalGtag = fresh.gtag;
fresh.window = fresh;
vm.runInContext("window.dataLayer = window.dataLayer || []; function gtag() { window.dataLayer.push(arguments); } gtag('js', new Date()); gtag('config', 'G-SYNTHETIC');", vm.createContext(fresh));
assert.notEqual(fresh.gtag, originalGtag, 'The theme bootstrap must replace the actual global gtag');
assert.deepEqual(commands(fresh).map(entry => entry[0]), ['consent', 'js', 'config']);
// Simulate updates supplied by the CMP; this does not test CookieYes itself.
for (const analytics of ['denied', 'granted', 'denied']) {
    fresh.gtag('consent', 'update', { analytics_storage: analytics });
    run(fresh);
    assert.equal(commands(fresh).filter(entry => entry[1] === 'default').length, 1);
    assert.equal(commands(fresh).at(-1)[2].analytics_storage, analytics);
}
// The marker still protects against reinitialization if a consumer drains the queue.
fresh.dataLayer.length = 0;
run(fresh);
assert.equal(fresh.dataLayer.length, 0);

const event = { event: 'existing_event' };
const queued = [event];
const existingFunction = function () { queued.push(arguments); };
const existing = run({ dataLayer: queued, gtag: existingFunction });
assert.equal(existing.dataLayer, queued);
assert.equal(existing.gtag, existingFunction);
assert.equal(queued[0], event);
assert.deepEqual(plain(Array.from(queued[1])), ['consent', 'default', expected]);

for (const state of [{ analytics_storage: 'granted' }, { analytics_storage: 'denied', region: ['XX'] }]) {
    const prior = gtagCommand('consent', 'default', state);
    const window = run({ dataLayer: [null, event, prior] });
    assert.equal(window.dataLayer.length, 3, 'An existing default belongs to its existing owner');
    assert.equal(window.dataLayer[2], prior);
    assert.equal(typeof window.gtag, 'function');
}
const argumentDefault = gtagCommand('consent', 'default', expected);
assert.equal(run({ dataLayer: [argumentDefault] }).dataLayer.length, 1);
assert.equal(run({ dataLayer: [null, event, ['unrelated']] }).dataLayer.length, 4);
const malformedDefault = ['consent', 'default', { analytics_storage: 'granted' }];
assert.equal(run({ dataLayer: [malformedDefault] }).dataLayer.length, 2, 'A plain array is not a gtag arguments command');
console.log('PASS: emitted consent initializer, queue preservation, duplicate defaults, and simulated updates. Isolated JS; no live CMP/browser verification.');
