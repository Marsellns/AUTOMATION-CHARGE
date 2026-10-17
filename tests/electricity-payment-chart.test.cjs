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

test('payment count labels remain complete above the columns with room for a full year', () => {
    const view = fs.readFileSync(path.join(__dirname, '../resources/views/dashboard/master.blade.php'), 'utf8');
    const source = view.slice(view.indexOf('    function renderElectricityPayment('), view.indexOf('    function renderElectricityAll('));
    let chart;
    const context = vm.createContext({
        cachedChartData: { filters: {} },
        corpColors: { success: 'green', primary: 'blue' },
        $: () => ({ text: () => {} }),
        upsertChart: (_key, _element, config) => { chart = config; },
    });
    vm.runInContext(source, context);
    context.renderElectricityPayment({
        period_labels: ['Jan 2026', 'Feb 2026'], active_sites: [4023, 4023],
        paid_counts: [3872, 4023], percentages: [96.25, 100], data_available: [true, true],
    }, { text: '#172033', gridLine: 'gray' });
    const labels = chart.plotOptions.column.dataLabels;
    assert.equal(labels.formatter.call({ point: { index: 0 } }), '3.872<br/>/ 4.023');
    assert.equal(labels.formatter.call({ point: { index: 1 } }), '4.023<br/>/ 4.023');
    assert.equal(labels.inside, false);
    assert.equal(labels.style.color, '#172033');
    assert.equal(chart.yAxis.endOnTick, false);
    assert.ok(chart.yAxis.max > 110, '100% columns need space above their two-line labels');
    assert.ok(chart.chart.scrollablePlotArea.minWidth >= 760, 'narrow screens must retain readable month columns');
});

test('payment detail discards stale responses and finishes loading on timeout or modal close', () => {
    const view = fs.readFileSync(path.join(__dirname, '../resources/views/dashboard/master.blade.php'), 'utf8');
    const source = view.slice(view.indexOf('    function showElectricityPaymentDetail('), view.indexOf('    function escapeHtml('));
    const requests = [];
    const content = new Map();
    let onClose;
    const $ = selector => ({
        text: value => content.set(selector, value),
        html: value => content.set(selector, value),
        on: (_event, handler) => { onClose = handler; },
    });
    $.ajax = options => {
        const request = {
            options,
            done(handler) { this.resolve = handler; return this; },
            fail(handler) { this.reject = handler; return this; },
            always(handler) { this.finish = handler; return this; },
            abort() { this.aborted = true; this.reject({ statusText: 'abort' }); this.finish(); },
        };
        requests.push(request);
        return request;
    };
    const context = vm.createContext({
        $, paymentDetailRequest: null, paymentDetailRequestId: 0, monthNames: ['Jan', 'Feb'],
        document: { getElementById: () => ({}) },
        bootstrap: { Modal: { getOrCreateInstance: () => ({ show() {} }) } },
        escapeHtml: value => String(value ?? '-'),
    });
    vm.runInContext(source, context);
    context.showElectricityPaymentDetail(2026, 1, 'BEKASI');
    context.showElectricityPaymentDetail(2026, 2, 'BOGOR');
    assert.equal(requests[0].aborted, true);
    assert.equal(requests[1].options.timeout, 30000);
    assert.equal(requests[1].options.data.bulan, 2);
    requests[0].resolve({ total: 99, sites: [{ site_id: 'STALE' }] });
    assert.doesNotMatch(content.get('#electricity-payment-detail-body'), /STALE/);
    requests[1].reject({ statusText: 'timeout' });
    assert.match(content.get('#electricity-payment-detail-summary'), /terlalu lama/);
    assert.doesNotMatch(content.get('#electricity-payment-detail-body'), /Memuat data/);
    requests[1].finish();
    assert.equal(context.paymentDetailRequest, null);

    context.showElectricityPaymentDetail(2026, 1, 'BEKASI');
    onClose();
    requests[2].resolve({ total: 99, sites: [{ site_id: 'CLOSED' }] });
    assert.equal(requests[2].aborted, true);
    assert.equal(context.paymentDetailRequest, null);
    assert.doesNotMatch(content.get('#electricity-payment-detail-body'), /CLOSED/);
});

test('dashboard renders payment data from its shared response and refreshes the chart theme', () => {
    const view = fs.readFileSync(path.join(__dirname, '../resources/views/dashboard/master.blade.php'), 'utf8');
    const source = view.slice(view.indexOf('    function renderAllCharts('), view.indexOf('    // Filter controls management'));
    const payload = { electricity_payment: { paid_counts: [3872], active_sites: [4023] } };
    const calls = [];
    let theme = { text: '#172033' };
    const chain = { each() { return this; }, addClass() { return this; } };
    const context = vm.createContext({
        $: () => chain,
        cachedChartData: null,
        getThemeStyles: () => theme,
        applyHighchartsTheme() {},
        renderElectricityPayment: (data, styles) => calls.push({ data, styles }),
        ...Object.fromEntries(['renderGrowthBars', 'renderCategoryDonut', 'renderSiteOwners', 'renderElectricityAll',
            'renderAnalyticsPerformance', 'renderStorageAllocation', 'renderPeakUsage', 'renderErrorDistribution'].map(name => [name, () => {}])),
    });
    vm.runInContext(source, context);
    context.renderAllCharts(payload);
    theme = { text: '#e5e7eb' };
    context.renderAllCharts(context.cachedChartData);
    assert.equal(calls.length, 2);
    assert.equal(calls[0].data, payload.electricity_payment);
    assert.equal(calls[1].styles.text, '#e5e7eb');
    assert.doesNotMatch(view, /loadElectricityPaymentData|electricityPaymentApiUrl/);
});
