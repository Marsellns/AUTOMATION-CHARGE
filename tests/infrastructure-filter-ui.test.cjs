const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');

test('Sewa Lahan and Combat chart selections use their existing lower table', () => {
    const analytics = read('resources/views/infrastruktur/partials/analytics.blade.php');
    const guard = analytics.indexOf("if (scope === 'sewa' || scope === 'combat') {");
    const modalLookup = analytics.indexOf("document.getElementById('infraDrilldownModal')", guard);

    assert.ok(guard >= 0, 'scope guard for Sewa Lahan and Combat is missing');
    assert.ok(modalLookup > guard, 'table routing must happen before opening the drilldown modal');
    assert.match(analytics, /within_180:\s*\['lease_window', '180'\]/);
    assert.match(analytics, /window\.applyInfrastructureFilter\(scope, selected\[0\], selected\[1\], root\.data\('ownership-scope'\)/);
    assert.match(analytics, /params\.set\('ownership_scope', ownershipScope\)/);
    assert.doesNotMatch(analytics, /Status Dokumen Terbanyak/);
    assert.doesNotMatch(analytics, /infra-priority-table/);
    assert.match(analytics, /infra-priority-trigger/);
    assert.match(analytics, /'priority',\s*'1'/);
});

test('Infrastructure tables expose the operational dataset columns', () => {
    const sewa = read('resources/views/infrastruktur/sewa-lahan/index.blade.php');
    const combat = read('resources/views/infrastruktur/combat/index.blade.php');

    for (const label of [
        'Status Site', 'Area', 'NOP', 'Ownership', 'Vendor', 'Periode Awal Baru',
        'Periode Akhir Baru', 'Masa Sewa', 'Harga Baru / Tahun', 'Total Harga Existing', 'Process Aging',
    ]) {
        assert.ok(sewa.includes(`<th>${label}</th>`), `Sewa Lahan is missing ${label}`);
    }

    for (const label of [
        'Renewal Pattern', 'Match NBAE', 'Combat Type', 'Status Combat', 'Kabupaten',
        'Status Masa Sewa', 'PKS Status', 'Aging Days', 'Tindak Lanjut', 'Prioritas',
    ]) {
        assert.ok(combat.includes(`<th>${label}</th>`), `Combat is missing ${label}`);
    }
});

test('infrastructure source formulas are replaced by derived display fields', () => {
    const sewa = read('resources/views/infrastruktur/sewa-lahan/index.blade.php');
    const combat = read('resources/views/infrastruktur/combat/index.blade.php');

    assert.match(sewa, /data: 'lease_duration'/);
    assert.match(sewa, /data: 'process_aging_days'/);
    assert.match(combat, /data: 'lease_duration'/);
    assert.match(combat, /data: 'pks_status_label'/);
    assert.match(combat, /data: 'process_aging_days'/);
    assert.match(combat, /data: 'next_action_label'/);
    assert.match(combat, /data: 'priority_label'/);
    assert.match(sewa, /startsWith\('='\)/);
    assert.match(combat, /startsWith\('='\)/);
});

test('main-module filters opt into the shared compact drawer', () => {
    const expectedViews = [
        'resources/views/dashboard/master.blade.php',
        'resources/views/dashboard/index.blade.php',
        'resources/views/pnl/index.blade.php',
        'resources/views/infrastruktur/sewa-lahan/index.blade.php',
        'resources/views/infrastruktur/combat/index.blade.php',
        'resources/views/electricity/centralized/listrik-pln/index.blade.php',
        'resources/views/electricity/centralized/payment/index.blade.php',
        'resources/views/electricity/inbuilding/payment/index.blade.php',
        'resources/views/po-varcost/index.blade.php',
        'resources/views/document-circulation/index.blade.php',
        'resources/views/data-potensi/data-site/index.blade.php',
    ];

    for (const view of expectedViews) {
        assert.match(read(view), /data-simaster-filter-panel="[^"]+"/, `${view} is not connected to the drawer`);
    }

    const layout = read('resources/views/layouts/app.blade.php');
    assert.match(layout, /querySelectorAll\('\[data-simaster-filter-panel\]'\)/);
    assert.match(layout, /appendChild\(source\)/, 'the original controls must be moved so existing handlers survive');
    assert.match(layout, /const periodPicker = source\.querySelector\('\.simaster-filter-wrapper'\)/);
    assert.match(layout, /periodMenu\.classList\.add\('simaster-filter-context-menu'\)/);
    assert.match(layout, /trigger\.className = 'simaster-context-filter-trigger'/);
    assert.match(layout, /popupBody\.appendChild\(periodPicker\)/, 'period/NOP picker must open inside the compact popup');
    assert.match(layout, /periodMenu\) periodMenu\.classList\.add\('show'\)/, 'existing period picker must be shown only when Filter is opened');

    const filtersCss = read('public/css/simaster.css');
    assert.match(filtersCss, /\.simaster-filter-context-menu\s*\{\s*max-height: min\(72vh, 620px\)/);
    assert.match(filtersCss, /\.simaster-filter-drawer \.simaster-filter-wrapper > \.simaster-filter-btn\s*\{\s*display: none !important/);
    assert.doesNotMatch(filtersCss, /\.simaster-filter-drawer\s*\{[\s\S]*?height: 100%/);
});

test('changed inline browser scripts remain valid JavaScript after Blade values are rendered', () => {
    const bladeToJavaScript = source => source
        .replace(/@json\(route\('[^']+'\)\)/g, '"/generated-url"')
        .replace(/@json\(\\App\\Support\\LeaseStatus::[A-Z0-9_]+\)/g, '"status"')
        .replace(/\{\{\s*auth\(\)->user\(\)->hasRole\('admin'\)\s*\?\s*'true'\s*:\s*'false'\s*\}\}/g, 'true');
    const inlineScripts = file => [...read(file).matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)]
        .map(match => match[1])
        .filter(source => source.trim());

    for (const file of [
        'resources/views/infrastruktur/partials/analytics.blade.php',
        'resources/views/infrastruktur/sewa-lahan/index.blade.php',
        'resources/views/infrastruktur/combat/index.blade.php',
    ]) {
        for (const source of inlineScripts(file)) {
            assert.doesNotThrow(() => new vm.Script(bladeToJavaScript(source)), `${file} contains invalid JavaScript`);
        }
    }

    const layout = read('resources/views/layouts/app.blade.php');
    const drawerScript = inlineScripts('resources/views/layouts/app.blade.php')
        .find(source => source.includes("querySelectorAll('[data-simaster-filter-panel]')"));
    assert.ok(drawerScript, 'shared drawer script is missing');
    assert.doesNotThrow(() => new vm.Script(drawerScript), 'shared drawer script contains invalid JavaScript');
    assert.ok(layout.includes('simaster-context-filter-trigger'));
});
