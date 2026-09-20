@extends('layouts.app')

@section('title', 'Infrastruktur — SIMASTER')

@section('content')
<div class="page-hero mb-4">
    <h1 class="h4 mb-1">Infrastruktur Performance</h1>
    <p class="text-body-secondary small mb-0">Ringkasan Sewa Lahan dan Combat berdasarkan data dataset yang tersimpan.</p>
</div>
<div class="alert alert-info py-2 small">Dashboard ini menggunakan snapshot dataset: <strong id="dataset-caption">Memuat jumlah baris...</strong>. Angka Site dihitung unik berdasarkan Site ID.</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['id' => 'total', 'label' => 'Total Site', 'class' => 'primary'],
        ['id' => 'active', 'label' => 'Operational / Active', 'class' => 'success'],
        ['id' => 'contract', 'label' => 'Perlu Perhatian (Contract)', 'class' => 'warning'],
        ['id' => 'without_pks', 'label' => 'Tanpa PKS (Risiko Legal)', 'class' => 'danger'],
        ['id' => 'off_air', 'label' => 'Off Air / Non-Operational', 'class' => 'secondary'],
    ] as $card)
        <div class="col-lg-4 col-md-6">
            <div class="card h-100 border-{{ $card['class'] }} infra-summary-link"
                 data-filter-field="{{ $card['id'] === 'total' ? '' : 'summary_status' }}" data-filter-value="{{ $card['id'] === 'total' ? '' : $card['id'] }}"
                 role="link" tabindex="0" title="Buka data {{ $card['label'] }}">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ $card['label'] }}</div>
                    <div class="fs-3 fw-bold" id="infra-{{ $card['id'] }}">—</div>
                    @if ($card['id'] === 'total')
                        <div class="text-body-tertiary" id="infra-records-sub" style="font-size: 0.75rem;"></div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
    <div class="col-lg-4 col-md-6">
        <div class="card h-100 border-info infra-summary-link"
             data-filter-field="summary_status" data-filter-value="contract"
             role="link" tabindex="0" title="Buka data yang perlu perhatian">
            <div class="card-body">
                <div class="small text-body-secondary">Nilai Kontrak Berisiko</div>
                <div class="fs-3 fw-bold" id="infra-risk_value">—</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card"><div class="card-header fw-semibold">Performance Infrastruktur 2026</div><div class="card-body"><div id="infra-performance-chart" style="height:320px"></div></div></div></div>
    <div class="col-lg-5"><div class="card"><div class="card-header fw-semibold">Komposisi Status Site</div><div class="card-body"><div id="infra-status-chart" style="height:320px"></div></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header fw-semibold">Site Owner (TP / Telkomsel)</div><div class="card-body"><div id="infra-owner-chart" style="height:280px"></div></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header fw-semibold">Sumber Data Site</div><div class="card-body"><div id="infra-source-chart" style="height:280px"></div></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header fw-semibold">Status Dokumen Terbanyak</div><div class="card-body"><div id="infra-status-breakdown" style="height:300px"></div></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header fw-semibold">Distribusi Tahun Renewal Sewa Lahan</div><div class="card-body"><div id="infra-renewal-chart" style="height:300px"></div></div></div></div>
    <div class="col-lg-12"><div class="card"><div class="card-header fw-semibold">Nilai Kontrak Berdasarkan Sumber Dataset</div><div class="card-body"><div id="infra-contract-chart" style="height:280px"></div></div></div></div>
</div>
<div class="mt-4">
    <h2 class="h5 mb-3">Analitik Detail Infrastruktur</h2>
    @include('infrastruktur.partials.analytics', ['scope' => 'all'])
</div>
@endsection

@push('scripts')
<script>
$(function () {
    $(document).on('click keydown', '.infra-summary-link', function (event) {
        if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        window.openInfrastructureDrilldown('all', $(this).data('filter-field'), $(this).data('filter-value'), $(this).find('.small').first().text());
    });

    $.getJSON(@json(route('infrastruktur.dashboard.data')), function (data) {
        Object.entries(data.cards).forEach(([key, value]) => {
            const el = $('#infra-' + key);
            if (el.length) el.text(key === 'risk_value' ? 'Rp ' + new Intl.NumberFormat('id-ID').format(value) : new Intl.NumberFormat('id-ID').format(value));
        });
        if (data.cards.records) {
            $('#infra-records-sub').text(`${new Intl.NumberFormat('id-ID').format(data.cards.records)} baris data`);
        }
        const fmt = value => new Intl.NumberFormat('id-ID').format(value || 0);
        $('#dataset-caption').text(`${fmt(data.dataset_rows.sewa_sites)} site Sewa Lahan (${fmt(data.dataset_rows.sewa_lahan)} baris) + ${fmt(data.dataset_rows.combat_sites)} site Combat (${fmt(data.dataset_rows.combat)} baris)`);
        const base = { credits: { enabled: false }, exporting: { enabled: false }, title: { text: null } };
        Highcharts.chart('infra-performance-chart', { ...base, chart: { type: 'column' }, xAxis: { categories: data.performance.map(x => x.label) }, yAxis: { title: { text: 'Nilai' } }, plotOptions: { series: { cursor: 'pointer', point: { events: { click: function () { window.location.href = @json(route('infrastruktur.index')) + '#infrastructure-data'; } } } } }, series: [{ name: 'Revenue', data: data.performance.map(x => x.revenue) }, { name: 'Cost', data: data.performance.map(x => x.cost) }, { name: 'PnL', data: data.performance.map(x => x.pnl) }] });
        const drilldown = (series) => series.map(point => ({ ...point, custom: { filterField: point.filter_field, filterValue: point.filter_value } }));
        const drilldownClick = scope => function () {
            const custom = this.options?.custom;
            if (!custom?.filterField) return;
            window.openInfrastructureDrilldown(scope, custom.filterField, custom.filterValue, custom.filterValue || custom.filterField);
        };
        const clickOptions = { cursor: 'pointer', point: { events: { click: drilldownClick('all') } } };
        const sewaRenewalClickOptions = { cursor: 'pointer', point: { events: { click: drilldownClick('sewa') } } };

        Highcharts.chart('infra-status-chart', { ...base, chart: { type: 'pie' }, plotOptions: { pie: { dataLabels: { enabled: true, format: '{point.name}: {point.y} ({point.percentage:.1f}%)' } }, series: clickOptions }, series: [{ name: 'Site', data: drilldown(data.status) }] });
        Highcharts.chart('infra-owner-chart', { ...base, chart: { type: 'pie' }, plotOptions: { pie: { innerSize: '55%', dataLabels: { enabled: true, format: '{point.name}: {point.y} ({point.percentage:.1f}%)' } }, series: clickOptions }, series: [{ name: 'Site', data: drilldown(data.owners) }] });
        Highcharts.chart('infra-source-chart', { ...base, chart: { type: 'pie' }, plotOptions: { pie: { innerSize: '55%', dataLabels: { enabled: true, format: '{point.name}: {point.y} ({point.percentage:.1f}%)' } }, series: clickOptions }, series: [{ name: 'Site', data: drilldown(data.sources) }] });
        Highcharts.chart('infra-status-breakdown', { ...base, chart: { type: 'bar' }, xAxis: { type: 'category' }, yAxis: { title: { text: 'Site unik' } }, plotOptions: { series: clickOptions }, series: [{ name: 'Site', data: drilldown(data.status_breakdown) }] });
        Highcharts.chart('infra-renewal-chart', { ...base, chart: { type: 'column' }, xAxis: { type: 'category' }, yAxis: { title: { text: 'Site unik' } }, plotOptions: { series: sewaRenewalClickOptions }, series: [{ name: 'Site', data: drilldown(data.renewal_years) }] });
        Highcharts.chart('infra-contract-chart', { ...base, chart: { type: 'column' }, xAxis: { type: 'category' }, yAxis: { title: { text: 'Nilai kontrak' } }, tooltip: { pointFormat: '<b>Rp {point.y:,.0f}</b>' }, plotOptions: { series: clickOptions }, series: [{ name: 'Nilai', data: drilldown(data.contract_by_source) }] });
    }).fail(function (xhr) {
        const message = xhr.responseJSON?.message || 'Data Infrastruktur belum dapat dimuat.';
        $('.card-body').first().prepend(`<div class="alert alert-danger mb-3">${$('<div>').text(message).html()}</div>`);
    });
});
</script>
@endpush
