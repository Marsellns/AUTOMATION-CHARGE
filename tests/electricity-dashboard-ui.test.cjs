const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');

test('Electricity dashboard exposes its filters, KPI cards, and analytical charts', () => {
    const view = read('resources/views/electricity/dashboard/index.blade.php');
    const layout = read('resources/views/layouts/app.blade.php');

    for (const id of [
        'electricity-scope', 'electricity-year', 'electricity-month', 'electricity-nop',
        'kpi-electricity-sites', 'kpi-electricity-billed', 'kpi-electricity-rate',
        'chart-electricity-billing', 'chart-electricity-payment', 'chart-electricity-anomaly',
        'chart-electricity-sites', 'chart-electricity-nop',
    ]) {
        assert.match(view, new RegExp(`id="${id}"`), `missing #${id}`);
    }

    assert.match(view, /data-simaster-filter-panel="Filter Dashboard Electricity"/);
    assert.match(layout, /route\('electricity\.dashboard'\)/);
    assert.match(layout, />Dashboard Electricity</);
});

test('Electricity dashboard inline JavaScript remains valid after Blade URL rendering', () => {
    const view = read('resources/views/electricity/dashboard/index.blade.php');
    const script = [...view.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)]
        .map(match => match[1])
        .find(source => source.includes('const dataUrl'));

    assert.ok(script, 'inline dashboard script is missing');
    const rendered = script.replace(/@json\(route\('[^']+'\)\)/g, '"/generated-url"');
    assert.doesNotThrow(() => new vm.Script(rendered));
    assert.match(rendered, /textContent = value/);
    assert.match(rendered, /replaceChildren\(\)/);
});
