const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const view = fs.readFileSync(
    path.join(__dirname, '..', 'resources/views/pnl/index.blade.php'),
    'utf8'
);

test('PnL highlight cards route the selected category to the existing filtered table', () => {
    assert.match(view, /id="pnl-highlight-profit"/);
    assert.match(view, /id="pnl-highlight-loss"/);
    assert.match(view, /aria-controls="pnl-table"/);
    assert.match(view, /function showHighlightInTable\(\$card\)/);
    assert.match(view, /\$\('#filter-status'\)\.val\(''\)/);
    assert.match(view, /table\.columns\(\)\.search\(''\)/);
    assert.match(view, /table\.column\(1\)\.search\(siteId\)/);
    assert.match(view, /function clearHighlightSiteFilter\(\)/);
    assert.match(view, /scrollIntoView\(\{ behavior: 'smooth', block: 'start' \}\)/);
    assert.match(view, /data: getChartFilters\(\)/);
    assert.doesNotMatch(view, /Klik untuk tampilkan di tabel/);
});
