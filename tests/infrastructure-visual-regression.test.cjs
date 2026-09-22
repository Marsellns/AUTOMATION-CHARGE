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

test('infrastructure summary cards use contextual inline icons', () => {
    const dashboard = read('resources/views/infrastruktur/index.blade.php');
    const analytics = read('resources/views/infrastruktur/partials/analytics.blade.php');
    const css = read('public/css/simaster.css');

    assert.match(dashboard, /infra-summary-card/);
    assert.match(dashboard, /infra-summary-icon/);
    assert.match(dashboard, /'icon' => 'tower'/);
    assert.match(dashboard, /'icon' => 'check'/);
    assert.match(dashboard, /'icon' => 'contract'/);
    assert.match(dashboard, /'icon' => 'alert'/);
    assert.match(dashboard, /'icon' => 'offline'/);
    assert.doesNotMatch(dashboard, /module-navigation/);
    assert.match(analytics, /infra-card-link infra-summary-card/);
    assert.match(analytics, /infra-card-risk_value/);
    assert.match(analytics, /'icon' => 'tower'/);
    assert.match(analytics, /'icon' => 'offline'/);
    assert.match(css, /\.infra-summary-card-body\s*\{/);
    assert.match(css, /\.infra-summary-card\.border-info \.infra-summary-icon/);
});

test('dashboard metrics and anomaly distribution use database-derived values', () => {
    const controller = read('app/Http/Controllers/DashboardMasterController.php');
    const dashboard = read('resources/views/dashboard/master.blade.php');

    assert.match(controller, /\$moduleDataComposition/);
    assert.match(controller, /ListrikInbuilding::count\(\)/);
    assert.match(controller, /\$normalPlnCount \+ \$normalIbcCount \+ \$normalPnlCount/);
    assert.doesNotMatch(controller, /'metric_val'\s*=>\s*'94\.2%'/);
    assert.doesNotMatch(dashboard, /99\.9% Optimal|width: 78%;|y: 4282/);
    assert.match(dashboard, /moduleDataComposition\['infrastructure'\]\['percentage'\]/);
    assert.match(dashboard, /const data = anomalies\?\.distribution \|\| \[\];/);
});

test('infrastructure only retains distinct, balanced chart pairs', () => {
    const analytics = read('resources/views/infrastruktur/partials/analytics.blade.php');
    const dashboard = read('resources/views/infrastruktur/index.blade.php');
    const css = read('public/css/simaster.css');

    assert.match(dashboard, /infra-overview-chart-grid/);
    assert.match(analytics, /\['id' => 'aging'[\s\S]*?\['id' => 'geography'/);
    assert.doesNotMatch(analytics, /\['id' => 'geography'[^\n]*'wide'/);
    assert.doesNotMatch(dashboard, /infra-source-chart|infra-contract-chart/);
    assert.doesNotMatch(analytics, /Sumber Data Site|Nilai Kontrak Berdasarkan Sumber Dataset|Status Masa Sewa \(berdasarkan tanggal akhir\)/);
    assert.doesNotMatch(analytics, /\['source', 'sources', 'pie'\]|\['contract-source'|\['lease-status'/);
    assert.match(analytics, /\['id' => 'health'[\s\S]*?\['id' => 'pipeline'[\s\S]*?'wide_on_overview' => true/);
    assert.match(analytics, /wide_on_overview'\]\) && \$scope === 'all'/);
    assert.match(analytics, /showInLegend: true/);
    assert.match(analytics, /infrastructurePieLabelOptions/);
    assert.match(analytics, /labelFormatter: function \(\)/);
    assert.match(analytics, /infrastructureValueLabelOptions/);
    assert.match(analytics, /allowOverlap: false/);
    assert.match(css, /\.infra-overview-chart\s*\{\s*height: 320px/);
});

test('infrastructure shows row totals and moves performance into site details', () => {
    const dashboard = read('resources/views/infrastruktur/index.blade.php');
    const analytics = read('resources/views/infrastruktur/partials/analytics.blade.php');
    const controller = read('app/Http/Controllers/InfrastructureDashboardController.php');
    const sewa = read('resources/views/infrastruktur/sewa-lahan/index.blade.php');
    const combat = read('resources/views/infrastruktur/combat/index.blade.php');

    assert.match(controller, /'total' => \$all->count\(\)/);
    assert.match(controller, /'unique_sites' => \$allSites->count\(\)/);
    assert.match(dashboard, /data-filter-field="\{\{ \$card\['id'\] === 'total' \? 'all_records'/);
    assert.match(analytics, /data-infra-performance-year/);
    assert.match(analytics, /data-infra-performance-month/);
    assert.match(analytics, /renderInfrastructureSitePerformance/);
    assert.doesNotMatch(dashboard, /infra-performance-chart/);
    assert.doesNotMatch(analytics, /\['id' => 'performance'/);
    assert.match(sewa, /data-site-performance/);
    assert.match(combat, /data-site-performance/);
});
