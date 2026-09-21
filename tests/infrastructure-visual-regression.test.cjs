const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('equipment relocation uses self-contained SVG icons', () => {
    const view = read('resources/views/rru/index.blade.php');
    const script = read('public/assets/js/equipment-relocation.js');

    assert.doesNotMatch(view, /fa-solid/);
    assert.doesNotMatch(script, /fa-solid/);
    assert.match(view, /class="er-icon"/);
    assert.match(script, /class="er-icon"/);
});

test('infrastructure charts explicitly follow the application theme', () => {
    const analytics = read('resources/views/infrastruktur/partials/analytics.blade.php');
    const dashboard = read('resources/views/infrastruktur/index.blade.php');

    assert.match(analytics, /infrastructureChartThemeOptions/);
    assert.match(analytics, /background = dark \? '#111827' : '#ffffff'/);
    assert.match(analytics, /simaster:theme-changed/);
    assert.match(dashboard, /Highcharts\.merge\(base, options\)/);
});
