<section class="infra-analytics mb-4"
         data-scope="{{ $scope }}"
         data-ownership-scope="{{ $scope === 'sewa' ? (request('ownership_scope') ?: (request('filter_field') === 'ownership' ? request('filter_value') : '')) : '' }}"
         @if ($scope === 'all') id="infrastructure-data" @endif>
    <h2 class="h5 mb-3">Analitik {{ $scope === 'sewa' ? 'Sewa Lahan' : ($scope === 'combat' ? 'Combat' : 'Detail Infrastruktur') }}</h2>
    @if ($scope !== 'all')
    <div class="row g-3 mb-3">
        @foreach ([
            ['id' => 'total', 'label' => 'Total Site', 'class' => 'primary'],
            ['id' => 'active', 'label' => 'Operational / Active', 'class' => 'success'],
            ['id' => 'contract', 'label' => 'Perlu Perhatian', 'class' => 'warning'],
            ['id' => 'without_pks', 'label' => 'Tanpa PKS', 'class' => 'danger'],
            ['id' => 'off_air', 'label' => 'Off Air / Non-Operational', 'class' => 'secondary'],
        ] as $card)
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card h-100 border-{{ $card['class'] }} infra-card-link"
                     data-filter-field="{{ $card['id'] === 'total' ? '' : 'summary_status' }}"
                     data-filter-value="{{ in_array($card['id'], ['active', 'contract', 'without_pks', 'off_air'], true) ? $card['id'] : '' }}"
                     role="link" tabindex="0">
                    <div class="card-body py-2">
                        <div class="small text-body-secondary">{{ $card['label'] }}</div>
                        <div class="fs-4 fw-bold infra-card-{{ $card['id'] }}">—</div>
                        @if ($card['id'] === 'total')
                            <div class="text-body-tertiary infra-card-records-sub" style="font-size: 0.72rem; line-height: 1.1;"></div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card h-100 border-info infra-card-link" data-filter-field="summary_status" data-filter-value="contract"
                 role="link" tabindex="0" title="Buka data yang perlu perhatian"><div class="card-body py-2">
                <div class="small text-body-secondary">Nilai Risiko</div>
                <div class="fs-4 fw-bold infra-card-risk_value">—</div>
            </div></div>
        </div>
    </div>
    @endif
    <div class="infra-alert-panel mb-3" role="status" aria-live="polite">
        <div class="small fw-semibold text-uppercase text-body-secondary mb-2">Peringatan {{ $scope === 'sewa' ? 'Sewa Lahan' : ($scope === 'combat' ? 'Combat' : 'Infrastruktur') }}</div>
        <div class="row g-2 infra-alert-grid">
            <div class="col-md-3 col-6"><button type="button" class="alert alert-danger py-2 mb-0 w-100 text-start infra-alert-toggle" data-alert-key="expired" aria-expanded="false"><div class="small">Masa Sewa Berakhir</div><strong class="infra-alert-expired">0</strong> site <span class="float-end">⌄</span></button></div>
            <div class="col-md-3 col-6"><button type="button" class="alert alert-warning py-2 mb-0 w-100 text-start infra-alert-toggle" data-alert-key="within_90" aria-expanded="false"><div class="small">Berakhir ≤90 Hari</div><strong class="infra-alert-90">0</strong> site <span class="float-end">⌄</span></button></div>
            <div class="col-md-3 col-6"><button type="button" class="alert alert-warning py-2 mb-0 w-100 text-start infra-alert-toggle" data-alert-key="within_180" aria-expanded="false"><div class="small">Berakhir ≤180 Hari</div><strong class="infra-alert-180">0</strong> site <span class="float-end">⌄</span></button></div>
            <div class="col-md-3 col-6"><button type="button" class="alert alert-secondary py-2 mb-0 w-100 text-start infra-alert-toggle" data-alert-key="unknown" aria-expanded="false"><div class="small">Tanpa Tanggal Akhir</div><strong class="infra-alert-unknown">0</strong> site <span class="float-end">⌄</span></button></div>
        </div>
        <div class="infra-alert-details card mt-2 d-none" aria-live="polite">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <strong class="infra-alert-details-title">Detail notifikasi</strong>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-link text-decoration-none infra-alert-close">Tutup</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Site ID</th><th>Nama Site</th><th>Sumber</th><th>Tanggal Akhir</th><th>Sisa Hari</th></tr></thead>
                        <tbody class="infra-alert-details-body"></tbody>
                    </table>
                </div>
                <div class="small text-body-secondary mt-2 infra-alert-details-empty d-none">Tidak ada site pada kategori ini.</div>
            </div>
        </div>
    </div>
    <div class="row g-3">
        @php($charts = [
            ['id' => 'performance', 'title' => 'Performance 2026', 'type' => 'column', 'key' => 'performance', 'wide' => true],
            ['id' => 'status', 'title' => 'Komposisi Status Site', 'type' => 'pie', 'key' => 'status'],
            ['id' => 'owner', 'title' => 'Site Owner', 'type' => 'pie', 'key' => 'owners'],
            ['id' => 'source', 'title' => 'Sumber Data Site', 'type' => 'pie', 'key' => 'sources'],
            ['id' => 'renewal', 'title' => 'Distribusi Tahun Renewal / Justi', 'type' => 'column', 'key' => 'renewal_years'],
            ['id' => 'contract-source', 'title' => 'Nilai Kontrak Berdasarkan Sumber', 'type' => 'column', 'key' => 'contract_by_source', 'wide' => true],
            ['id' => 'pks-status', 'title' => 'PKS Status', 'type' => 'pie', 'key' => 'pks_status'],
            ['id' => 'lease-status', 'title' => 'Status Masa Sewa (berdasarkan tanggal akhir)', 'type' => 'pie', 'key' => 'lease_status'],
            ['id' => 'nop', 'title' => 'NOP Site', 'type' => 'pie', 'key' => 'nop'],
            ['id' => 'vendor', 'title' => 'Vendor', 'type' => 'column', 'key' => 'vendor'],
            ['id' => 'health', 'title' => 'Contract Health / Aging Masa Sewa', 'type' => 'pie', 'key' => 'health'],
            ['id' => 'pipeline', 'title' => 'Renewal Pipeline', 'type' => 'bar', 'key' => 'pipeline'],
            ['id' => 'aging', 'title' => 'Rata-rata Process Aging (hari)', 'type' => 'column', 'key' => 'aging'],
            ['id' => 'geography', 'title' => 'Konsentrasi Area / Kota', 'type' => 'bar', 'key' => 'geography', 'wide' => true],
        ])
        @if ($scope === 'all')
            @php($charts = array_values(array_filter($charts, fn ($chart) => in_array($chart['key'], ['pks_status', 'lease_status', 'nop', 'vendor', 'health', 'pipeline', 'aging', 'geography'], true))))
        @endif
        @foreach ($charts as $chart)
            <div class="{{ !empty($chart['wide']) ? 'col-12' : 'col-xl-6' }}">
                <div class="card h-100">
                    <div class="card-header fw-semibold">{{ $chart['title'] }}</div>
                    <div class="card-body"><div id="infra-{{ $scope }}-{{ $chart['id'] }}" style="height:320px"></div></div>
                </div>
            </div>
        @endforeach
    </div>
    <button type="button" class="card mt-3 w-100 text-start infra-priority-trigger" aria-label="Tampilkan site Prioritas Tindakan">
        <span class="card-body d-flex align-items-center justify-content-between gap-3 py-3">
            <span><strong>Prioritas Tindakan</strong><small class="d-block text-body-secondary">Klik untuk menampilkan site yang memerlukan tindak lanjut.</small></span>
            <span class="badge text-bg-warning rounded-pill fs-6 infra-priority-count">—</span>
        </span>
    </button>
</section>

@once
<div class="modal fade" id="infraDrilldownModal" tabindex="-1" aria-labelledby="infraDrilldownModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable infra-drilldown-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="infraDrilldownModalLabel">Detail Infrastruktur</h5>
                    <div class="small text-body-secondary" id="infra-drilldown-summary">Memuat data...</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-0">
                <div class="alert alert-danger rounded-0 mb-0 d-none" id="infra-drilldown-error"></div>
                <div id="infra-drilldown-list">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="infra-drilldown-table">
                        <thead class="table-light">
                            <tr>
                                <th>Site ID</th><th>Nama Site</th><th>Sumber</th><th>Owner</th>
                                <th>Status Dokumen</th><th>Status Perpanjangan</th><th>Masa Sewa</th>
                                <th>Tanggal Akhir</th><th>Sisa Hari</th><th>Aging Proses</th><th></th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="11" class="text-center text-body-secondary py-4">Memuat data...</td></tr></tbody>
                    </table>
                </div>
                </div>
                <div id="infra-drilldown-row-detail" class="d-none p-3"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-primary btn-sm d-none" id="infra-drilldown-back">Kembali ke tabel</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@push('styles')
<style>
    .infra-alert-toggle { cursor: pointer; border: 1px solid transparent; transition: transform .15s ease, box-shadow .15s ease; }
    .infra-alert-toggle:hover, .infra-alert-toggle[aria-expanded="true"] { transform: translateY(-1px); box-shadow: 0 .2rem .5rem rgba(0,0,0,.08); }
    .infra-alert-details table { font-size: .82rem; }
    .infra-drilldown-dialog { width: 75vw; max-width: 75vw; }
    #infra-drilldown-table { font-size: .82rem; }
    #infra-drilldown-table th { white-space: nowrap; }
    #infra-drilldown-table tbody tr,
    .infra-alert-details-body tr { cursor: pointer; }
    #infra-drilldown-table tbody tr:hover,
    .infra-alert-details-body tr:hover { background: rgba(13,110,253,.08); }
    .infra-priority-trigger { border: 1px solid var(--bs-warning-border-subtle); background: var(--bs-body-bg); }
    .infra-priority-trigger:hover, .infra-priority-trigger:focus-visible { background: var(--bs-warning-bg-subtle); }
    #infra-drilldown-table details summary { cursor: pointer; white-space: nowrap; }
    .infra-drilldown-extra { min-width: 280px; max-width: 460px; white-space: normal; }
    @media (max-width: 992px) {
        .infra-drilldown-dialog { width: 95vw; max-width: 95vw; }
    }
</style>
@endpush
@push('scripts')
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>
<script>
window.infrastructureChartThemeOptions = function (requestedTheme) {
    const theme = requestedTheme || document.documentElement.getAttribute('data-bs-theme') || 'light';
    const dark = theme === 'dark';
    const background = dark ? '#111827' : '#ffffff';
    const text = dark ? '#f1f5f9' : '#0f172a';
    const muted = dark ? '#cbd5e1' : '#64748b';
    const grid = dark ? '#334155' : '#e2e8f0';

    return {
        credits: { enabled: false },
        exporting: { enabled: false },
        chart: {
            backgroundColor: background,
            plotBackgroundColor: background,
            style: { color: text }
        },
        title: { text: null, style: { color: text } },
        subtitle: { style: { color: muted } },
        legend: {
            itemStyle: { color: text },
            itemHoverStyle: { color: dark ? '#ffffff' : '#020617' },
            itemHiddenStyle: { color: dark ? '#64748b' : '#94a3b8' }
        },
        xAxis: {
            lineColor: grid,
            tickColor: grid,
            labels: { style: { color: muted } },
            title: { style: { color: text } }
        },
        yAxis: {
            gridLineColor: grid,
            lineColor: grid,
            tickColor: grid,
            labels: { style: { color: muted } },
            title: { style: { color: text } }
        },
        tooltip: {
            backgroundColor: dark ? '#1f2937' : '#ffffff',
            borderColor: grid,
            style: { color: text }
        },
        plotOptions: {
            series: { dataLabels: { style: { color: text, textOutline: 'none' } } },
            pie: {
                dataLabels: {
                    color: text,
                    connectorColor: muted,
                    style: { color: text, textOutline: 'none' }
                }
            }
        }
    };
};

window.addEventListener('simaster:theme-changed', function (event) {
    if (!window.Highcharts) return;
    const options = window.infrastructureChartThemeOptions(event.detail?.theme);
    Highcharts.charts.filter(Boolean).forEach(function (chart) {
        if (chart.renderTo?.id?.startsWith('infra-')) {
            chart.update(options, true, false);
        }
    });
});

const infrastructureDrilldownEscape = function (value) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'object') {
        try { value = JSON.stringify(value); } catch (e) { value = String(value); }
    }
    return $('<div>').text(String(value)).html();
};

window.__infraDrilldownState = { rows: [], scope: 'all', title: '' };
window.showInfrastructureRowDetail = function (row, scope, title) {
    const detail = $('#infra-drilldown-row-detail');
    const list = $('#infra-drilldown-list');
    const back = $('#infra-drilldown-back');
    if (!detail.length) return;

    const source = row.source || (scope === 'combat' ? 'Combat' : (scope === 'sewa' ? 'Sewa Lahan' : 'Infrastruktur'));
    const sourceBase = source === 'Combat'
        ? @json(route('infrastruktur.combat.index'))
        : @json(route('infrastruktur.sewa-lahan.index'));
    const sourceUrl = `${sourceBase}?filter_field=site_code&filter_value=${encodeURIComponent(row.site_code || '')}&unique_sites=1#infrastructure-data`;
    const entries = [
        ['Site ID', row.site_code], ['Nama Site', row.site_name], ['Sumber', source],
        ['Owner', row.owner], ['Tahun', row.tahun], ['NOP', row.nop], ['Vendor / TP', row.vendor],
        ['Status Dokumen', row.status_dokumen], ['Status Perpanjangan', row.status_perpanjangan],
        ['Status Masa Sewa', row.status_masa_sewa], ['Tanggal Akhir', row.end_date],
        ['Sisa Hari', row.days_remaining === null || row.days_remaining === undefined ? '—' : row.days_remaining],
        ['Mulai Tahap Proses', row.process_started_at], ['Aging Proses (hari)', row.process_aging_days],
        ['No PKS Baru', row.no_pks_baru], ['Total Harga Baru', row.total_harga_baru]
    ].concat(Object.entries(row.source_details || {}).map(([key, value]) => [key.replaceAll('_', ' '), value]));
    const cells = entries
        .filter(([, value]) => value !== null && value !== undefined && value !== '')
        .map(([key, value]) => `<tr><th class="w-25">${infrastructureDrilldownEscape(key)}</th><td>${infrastructureDrilldownEscape(value)}</td></tr>`)
        .join('');
    detail.html(`<div class="d-flex justify-content-between align-items-center mb-3"><h6 class="mb-0">Informasi lengkap ${infrastructureDrilldownEscape(row.site_code || '')}</h6><a class="btn btn-sm btn-outline-secondary" href="${sourceUrl}">Buka sumber data</a></div><div class="table-responsive"><table class="table table-bordered table-sm mb-0">${cells}</table></div>`);
    list.addClass('d-none');
    detail.removeClass('d-none');
    back.removeClass('d-none');
    $('#infraDrilldownModalLabel').text(`Detail ${source} — ${row.site_code || title || ''}`);
};

$(document).on('click', '#infra-drilldown-back', function () {
    $('#infra-drilldown-row-detail').addClass('d-none').empty();
    $('#infra-drilldown-list').removeClass('d-none');
    $(this).addClass('d-none');
    const state = window.__infraDrilldownState || {};
    $('#infraDrilldownModalLabel').text(`Detail ${state.scope === 'combat' ? 'Combat' : (state.scope === 'sewa' ? 'Sewa Lahan' : 'Infrastruktur')} — ${state.title || 'Semua Site'}`);
});

window.openInfrastructureDrilldown = function (scope, filterField, filterValue, title, ownershipScope = '') {
    // Halaman Sewa Lahan dan Combat sudah memiliki tabel lengkap di bawah
    // analitik. Semua pilihan kartu/diagram harus memakai tabel itu, bukan
    // membuat daftar kedua di modal.
    if (scope === 'sewa' || scope === 'combat') {
        window.applyInfrastructureFilter(scope, filterField, filterValue, ownershipScope);
        return;
    }

    const modalElement = document.getElementById('infraDrilldownModal');
    if (!modalElement || typeof bootstrap === 'undefined') {
        window.applyInfrastructureFilter(scope, filterField, filterValue);
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const body = $('#infra-drilldown-table tbody');
    $('#infra-drilldown-list').removeClass('d-none');
    $('#infra-drilldown-row-detail').addClass('d-none').empty();
    $('#infra-drilldown-back').addClass('d-none');
    const summary = $('#infra-drilldown-summary');
    const error = $('#infra-drilldown-error');
    const selectedTitle = title || (filterValue ? `${filterValue}` : 'Semua Site');
    $('#infraDrilldownModalLabel').text(`Detail ${scope === 'combat' ? 'Combat' : (scope === 'sewa' ? 'Sewa Lahan' : 'Infrastruktur')} — ${selectedTitle}`);
    summary.text('Memuat data...');
    error.addClass('d-none').text('');
    body.html('<tr><td colspan="11" class="text-center text-body-secondary py-4"><span class="spinner-border spinner-border-sm me-2"></span>Memuat data...</td></tr>');
    modal.show();

    const params = new URLSearchParams({ scope: scope || 'all' });
    if (filterField && filterValue !== undefined && filterValue !== null && filterValue !== '') {
        params.set('filter_field', filterField);
        params.set('filter_value', filterValue);
    }
    if (ownershipScope === 'Telkomsel' || ownershipScope === 'TP') {
        params.set('ownership_scope', ownershipScope);
    }

    fetch(@json(route('infrastruktur.dashboard.details')) + '?' + params.toString(), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
        .then(response => response.ok ? response.json() : response.json().then(payload => Promise.reject(payload)))
        .then(payload => {
            const rows = payload.data || [];
            window.__infraDrilldownState = { rows, scope: scope || 'all', title: selectedTitle };
            const sourceSummary = Object.entries(payload.source_counts || {})
                .map(([source, count]) => `${source}: ${new Intl.NumberFormat('id-ID').format(count)}`)
                .join(' · ');
            summary.text(`${new Intl.NumberFormat('id-ID').format(payload.count || rows.length)} site unik${sourceSummary ? ` (${sourceSummary})` : ''}`);

            if (!rows.length) {
                body.html('<tr><td colspan="11" class="text-center text-body-secondary py-4">Tidak ada data untuk pilihan ini.</td></tr>');
                return;
            }

            const extraDetails = function (row) {
                const entries = [
                    ['Tahun', row.tahun],
                    ['NOP', row.nop],
                    ['Vendor / TP', row.vendor],
                    ['No PKS Baru', row.no_pks_baru],
                    ['Total Harga Baru', row.total_harga_baru],
                    ['Mulai Tahap Proses', row.process_started_at],
                    ['Aging Proses (hari)', row.process_aging_days]
                ].concat(Object.entries(row.source_details || {}))
                    .filter(([key, value]) => value !== null && value !== '' && !/^(rev|cost|pnl|revenue|margin|profit|tracy|payload|jan|feb|mar|apr|mei|may|jun|jul|aug|agu|sep|okt|oct|nov|des|dec)|_202[0-9]|-(25|26)/i.test(key));
                if (!entries.length) return '';
                const cells = entries.map(([key, value]) => `<div><span class="text-body-secondary">${infrastructureDrilldownEscape(key.replaceAll('_', ' '))}:</span> ${infrastructureDrilldownEscape(value)}</div>`).join('');
                return `<details class="infra-drilldown-extra mt-1"><summary class="text-primary">Detail kolom dataset</summary><div class="border rounded p-2 mt-1 bg-body-tertiary">${cells}</div></details>`;
            };
            body.html(rows.map((row, rowIndex) => {
                const days = row.days_remaining === null || row.days_remaining === undefined
                    ? '—'
                    : (row.days_remaining < 0 ? `${Math.abs(row.days_remaining)} hari lewat` : `${row.days_remaining} hari`);
                return `<tr class="infra-drilldown-row" data-row-index="${rowIndex}">
                    <td class="fw-semibold">${infrastructureDrilldownEscape(row.site_code)}</td>
                    <td>${infrastructureDrilldownEscape(row.site_name)}</td>
                    <td>${infrastructureDrilldownEscape(row.source)}</td>
                    <td>${infrastructureDrilldownEscape(row.owner)}</td>
                    <td>${infrastructureDrilldownEscape(row.status_dokumen)}</td>
                    <td>${infrastructureDrilldownEscape(row.status_perpanjangan)}</td>
                    <td>${infrastructureDrilldownEscape(row.status_masa_sewa)}</td>
                    <td>${infrastructureDrilldownEscape(row.end_date)}</td>
                    <td>${infrastructureDrilldownEscape(days)}</td>
                    <td>${infrastructureDrilldownEscape(row.process_aging_days === null || row.process_aging_days === undefined ? '—' : `${row.process_aging_days} hari`)}</td>
                    <td>${extraDetails(row)}</td>
                </tr>`;
            }).join(''));
            body.off('click.infraRow').on('click.infraRow', 'tr.infra-drilldown-row', function () {
                const row = window.__infraDrilldownState.rows[Number($(this).data('row-index'))];
                if (row) window.showInfrastructureRowDetail(row, scope || 'all', selectedTitle);
            });
            if (filterField === 'site_code' && rows.length === 1) {
                window.showInfrastructureRowDetail(rows[0], scope || 'all', selectedTitle);
            }
        })
        .catch(payload => {
            const message = payload?.message || 'Detail data belum dapat dimuat.';
            error.removeClass('d-none').text(message);
            summary.text('Gagal memuat data');
            body.html('<tr><td colspan="11" class="text-center text-body-secondary py-4">Tidak ada data yang dapat ditampilkan.</td></tr>');
        });
};

window.scrollToInfrastructureTable = function () {
    const target = document.getElementById('infrastructure-data')
        || document.getElementById('sewa-table-container')
        || document.getElementById('combat-table-container')
        || document.querySelector('#sewa-table, #combat-table')?.closest('.card');

    if (!target) return;

    const header = document.querySelector('.app-header');
    const headerHeight = header ? header.offsetHeight : 70;
    const elementTop = target.getBoundingClientRect().top + window.pageYOffset;
    const offsetPosition = Math.max(0, elementTop - headerHeight - 16);

    window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth'
    });
};

function dashboardTarget(scope, filterField, filterValue, ownershipScope = '') {
    const infrastructureUrl = @json(route('infrastruktur.index'));
    const sewaUrl = @json(route('infrastruktur.sewa-lahan.index'));
    const combatUrl = @json(route('infrastruktur.combat.index'));
    let target = scope === 'combat' ? combatUrl : sewaUrl;

    if (filterField === 'tahun') {
        const params = new URLSearchParams({ tahun: filterValue, unique_sites: '1' });
        if (scope === 'sewa' && (ownershipScope === 'Telkomsel' || ownershipScope === 'TP')) {
            params.set('ownership_scope', ownershipScope);
        }
        return (scope === 'combat' ? combatUrl : sewaUrl) + '?' + params;
    }
    if (scope === 'all' && filterField === 'source') {
        target = filterValue === 'Combat' ? combatUrl : sewaUrl;
        filterField = '';
        filterValue = '';
    } else if (filterField === 'source') {
        filterField = '';
        filterValue = '';
    } else if (scope === 'all' && filterField === 'ownership') {
        target = sewaUrl;
    }

    const params = new URLSearchParams();
    if (filterField && filterValue) {
        params.set('filter_field', filterField);
        params.set('filter_value', filterValue);
    }
    if (scope === 'sewa' && filterField !== 'ownership' && (ownershipScope === 'Telkomsel' || ownershipScope === 'TP')) {
        params.set('ownership_scope', ownershipScope);
    }
    // Diagram counts are based on unique Site ID.  Preserve that same
    // definition when opening a table from any chart/card.
    params.set('unique_sites', '1');
    return target + (params.toString() ? '?' + params.toString() : '');
}

window.applyInfrastructureFilter = function (scope, filterField, filterValue, ownershipScope = '') {
    const target = dashboardTarget(scope, filterField, filterValue, ownershipScope);
    const hasTable = document.getElementById('infrastructure-data') !== null;
    const isSameScope = (scope === 'sewa' && window.location.pathname.includes('sewa-lahan')) ||
                        (scope === 'combat' && window.location.pathname.includes('combat'));

    if (hasTable && isSameScope) {
        const url = new URL(window.location.href);
        const targetUrl = new URL(target, window.location.origin);
        url.search = targetUrl.search;
        window.history.pushState({}, '', url);

        $(document).trigger('infrastructure-filter', [{
            filterField: filterField || '',
            filterValue: filterValue || ''
        }]);
        // The module table is a chart drill-down and is revealed by the
        // event above. Scroll only after it is visible in the layout.
        window.scrollToInfrastructureTable();
        return;
    }

    // Jika berpindah dari halaman lain (misal dari dashboard utama)
    window.location.href = target + '#infrastructure-data';
};

$(function () {
    $('.infra-analytics').each(function () {
        const root = $(this), scope = root.data('scope');
        const ownershipScope = root.data('ownership-scope') || '';
        const dataParams = { scope };
        if (ownershipScope === 'Telkomsel' || ownershipScope === 'TP') {
            dataParams.ownership_scope = ownershipScope;
        }
        $.getJSON(@json(route('infrastruktur.dashboard.data')), dataParams).done(function (data) {
            Object.entries(data.cards).forEach(([key, value]) => {
                const el = root.find('.infra-card-' + key);
                if (el.length) el.text(key === 'risk_value' ? 'Rp ' + new Intl.NumberFormat('id-ID').format(value) : new Intl.NumberFormat('id-ID').format(value));
            });
            if (data.cards.records) {
                root.find('.infra-card-records-sub').text(`${new Intl.NumberFormat('id-ID').format(data.cards.records)} baris data`);
            }
            const alerts = data.alerts || { expired: [], within_90: [], within_180: [], unknown: [] };
            root[0].__infraAlerts = alerts;
            root.find('.infra-alert-expired').text(new Intl.NumberFormat('id-ID').format((alerts.expired || []).length));
            root.find('.infra-alert-90').text(new Intl.NumberFormat('id-ID').format((alerts.within_90 || []).length));
            root.find('.infra-alert-180').text(new Intl.NumberFormat('id-ID').format((alerts.within_180 || []).length));
            root.find('.infra-alert-unknown').text(new Intl.NumberFormat('id-ID').format((alerts.unknown || []).length));
            const base = window.infrastructureChartThemeOptions();
            const charts = [
                ['performance', 'performance', 'column'], ['status', 'status', 'pie'], ['owner', 'owners', 'pie'],
                ['source', 'sources', 'pie'],
                ['renewal', 'renewal_years', 'column'], ['contract-source', 'contract_by_source', 'column'],
                ['pks-status', 'pks_status', 'pie'], ['lease-status', 'lease_status', 'pie'],
                ['nop', 'nop', 'pie'], ['vendor', 'vendor', 'column'],
                ['health', 'health', 'pie'], ['pipeline', 'pipeline', 'bar'],
                ['aging', 'aging', 'column'], ['geography', 'geography', 'bar']
            ];
            charts.forEach(([id, key, type]) => {
                const chartId = `infra-${scope}-${id}`;
                const container = document.getElementById(chartId);

                // Halaman Dashboard Utama hanya menampilkan analitik lanjutan.
                // Jangan membuat chart untuk kontainer yang sengaja tidak
                // dirender; Highcharts akan melempar error #13 dan menghentikan
                // proses render chart berikutnya.
                if (!container) return;

                const source = data[key] || [];
                const isPerformance = key === 'performance';
                const isAging = key === 'aging';
                const series = isPerformance
                    ? ['revenue', 'cost', 'pnl'].map(name => ({ name: name.toUpperCase(), data: source.map(item => item[name]) }))
                    : [{ name: isAging ? 'Rata-rata hari' : 'Site', data: source.map(point => ({
                        name: point.name, y: point.y,
                        custom: {
                            filterField: point.filter_field,
                            filterValue: point.filter_value,
                            datedCount: point.dated_count,
                            totalCount: point.count
                        }
                    })) }];
                Highcharts.chart(container, Highcharts.merge(base, {
                    chart: { type },
                    xAxis: { type: 'category', categories: isPerformance ? source.map(item => item.label) : undefined },
                    yAxis: { title: { text: isPerformance ? 'Nilai' : (isAging ? 'Hari' : 'Site unik') } },
                    plotOptions: {
                        pie: {
                            cursor: 'pointer',
                            point: {
                                events: {
                                    click: function () {
                                        if (!this.options.custom) return;
                                        window.openInfrastructureDrilldown(scope, this.options.custom.filterField, this.options.custom.filterValue, this.options.custom.filterValue || this.options.custom.filterField, ownershipScope);
                                    }
                                }
                            },
                            dataLabels: {
                                enabled: true,
                                format: '{point.name}: {point.y} ({point.percentage:.1f}%)'
                            }
                        },
                        series: {
                            cursor: isPerformance ? 'default' : 'pointer',
                point: {
                    events: {
                                    click: function () {
                                        if (isPerformance) {
                                            if (scope === 'sewa' || scope === 'combat') {
                                                window.applyInfrastructureFilter(
                                                    scope,
                                                    ownershipScope === 'Telkomsel' || ownershipScope === 'TP' ? 'ownership' : '',
                                                    ownershipScope === 'Telkomsel' || ownershipScope === 'TP' ? ownershipScope : ''
                                                );
                                                return;
                                            }
                                            const target = scope === 'combat'
                                                ? @json(route('infrastruktur.combat.index'))
                                                : (scope === 'sewa' ? @json(route('infrastruktur.sewa-lahan.index')) : @json(route('infrastruktur.index')));
                                            const targetUrl = new URL(target, window.location.origin);
                                            if (ownershipScope === 'Telkomsel' || ownershipScope === 'TP') {
                                                targetUrl.searchParams.set('filter_field', 'ownership');
                                                targetUrl.searchParams.set('filter_value', ownershipScope);
                                            }
                                            window.location.href = targetUrl.pathname + targetUrl.search + '#infrastructure-data';
                                            return;
                                        }
                                        if (!this.options.custom) return;
                                        window.openInfrastructureDrilldown(scope, this.options.custom.filterField, this.options.custom.filterValue, this.options.custom.filterValue || this.options.custom.filterField, ownershipScope);
                                    }
                                }
                            }
                        }
                    },
                    ...(key === 'contract_by_source'
                        ? { tooltip: { pointFormat: '<b>Rp {point.y:,.0f}</b>' } }
                        : (isAging ? {
                            tooltip: {
                                formatter: function () {
                                    const dated = this.point.options.custom?.datedCount || 0;
                                    const total = this.point.options.custom?.totalCount || 0;
                                    const value = this.y === null || this.y === undefined ? 'Tanggal proses belum tersedia' : `<b>${this.y} hari</b>`;
                                    return `${this.key}<br>${value}<br><span style="font-size:11px">${dated} dari ${total} site memiliki tanggal tahap</span>`;
                                }
                            }
                        } : {})),
                    series
                }));
            });

            root.find('.infra-priority-count').text(new Intl.NumberFormat('id-ID').format(data.priority_count || 0));

            // Setelah semua chart selesai digambar di DOM, jika ada filter aktif atau hash, pastikan posisi scroll tepat di tabel
            if (window.location.hash === '#infrastructure-data' || new URLSearchParams(window.location.search).has('filter_field') || new URLSearchParams(window.location.search).has('tahun')) {
                setTimeout(function () {
                    window.scrollToInfrastructureTable();
                }, 200);
            }
        });
    });

    $(document).on('click keydown', '.infra-card-link', function (event) {
        if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        const root = $(this).closest('.infra-analytics');
        window.openInfrastructureDrilldown(
            root.data('scope'),
            $(this).data('filter-field'),
            $(this).data('filter-value'),
            $(this).find('.small').first().text(),
            root.data('ownership-scope') || ''
        );
    });

    $(document).on('click', '.infra-priority-trigger', function () {
        const root = $(this).closest('.infra-analytics');
        window.openInfrastructureDrilldown(
            root.data('scope'),
            'priority',
            '1',
            'Prioritas Tindakan',
            root.data('ownership-scope') || ''
        );
    });

    const alertTitles = {
        expired: 'Site dengan masa sewa sudah berakhir',
        within_90: 'Site yang berakhir dalam ≤90 hari',
        within_180: 'Site yang berakhir dalam ≤180 hari',
        unknown: 'Site tanpa tanggal akhir masa sewa'
    };
    const escapeAlertHtml = value => $('<div>').text(value ?? '').html();
    const renderAlertDetails = function (root, key) {
        const panel = root.find('.infra-alert-details');
        const body = panel.find('.infra-alert-details-body');
        const items = root[0].__infraAlerts?.[key] || [];
        root.find('.infra-alert-toggle').attr('aria-expanded', 'false');
        root.find(`.infra-alert-toggle[data-alert-key="${key}"]`).attr('aria-expanded', 'true');
        panel.removeClass('d-none');
        panel.find('.infra-alert-details-title').text(`${alertTitles[key] || 'Detail notifikasi'} (${items.length} site)`);
        panel.find('.infra-alert-details-empty').toggleClass('d-none', items.length > 0);
        body.html(items.map(item => {
            const days = item.days_remaining === null || item.days_remaining === undefined
                ? '—'
                : (item.days_remaining < 0 ? `${Math.abs(item.days_remaining)} hari lewat` : `${item.days_remaining} hari`);
            return `<tr class="infra-alert-row" data-site="${escapeAlertHtml(item.site_code || '')}" data-source="${escapeAlertHtml(item.source || '')}">
                <td class="fw-semibold">${escapeAlertHtml(item.site_code || '—')}</td>
                <td>${escapeAlertHtml(item.site_name || '—')}</td>
                <td>${escapeAlertHtml(item.source || '—')}</td>
                <td>${escapeAlertHtml(item.end_date || '—')}</td>
                <td>${escapeAlertHtml(days)}</td>
            </tr>`;
        }).join(''));
        body.off('click.infraAlertRow').on('click.infraAlertRow', 'tr.infra-alert-row', function () {
            const rowSource = $(this).data('source') === 'Combat' ? 'combat' : 'sewa';
            window.openInfrastructureDrilldown(rowSource, 'site_code', $(this).data('site'), $(this).data('site'), root.data('ownership-scope') || '');
        });
        if (items.length) {
            setTimeout(() => panel[0]?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 0);
        }
    };
    $(document).on('click', '.infra-alert-toggle', function () {
        const root = $(this).closest('.infra-analytics');
        const key = $(this).data('alert-key');
        const scope = root.data('scope');

        if (scope === 'sewa' || scope === 'combat') {
            const tableFilters = {
                expired: ['status_masa_sewa', @json(\App\Support\LeaseStatus::EXPIRED)],
                within_90: ['status_masa_sewa', @json(\App\Support\LeaseStatus::WITHIN_90_DAYS)],
                within_180: ['lease_window', '180'],
                unknown: ['status_masa_sewa', @json(\App\Support\LeaseStatus::NO_END_DATE)]
            };
            const selected = tableFilters[key] || ['', ''];
            window.applyInfrastructureFilter(scope, selected[0], selected[1], root.data('ownership-scope') || '');
            return;
        }

        const panel = root.find('.infra-alert-details');
        if (!panel.hasClass('d-none') && panel.data('alert-key') === key) {
            panel.addClass('d-none').removeData('alert-key');
            root.find('.infra-alert-toggle').attr('aria-expanded', 'false');
            return;
        }
        panel.data('alert-key', key);
        renderAlertDetails(root, key);
    });
    $(document).on('click', '.infra-alert-close', function () {
        const root = $(this).closest('.infra-analytics');
        root.find('.infra-alert-details').addClass('d-none').removeData('alert-key');
        root.find('.infra-alert-toggle').attr('aria-expanded', 'false');
    });
    if (window.location.hash === '#infrastructure-data' || new URLSearchParams(window.location.search).has('filter_field') || new URLSearchParams(window.location.search).has('tahun')) {
        setTimeout(function () {
            window.scrollToInfrastructureTable();
        }, 150);
    }
});
</script>
@endpush
@endonce
