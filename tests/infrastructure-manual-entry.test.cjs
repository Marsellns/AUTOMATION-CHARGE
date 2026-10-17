const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function setup({ reopen = false, errors = false } = {}) {
    const submit = { disabled: false, textContent: 'Simpan Site' };
    const events = {}, windowEvents = {};
    let focus = '', shows = 0;
    const form = { querySelector: selector => selector === '[data-manual-submit]' ? submit : { focus() { focus = 'site'; } },
        addEventListener(key, fn) { events[key] = fn; } };
    const modal = { dataset: { reopen: String(reopen) }, querySelector: selector => selector === '[data-infrastructure-manual-form]' ? form
        : errors ? { focus() { focus = 'errors'; } } : null, addEventListener(key, fn) { events[key] = fn; } };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/js/infrastructure-manual-entry.js'), 'utf8'), {
        document: { readyState: 'complete', getElementById: () => modal },
        window: { addEventListener(key, fn) { windowEvents[key] = fn; } },
        bootstrap: { Modal: { getOrCreateInstance: () => ({ show() { shows++; } }) } },
    });
    return { submit, events, windowEvents, focus: () => focus, shows: () => shows };
}

test('manual entry prevents double submit and restores the button after browser back', () => {
    const ui = setup();
    let prevented = false;
    ui.events.submit({ preventDefault() { prevented = true; } });
    assert.equal(ui.submit.disabled, true);
    assert.equal(ui.submit.textContent, 'Menyimpan…');
    ui.events.submit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    ui.windowEvents.pageshow();
    assert.equal(ui.submit.disabled, false);
    assert.equal(ui.submit.textContent, 'Simpan Site');
});

test('server validation reopens the popup and directs focus to the error summary', () => {
    const ui = setup({ reopen: true, errors: true });
    assert.equal(ui.shows(), 1);
    ui.events['shown.bs.modal']();
    assert.equal(ui.focus(), 'errors');
    const normal = setup();
    assert.equal(normal.shows(), 0);
    normal.events['shown.bs.modal']();
    assert.equal(normal.focus(), 'site');
});
