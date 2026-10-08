const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

test('payment chart keeps missing periods empty while retaining a real zero payment rate', () => {
    const view = fs.readFileSync(path.join(__dirname, '../resources/views/dashboard/master.blade.php'), 'utf8');
    const source = view.slice(view.indexOf('    function renderElectricityPayment('), view.indexOf('    function renderElectricityAll('));
    let chart;
    let caption;
    const context = vm.createContext({
        cachedChartData: { filters: {} },
        corpColors: { success: 'green', primary: 'blue' },
        $: () => ({ text: value => { caption = value; } }),
        upsertChart: (_key, _element, config) => { chart = config; },
    });
    vm.runInContext(source, context);
    context.renderElectricityPayment({
        period_labels: ['Jan 2026', 'Feb 2026', 'Mar 2026'],
        active_sites: [2, 2, 2], paid_counts: [1, 0, 0],
        percentages: [50, 0, null], costs: [100, 0, 0],
        periods: [{ tahun: 2026, bulan: 1 }, { tahun: 2026, bulan: 2 }, { tahun: 2026, bulan: 3 }],
        data_available: [true, true, false],
    }, { text: 'white', gridLine: 'gray' });
    assert.deepEqual(Array.from(chart.series[0].data, point => point.y), [50, 0, null]);
    assert.match(caption, /site pada snapshot/);
    assert.match(caption, /tanpa data tidak dihitung sebagai 0%/);
});
