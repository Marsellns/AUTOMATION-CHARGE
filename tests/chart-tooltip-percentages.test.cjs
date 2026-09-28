const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('shared chart metric helper calculates a contribution against its series total', () => {
    const layout = read('resources/views/layouts/app.blade.php');

    assert.match(layout, /window\.SimasterChartMetrics = Object\.freeze/);
    assert.match(layout, /Math\.abs\(numericValue\) \/ total/);
    assert.match(layout, /highcharts\(point\)/);
    assert.match(layout, /chartJs\(context\)/);
    assert.match(layout, /apex\(value, options\)/);
    assert.match(layout, /Array\.isArray\(currentSeries\) \? currentSeries : series/);
    assert.match(layout, /maximumFractionDigits: digits/);
});

test('Highcharts tooltips include the percentage of their corresponding series', () => {
    const dashboard = read('resources/views/dashboard/master.blade.php');
    const pnl = read('resources/views/pnl/index.blade.php');
    const analytics = read('resources/views/infrastruktur/partials/analytics.blade.php');
    const centralized = read('resources/views/electricity/centralized/listrik-all/index.blade.php');
    const inbuilding = read('resources/views/electricity/inbuilding/inbuilding-all/index.blade.php');

    for (const template of [dashboard, pnl, analytics, centralized, inbuilding]) {
        assert.match(template, /SimasterChartMetrics\.highcharts/);
        assert.match(template, /Persentase|dari total/);
    }
});

test('Chart.js and ApexCharts tooltips include percentage values', () => {
    const pln = read('resources/views/electricity/centralized/listrik-pln/grafik.blade.php');
    const dashboard = read('resources/views/dashboard/index.blade.php');
    const inbuilding = read('resources/views/electricity/inbuilding/listrik-inbuilding/index.blade.php');
    const equipmentRelocation = read('public/assets/equipment-relocation/js/index.js');

    assert.match(pln, /SimasterChartMetrics\.chartJs\(context\)/);
    assert.match(pln, /Persentase total tagihan/);
    assert.match(dashboard, /SimasterChartMetrics\.apex\(value, options\)/);
    assert.match(inbuilding, /SimasterChartMetrics\.apex\(val, options\)/);
    assert.match(equipmentRelocation, /pct\(percentage\)/);
});
