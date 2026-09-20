@extends('layouts.app')

@section('title', 'Profit & Loss — SIMASTER')

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    @if ($errors->has('pnl_file'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ $errors->first('pnl_file') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3 page-hero">
        <div>
            <h1 class="h4 mb-1">Profit & Loss (P & L) Data</h1>
            <p class="text-body-secondary mb-0 small">
                Daftar data finansial dan status performa site per periode (Revenue, Cost, dan Profit/Loss).
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('pnl.upload') }}" class="btn btn-outline-brand btn-sm">Upload Excel</a>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="card mb-3" data-simaster-filter-panel="Filter Profit &amp; Loss">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
            {{-- Page Size --}}
            <div class="d-flex align-items-center gap-2">
                <label for="page-size" class="form-label mb-0 small fw-semibold text-nowrap">Show:</label>
                <select id="page-size" class="form-select form-select-sm" style="width:auto">
                    @foreach ([10, 20, 40, 80, 100, 5000] as $size)
                        <option value="{{ $size }}" @selected($size === 10)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Periode --}}
            <div class="d-flex align-items-center gap-2">
                <label for="filter-year" class="form-label mb-0 small fw-semibold text-nowrap">Pilih Tahun:</label>
                <select id="filter-year" class="form-select form-select-sm" style="min-width: 105px;"></select>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label for="filter-month" class="form-label mb-0 small fw-semibold text-nowrap">Pilih Bulan:</label>
                <select id="filter-month" class="form-select form-select-sm" style="min-width: 135px;"></select>
            </div>

            {{-- Filter NOP --}}
            <div class="d-flex align-items-center gap-2">
                <label for="filter-nop" class="form-label mb-0 small fw-semibold text-nowrap">NOP:</label>
                <select id="filter-nop" class="form-select form-select-sm" style="min-width: 170px;">
                    <option value="">Semua NOP</option>
                    @foreach ($nops as $nop)
                        <option value="{{ $nop }}" @selected($nop === $selectedNop)>{{ $nop }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Status (Semua / Profit / Loss / Tidak Aktif) --}}
            <div class="d-flex align-items-center gap-2">
                <label for="filter-status" class="form-label mb-0 small fw-semibold text-nowrap">Status:</label>
                <select id="filter-status" class="form-select form-select-sm" style="min-width: 140px;">
                    <option value="" @selected(empty($selectedStatus))>Semua Status</option>
                    <option value="Profit" @selected($selectedStatus === 'Profit')>Profit</option>
                    <option value="Loss" @selected($selectedStatus === 'Loss')>Loss</option>
                    <option value="TidakAktif" @selected($selectedStatus === 'TidakAktif' || $selectedStatus === 'Tidak Aktif')>Tidak Aktif</option>
                </select>
            </div>

            {{-- Download Excel --}}
            <a href="{{ route('pnl.export-excel') }}" id="btn-export" class="btn btn-sm btn-outline-brand ms-auto">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-1"><path d="M.5 9.9a.5.5 0 0 1 .5.1v2.5A1.5 1.5 0 0 0 2.5 14h11a1.5 1.5 0 0 0 1.5-1.5V10a.5.5 0 0 1 1 0v2.5a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 0 12.5V10a.5.5 0 0 1 .5-.1"/><path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708z"/></svg>
                Download Excel
            </a>
        </div>
    </div>

    {{-- Main PnL Table --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h6 mb-0">Perbandingan Revenue, Cost, dan Net PnL</h2>
                    <span class="text-body-secondary small">Batang Net PnL di atas garis nol = profit, di bawah garis nol = loss (satuan: Miliar Rp)</span>
                </div>
                <div class="card-body">
                    <div id="pnl-growth-chart" style="height: 320px; width: 100%;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h6 mb-0">Data by Category</h2>
                </div>
                <div class="card-body">
                    <div id="pnl-category-chart" style="height: 270px; width: 100%;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="pnl-table" class="display align-middle text-nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Site ID</th>
                        <th>Site Name</th>
                        <th>NOP</th>
                        <th>Revenue</th>
                        <th>Cost</th>
                        <th>Profit / Loss</th>
                        <th>Status</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    {{-- Modal Detail Site & Histori Bulanan --}}
    <div class="modal fade" id="siteDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Rincian Histori Site — <span id="modal-site-id" class="fw-bold"></span>
                        <span id="modal-site-name" class="text-body-secondary fw-normal fs-6 ms-1"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body" id="modal-site-body">
                    <div class="text-center text-body-secondary py-4">Memuat histori data...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="metricDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="metric-detail-title">Detail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody id="metric-detail-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    #pnl-table tbody tr { cursor: pointer; }
</style>
@endpush

@push('scripts')
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
$(function () {
    const dataUrl = @json(route('pnl.data'));
    const chartApiUrl = @json(route('dashboard.chart-data'));
    const historyBaseUrl = @json(url('pnl/site-history'));
    const exportBaseUrl = @json(route('pnl.export-excel'));
    const availablePeriods = @json($periods);
    const initialPeriod = @json($selectedPeriod).split('-').map(Number);
    const initialAllMonths = @json($selectedAllMonths ?? false);
    const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    let growthChart = null;
    let categoryChart = null;
    let chartRequest = null;

    function populateMonths(tahun, selectedBulan) {
        const months = availablePeriods
            .filter((period) => Number(period.tahun) === Number(tahun))
            .map((period) => Number(period.bulan))
            .sort((a, b) => a - b);
        const $month = $('#filter-month').empty();
        // Tambahkan pilihan Semua Bulan
        $month.append($('<option>').val('all').text('Semua Bulan'));
        months.forEach((bulan) => $month.append($('<option>').val(bulan).text(monthNames[bulan - 1])));
        // Pre-select: 'all' jika selectedBulan === 0 atau 'all', else nilai bulan
        if (selectedBulan === 0 || selectedBulan === 'all' || selectedBulan === null) {
            $month.val('all');
        } else {
            $month.val(months.includes(Number(selectedBulan)) ? Number(selectedBulan) : months[months.length - 1]);
        }
    }

    function initPeriodFilters() {
        const years = [...new Set(availablePeriods.map((period) => Number(period.tahun)))].sort((a, b) => b - a);
        const $year = $('#filter-year').empty();
        years.forEach((tahun) => $year.append($('<option>').val(tahun).text(tahun)));
        $year.val(initialPeriod[1]);
        // initialPeriod[0] === 0 berarti semua bulan
        populateMonths(initialPeriod[1], initialAllMonths ? 0 : initialPeriod[0]);
        $('#filter-year, #filter-month').trigger('simaster:period-sync');
    }

    initPeriodFilters();

    const columns = [
        { data: 'DT_RowIndex', name: 'no', orderable: false, searchable: false },
        { data: 'site_id', name: 'site_id' },
        { data: 'site_name', name: 'site_name' },
        { data: 'nop', name: 'nop', defaultContent: '-' },
        { data: 'revenue', name: 'revenue', className: 'text-end' },
        { data: 'cost', name: 'cost', className: 'text-end' },
        { data: 'profit_loss', name: 'profit_loss', className: 'text-end' },
        { data: 'status_badge', name: 'status_badge', className: 'text-center' },
    ];

    const table = new DataTable('#pnl-table', {
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: {
            url: dataUrl,
            data: function (d) {
                const bulanVal = $('#filter-month').val();
                // Kirim 'all' jika Semua Bulan dipilih, else kirim nilai bulan
                d.bulan = (bulanVal === 'all') ? 'all' : bulanVal;
                d.tahun = $('#filter-year').val();
                d.nop = $('#filter-nop').val();
                d.status = $('#filter-status').val();
            }
        },
        columns: columns,
        order: [],
        pageLength: 10,
    });

    let pnlModalChart = null;

    function updateExportUrl() {
        const status = $('#filter-status').val();
        const nop = $('#filter-nop').val();
        const bulanVal = $('#filter-month').val();

        const params = new URLSearchParams();
        if (bulanVal && bulanVal !== 'all') params.append('bulan', bulanVal);
        else if (bulanVal === 'all') params.append('bulan', 'all');
        params.append('tahun', $('#filter-year').val());
        if (nop) params.append('nop', nop);
        if (status) params.append('status', status);

        $('#btn-export').attr('href', exportBaseUrl + '?' + params.toString());
    }

    updateExportUrl();

    function getChartFilters() {
        const bulan = $('#filter-month').val();

        return {
            tahun: $('#filter-year').val(),
            bulan: bulan === 'all' ? 'all' : bulan,
            nop: $('#filter-nop').val()
        };
    }

    function renderPnlCharts(res) {
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const textColor = isDark ? '#CBD5E1' : '#334155';
        const gridColor = isDark ? '#1E293B' : '#F1F5F9';
        const tooltipBg = isDark ? '#0F172A' : '#FFFFFF';
        const tooltipText = isDark ? '#F8FAFC' : '#1E293B';
        const pnl = res.pnl || {};
        const formatMiliar = (value) => `Rp ${Number(value || 0).toFixed(2)} M`;

        if (growthChart) growthChart.destroy();
        growthChart = Highcharts.chart('pnl-growth-chart', {
            chart: { type: 'column', backgroundColor: 'transparent' },
            title: { text: null },
            credits: { enabled: false },
            xAxis: {
                categories: pnl.labels || [],
                labels: { style: { color: textColor, fontSize: '11px' } },
                lineColor: gridColor
            },
            yAxis: {
                title: { text: 'Miliar Rupiah (Rp)', style: { color: textColor, fontSize: '11px' } },
                gridLineColor: gridColor,
                labels: { style: { color: textColor, fontSize: '11px' } },
                plotLines: [{
                    value: 0,
                    color: isDark ? '#94A3B8' : '#64748B',
                    width: 1.5,
                    zIndex: 5,
                    label: { text: 'Rp 0', align: 'right', style: { color: textColor, fontSize: '10px' } }
                }]
            },
            tooltip: {
                shared: true,
                backgroundColor: tooltipBg,
                style: { color: tooltipText },
                formatter: function () {
                    let content = `<strong>${this.x}</strong><br/>`;
                    this.points.forEach((point) => {
                        content += `<span style="color:${point.color}">\u25CF</span> ${point.series.name}: <b>${formatMiliar(point.y)}</b><br/>`;
                    });
                    return content;
                }
            },
            legend: { itemStyle: { color: textColor, fontSize: '11px' } },
            plotOptions: { column: { borderRadius: 4, borderWidth: 0 } },
            series: [
                { name: 'Revenue', data: pnl.revenue || [], color: '#2563EB' },
                { name: 'Cost', data: pnl.cost || [], color: '#F97316' },
                {
                    name: 'Net PnL',
                    data: (pnl.profit_loss || []).map((value) => ({
                        y: Number(value) || 0,
                        color: Number(value) > 0 ? '#16A34A' : '#DC2626'
                    }))
                }
            ]
        });

        if (categoryChart) categoryChart.destroy();
        categoryChart = Highcharts.chart('pnl-category-chart', {
            chart: { type: 'pie', backgroundColor: 'transparent' },
            title: { text: null },
            credits: { enabled: false },
            tooltip: {
                pointFormat: '<b>{point.y}</b> site ({point.percentage:.1f}%)',
                backgroundColor: tooltipBg,
                style: { color: tooltipText }
            },
            plotOptions: {
                pie: {
                    innerSize: '65%',
                    borderWidth: 0,
                    dataLabels: { enabled: true, format: '{point.name}: {point.y} ({point.percentage:.1f}%)' },
                    showInLegend: true
                }
            },
            legend: { itemStyle: { color: textColor, fontSize: '11px' } },
            series: [{ name: 'Total Site', data: res.pnl_status || [] }]
        });
    }

    function loadPnlCharts() {
        if (chartRequest) chartRequest.abort();
        $('#pnl-growth-chart, #pnl-category-chart').addClass('opacity-50');
        let request;
        request = $.ajax({
            url: chartApiUrl,
            method: 'GET',
            data: getChartFilters(),
            success: renderPnlCharts,
            error: function (_xhr, status) {
                if (status === 'abort') return;
                $('#pnl-growth-chart, #pnl-category-chart').html('<div class="text-center text-body-secondary py-5">Gagal memuat diagram.</div>');
            },
            complete: function () {
                if (chartRequest !== request) return;
                $('#pnl-growth-chart, #pnl-category-chart').removeClass('opacity-50');
                chartRequest = null;
            }
        });
        chartRequest = request;
    }

    loadPnlCharts();

    $('#page-size').on('change', function () {
        table.page.len(parseInt($(this).val())).draw();
    });

    $('#filter-year').on('change', function () {
        // Saat ganti tahun, pertahankan pilihan Semua Bulan jika sebelumnya Semua Bulan
        const prevBulan = $('#filter-month').val();
        populateMonths(this.value, prevBulan === 'all' ? 0 : null);
        updateExportUrl();
        table.draw();
        loadPnlCharts();
    });

    $('#filter-month, #filter-nop').on('change', function () {
        updateExportUrl();
        table.draw();
        loadPnlCharts();
    });

    $('#filter-status').on('change', function () {
        updateExportUrl();
        table.draw();
    });

    // Helper formatting
    function esc(v) { return $('<div>').text(v == null || v === '' ? '-' : String(v)).html(); }
    function fmtRupiah(val) {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(val || 0));
    }

    const modal = new bootstrap.Modal('#siteDetailModal');

    // Row Click Popup Modal
    $('#pnl-table tbody').on('click', 'tr', function () {
        const row = table.row(this).data();
        if (!row || !row.id) return;

        $('#modal-site-id').text(row.site_id);
        $('#modal-site-name').text('(' + (row.site_name || '-') + ') — NOP: ' + (row.nop || '-'));
        $('#modal-site-body').html('<div class="text-center text-body-secondary py-4">Memuat data histori & grafik...</div>');
        modal.show();

        $.ajax({
            url: historyBaseUrl + '/' + row.id,
            method: 'GET',
            success: function (res) {
                renderSiteModalContent(res);
            },
            error: function () {
                $('#modal-site-body').html('<div class="alert alert-danger mb-0">Gagal memuat rincian histori site.</div>');
            }
        });
    });

    $('#siteDetailModal').on('shown.bs.modal', function () {
        if (pnlModalChart) {
            pnlModalChart.reflow();
        }
    });

    function renderSiteModalContent(res) {
        const t = res.totals || {};
        const history = res.history || [];

        const categories = [];
        const revData = [];
        const costData = [];
        const pnlData = [];

        let rowsHtml = '';
        history.forEach(function (h, index) {
            const isProfit = h.profit_loss > 0;
            const isAnomaly = Boolean(h.is_anomaly);
            const pnlClass = isAnomaly ? 'text-secondary' : (isProfit ? 'text-profit' : 'text-loss');
            const badgeClass = isAnomaly ? 'badge-anomaly' : (isProfit ? 'badge-profit' : 'badge-loss');
            const statusLabel = isAnomaly ? 'Anomali' : h.status;

            categories.push(h.bulan_label);
            revData.push(parseFloat(h.revenue) || 0);
            costData.push(parseFloat(h.cost) || 0);
            pnlData.push(parseFloat(h.profit_loss) || 0);

            rowsHtml += `
            <tr class="${isAnomaly ? 'table-warning' : ''}">
                <td>${esc(h.bulan_label)}</td>
                <td class="text-end"><button type="button" class="btn btn-link btn-sm p-0 metric-detail-button" data-detail-type="revenue" data-detail-index="${index}" title="Lihat detail Revenue">${fmtRupiah(h.revenue)} <span aria-hidden="true">+</span></button></td>
                <td class="text-end"><button type="button" class="btn btn-link btn-sm p-0 metric-detail-button" data-detail-type="cost" data-detail-index="${index}" title="Lihat detail Cost">${fmtRupiah(h.cost)} <span aria-hidden="true">+</span></button></td>
                <td class="text-end ${pnlClass}">${fmtRupiah(h.profit_loss)}</td>
                <td class="text-center">
                    <span class="badge ${badgeClass}">${esc(statusLabel)}</span>
                </td>
            </tr>`;
        });

        const html = `
        ${history.some(h => h.is_anomaly) ? '<div class="alert alert-warning py-2 small">Baris bertanda <b>Anomali</b> ditampilkan sebagai referensi dan tidak termasuk dalam total finansial.</div>' : ''}
        <div class="row g-2 mb-3 text-center">
            <div class="col-md-4">
                <div class="p-2 border rounded stat-card">
                    <span class="text-secondary small">Total Revenue</span>
                    <h6 class="mb-0 fw-bold mt-1">${fmtRupiah(t.total_revenue)}</h6>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-2 border rounded stat-card">
                    <span class="text-secondary small">Total Cost</span>
                    <h6 class="mb-0 fw-bold mt-1">${fmtRupiah(t.total_cost)}</h6>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-2 border rounded stat-card">
                    <span class="text-secondary small">Total Net PnL</span>
                    <h6 class="mb-0 fw-bold ${t.total_pnl > 0 ? 'text-profit' : 'text-loss'} mt-1">${fmtRupiah(t.total_pnl)}</h6>
                </div>
            </div>
        </div>

        {{-- Grafik Diagram Batang Histori --}}
        <div class="card border mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="small fw-semibold">Grafik Diagram Batang: Perbandingan Revenue vs Cost vs Net PnL per Bulan</span>
            </div>
            <div class="card-body p-2">
                <div id="modal-pnl-chart" style="height: 270px; width: 100%;"></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Profit / Loss</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml || '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada data histori bulanan</td></tr>'}
                </tbody>
            </table>
        </div>`;

        $('#modal-site-body').html(html);

        $('#siteDetailModal').off('click.metricDetail', '.metric-detail-button').on('click.metricDetail', '.metric-detail-button', function () {
            const historyRow = history[Number($(this).data('detail-index'))];
            const type = $(this).data('detail-type');
            const details = type === 'cost' ? historyRow.cost_details : historyRow.revenue_details;
            const title = type === 'cost' ? 'Cost Details' : 'Revenue Details';
            const rows = Object.entries(details || {}).map(([label, value]) =>
                `<tr><th>${esc(label)}</th><td class="text-end">${value === null ? '-' : fmtRupiah(value)}</td></tr>`
            ).join('');

            $('#metric-detail-title').text(`${historyRow.bulan_label} ${title}`);
            $('#metric-detail-body').html(rows || '<tr><td class="text-center">-</td></tr>');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('metricDetailModal')).show();
        });

        // Render Highcharts Bar / Column Chart
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const textColor = isDark ? '#94A3B8' : '#64748B';
        const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';

        if (categories.length > 0) {
            pnlModalChart = Highcharts.chart('modal-pnl-chart', {
                chart: {
                    type: 'column',
                    backgroundColor: 'transparent',
                    animation: true
                },
                title: { text: null },
                credits: { enabled: false },
                xAxis: {
                    categories: categories,
                    labels: { style: { color: textColor, fontSize: '11px' } },
                    lineColor: gridColor,
                    tickColor: gridColor
                },
                yAxis: {
                    title: { text: null },
                    gridLineColor: gridColor,
                    labels: {
                        formatter: function () {
                            const val = this.value;
                            if (Math.abs(val) >= 1e9) return (val / 1e9).toFixed(1) + 'M';
                            if (Math.abs(val) >= 1e6) return (val / 1e6).toFixed(0) + 'jt';
                            return val;
                        },
                        style: { color: textColor, fontSize: '11px' }
                    }
                },
                legend: {
                    itemStyle: { color: isDark ? '#E2E8F0' : '#334155', fontSize: '11px' }
                },
                tooltip: {
                    shared: true,
                    backgroundColor: isDark ? '#1E293B' : '#FFFFFF',
                    borderColor: isDark ? '#334155' : '#CBD5E1',
                    style: { color: isDark ? '#F8FAFC' : '#1E293B' },
                    formatter: function () {
                        let s = `<strong>${this.x}</strong><br/>`;
                        this.points.forEach(point => {
                            const color = point.color;
                            s += `<span style="color:${color}">\u25CF</span> ${point.series.name}: <b>${fmtRupiah(point.y)}</b><br/>`;
                        });
                        return s;
                    }
                },
                plotOptions: {
                    column: {
                        borderRadius: 3,
                        pointPadding: 0.1,
                        groupPadding: 0.15,
                        borderWidth: 0
                    }
                },
                series: [
                    { name: 'Revenue', data: revData, color: '#3B82F6' },
                    { name: 'Cost', data: costData, color: '#EF4444' },
                    { name: 'Net PnL', data: pnlData, color: '#10B981', negativeColor: '#F87171' }
                ]
            });
        }
    }
});
</script>
@endpush
