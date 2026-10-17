const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup({ changed = true, handled = false, hidden = false } = {}) {
    const listeners = {};
    const state = { reloads: 0, fetches: 0, events: 0, buttons: [], interval: null, confirm: false };
    const window = {
        location: { reload: () => state.reloads++ },
        confirm: () => state.confirm,
        addEventListener: (name, callback) => { listeners[name] = callback; },
        dispatchEvent: event => { state.events++; if (handled) event.detail.handled = true; },
    };
    const document = {
        hidden, currentScript: { dataset: { initialVersion: 'old', versionUrl: '/reports/data-version' } },
        addEventListener: (name, callback) => { listeners[name] = callback; },
        querySelector: () => null,
        createElement: () => ({ style: {}, addEventListener(name, callback) { this[name] = callback; }, remove() {} }),
        body: { append: button => state.buttons.push(button) },
    };
    vm.runInNewContext(fs.readFileSync('public/js/report-data-refresh.js','utf8'), {
        window, document, location: { pathname: '/dashboard' },
        setInterval: callback => { state.interval = callback; }, setTimeout: () => 1, clearTimeout() {},
        fetch: async () => { state.fetches++; return { ok: true, json: async () => ({ version: changed ? 'new' : 'old' }) }; },
        AbortController, CustomEvent: class { constructor(name, options) { this.type = name; this.detail = options.detail; } },
    });
    return { state, listeners, document };
}

test('unchanged data and hidden reports cause no refresh', async () => {
    for (const options of [{ changed: false }, { hidden: true }]) {
        const { state } = setup(options); await state.interval();
        assert.equal(state.reloads, 0); assert.equal(state.events, 0);
    }
});
test('new data refreshes a report once and keeps the version for later polls', async () => {
    const { state } = setup(); await state.interval(); await state.interval();
    assert.equal(state.reloads, 1); assert.equal(state.events, 1);
});
test('dashboards with a live handler update in place', async () => {
    const { state } = setup({ handled: true }); await state.interval();
    assert.equal(state.events, 1); assert.equal(state.reloads, 0);
});
test('unsaved inputs postpone refreshing and require a deliberate reload', async () => {
    const { state, listeners } = setup(); listeners.input({ target: { matches: selector => selector === 'input, textarea, select', closest: selector => selector === 'form' ? { getAttribute: () => 'POST' } : null } });
    await state.interval();
    assert.equal(state.events, 0); assert.equal(state.reloads, 0); assert.equal(state.buttons.length, 1);
    state.buttons[0].click(); assert.equal(state.reloads, 0);
    state.confirm = true; state.buttons[0].click(); assert.equal(state.reloads, 1);
});
test('report filters keep live updates enabled', async () => {
    const { state, listeners } = setup({ handled: true });
    listeners.change({ target: { matches: () => true, closest: () => ({}) } });
    await state.interval();
    assert.equal(state.events, 1); assert.equal(state.buttons.length, 0);
});
