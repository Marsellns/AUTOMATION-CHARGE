const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function setup() {
    const element = () => ({ children: [], textContent: '', events: {}, attributes: {}, addEventListener(key, fn) { this.events[key] = fn; },
        appendChild(child) { this.children.push(child); }, replaceChildren() { this.children = []; }, setAttribute(key, value) { this.attributes[key] = value; } });
    const nodes = Object.fromEntries(['title', 'summary', 'note', 'rows', 'page', 'prev', 'next', 'retry', 'content'].map(key => [key, element()]));
    const root = { ...element(), dataset: { url: '/electricity/payment-detail' }, querySelector(selector) { return nodes[selector.slice(13, -1)]; } };
    const requests = [];
    const timers = new Map();
    let timerId = 0, shows = 0;
    const window = {};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/js/electricity-payment-detail.js'), 'utf8'), {
        window, URLSearchParams, AbortController,
        document: { getElementById: () => root, createElement: element },
        bootstrap: { Modal: { getOrCreateInstance: () => ({ show() { shows++; } }) } },
        fetch(url, options) { return new Promise((resolve, reject) => { requests.push({ url, options, resolve: data => resolve({ ok: true, json: async () => data }), reject }); }); },
        setTimeout(fn) { timers.set(++timerId, fn); return timerId; }, clearTimeout(id) { timers.delete(id); },
    });
    return { open: window.SimasterElectricityDetail.open, nodes, root, requests, timers, shows: () => shows };
}
const payload = { page: 1, last_page: 2, total: 105, records_count: 106, amount: 300, source: 'Payment IBC', note: 'Master aktif',
    sites: [{ site_id: 'IBC-1', site_name: '<img src=x onerror=alert(1)>', records_count: 2, amount: 300, membership: 'Master aktif' }] };

test('bar popup opens in place, uses period/metric/NOP, renders database text safely and pages', async () => {
    const ui = setup();
    const result = ui.open('inbuilding', { tahun: 2026, bulan: 2 }, 'amount');
    assert.equal(ui.shows(), 1);
    assert.match(ui.requests[0].url, /source=inbuilding&tahun=2026&bulan=2&metric=amount&nop=&page=1/);
    ui.requests[0].resolve(payload);
    await result;
    assert.match(ui.nodes.title.textContent, /Inbuilding — Februari 2026/);
    assert.match(ui.nodes.summary.textContent, /105 site.*Rp 300/);
    assert.equal(ui.nodes.rows.children[0].children[1].textContent, payload.sites[0].site_name);
    assert.equal(ui.nodes.content.attributes['aria-busy'], 'false');
    assert.equal(ui.nodes.next.disabled, false);
    ui.nodes.next.events.click();
    assert.match(ui.requests[1].url, /page=2/);
    ui.requests[1].resolve({ ...payload, page: 2 });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(ui.nodes.next.disabled, true);
});

test('rapid bar changes and closed modals discard late responses', async () => {
    const ui = setup();
    const old = ui.open('macro', { tahun: 2026, bulan: 1 }, 'sites', 'BEKASI');
    const latest = ui.open('inbuilding', { tahun: 2026, bulan: 2 }, 'billing');
    assert.equal(ui.requests[0].options.signal.aborted, true);
    ui.requests[1].resolve(payload);
    await latest;
    ui.requests[0].resolve({ ...payload, source: 'STALE' });
    await old;
    assert.doesNotMatch(ui.nodes.summary.textContent, /STALE/);
    const closing = ui.open('inbuilding', { tahun: 2026, bulan: 3 });
    ui.root.events['hidden.bs.modal']();
    ui.requests[2].resolve(payload);
    await closing;
    assert.equal(ui.requests[2].options.signal.aborted, true);
    assert.equal(ui.nodes.summary.textContent, 'Memuat rincian dari database…');
});

test('timeout provides a retry and an empty month stays empty', async () => {
    const ui = setup();
    const result = ui.open('inbuilding', { tahun: 2026, bulan: 1 });
    [...ui.timers.values()][0]();
    const error = new Error('Aborted'); error.name = 'AbortError';
    ui.requests[0].reject(error);
    await result;
    assert.match(ui.nodes.summary.textContent, /Waktu permintaan habis/);
    assert.equal(ui.nodes.retry.hidden, false);
    ui.nodes.retry.events.click();
    ui.requests[1].resolve({ ...payload, sites: [], total: 0, amount: 0, last_page: 1 });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(ui.nodes.rows.children[0].children[0].textContent, 'Belum ada data pada periode dan filter ini.');
    assert.equal(ui.nodes.retry.hidden, true);
});
