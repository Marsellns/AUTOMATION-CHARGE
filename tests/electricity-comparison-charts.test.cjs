const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const window = {};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/js/electricity-comparison-charts.js'), 'utf8'), { window });
const renderer = window.SimasterElectricityComparison;
const data = {
    labels: ['Jan 2026', 'Feb 2026', 'Mar 2026'], periods: [{ tahun: 2026, bulan: 1 }], notes: [],
    series: [
        { key: 'macro', name: 'Site Makro', source: 'Flagging', population_label: 'Snapshot', points: [
            { available: true, paid_sites: 3872, population: 4023, percentage: 96.25, amount: 14e9 },
            { available: false, paid_sites: null, population: 0, percentage: null, amount: null },
            { available: true, paid_sites: 0, population: 4023, percentage: 0, amount: 0 },
        ] },
        { key: 'inbuilding', name: 'Site Inbuilding', source: 'IBC Done', population_label: 'Master aktif', points: [
            { available: true, paid_sites: 116, population: 186, percentage: 62.37, amount: 742037209, unmatched_sites: 1 },
        ] },
    ],
};

test('shared comparison preserves absent versus zero months, source metadata, and click periods', () => {
    let clicked;
    const options = renderer.options(data, { text: '#123', gridLine: '#cbd5e1' }, 'sites', (...args) => { clicked = args; });
    assert.deepEqual(Array.from(options.series[0].data, point => point.y), [3872, null, 0]);
    assert.equal(options.series[1].data[0].custom.population, 186);
    const labels = options.plotOptions.column.dataLabels;
    assert.equal(labels.formatter.call({ y: 116, point: options.series[1].data[0] }), '116<br/>(62,4%)');
    options.plotOptions.column.point.events.click.call({ index: 0, series: { options: options.series[1] } });
    assert.deepEqual(clicked, ['inbuilding', data.periods[0], 'sites']);
    assert.match(renderer.caption(data), /186 site aktif pada master saat ini/);
    const tooltip = options.tooltip.formatter.call({ point: { ...options.series[1].data[0], index: 0 }, series: options.series[1] });
    assert.match(tooltip, /di luar master/);
    assert.match(tooltip, /742\.037\.209/);
    assert.equal(labels.formatter.call({ y: 4021, point: { custom: { percentage: 99.95 } } }), '4.021<br/>(99,95%)');
});

test('amount chart measures rupiah while the site chart measures site counts', () => {
    let clicked;
    const amount = renderer.options(data, { text: '#fff', gridLine: '#475569' }, 'amount', (...args) => { clicked = args; });
    assert.equal(amount.series[0].data[0].y, 14e9);
    assert.equal(amount.series[1].data[0].y, 742037209);
    assert.equal(amount.plotOptions.column.dataLabels.enabled, false);
    assert.match(amount.yAxis.title.text, /Rp/);
    assert.equal(amount.yAxis.labels.formatter.call({ value: 14e9 }), 'Rp 14 miliar');
    assert.equal(amount.yAxis.gridLineWidth, 1);
    assert.equal(amount.xAxis.gridLineWidth, 1);
    assert.equal(amount.chart.backgroundColor, 'transparent');
    amount.plotOptions.column.point.events.click.call({ index: 0, series: { options: amount.series[1] } });
    assert.deepEqual(clicked, ['inbuilding', data.periods[0], 'amount']);
});
