@extends('layouts.app')

@section('title', 'Infrastruktur — SIMASTER')

@section('content')
<div class="page-hero mb-4">
    <h1 class="h4 mb-1">Infrastruktur Performance</h1>
    <p class="text-body-secondary small mb-0">Ringkasan Sewa Lahan dan Combat berdasarkan data yang tersimpan di database.</p>
</div>
<div class="alert alert-info py-2 small">Dashboard ini menggunakan data database: <strong id="dataset-caption">Memuat jumlah baris...</strong>. Kartu Total Site menampilkan jumlah baris data; Site ID unik ditampilkan sebagai keterangan.</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['id' => 'total', 'label' => 'Total Site', 'class' => 'primary', 'icon' => 'tower'],
        ['id' => 'active', 'label' => 'Operational / Active', 'class' => 'success', 'icon' => 'check'],
        ['id' => 'contract', 'label' => 'Perlu Perhatian (Contract)', 'class' => 'warning', 'icon' => 'contract'],
        ['id' => 'without_pks', 'label' => 'Tanpa PKS (Risiko Legal)', 'class' => 'danger', 'icon' => 'alert'],
        ['id' => 'off_air', 'label' => 'Off Air / Non-Operational', 'class' => 'secondary', 'icon' => 'offline'],
    ] as $card)
        <div class="col-lg-4 col-md-6">
            <div class="card h-100 border-{{ $card['class'] }} infra-summary-link infra-summary-card"
                 data-filter-field="{{ $card['id'] === 'total' ? 'all_records' : 'summary_status' }}" data-filter-value="{{ $card['id'] === 'total' ? '' : $card['id'] }}"
                 role="link" tabindex="0" title="Buka data {{ $card['label'] }}">
                <div class="card-body infra-summary-card-body">
                    <div>
                        <div class="small text-body-secondary">{{ $card['label'] }}</div>
                        <div class="fs-3 fw-bold" id="infra-{{ $card['id'] }}">—</div>
                        @if ($card['id'] === 'total')
                            <div class="text-body-tertiary" id="infra-records-sub" style="font-size: 0.75rem;"></div>
                        @endif
                    </div>
                    <span class="infra-summary-icon" aria-hidden="true">
                        @switch($card['icon'])
                            @case('tower')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 7 21M12 3l5 18M7 21h10M8.1 17h7.8M9.3 12h5.4"/><path d="M5.2 5.8a9.5 9.5 0 0 1 0 12.4M18.8 5.8a9.5 9.5 0 0 0 0 12.4"/></svg>
                                @break
                            @case('check')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="m8.5 12.1 2.2 2.2 4.8-5"/></svg>
                                @break
                            @case('contract')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h8l4 4v14H6zM14 3v5h5M9 12h6M9 16h3"/><circle cx="16.5" cy="16.5" r="3.5"/><path d="M16.5 14.8v2l1.2.8"/></svg>
                                @break
                            @case('alert')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h8l4 4v14H6zM14 3v5h5"/><path d="M12 12v4M12 19h.01"/></svg>
                                @break
                            @case('offline')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 7 21M12 3l5 18M7 21h10M8.1 17h7.8M9.3 12h5.4"/><path d="m4 4 16 16"/></svg>
                        @endswitch
                    </span>
                </div>
            </div>
        </div>
    @endforeach
    <div class="col-lg-4 col-md-6">
        <div class="card h-100 border-info infra-summary-link infra-summary-card"
             data-filter-field="summary_status" data-filter-value="contract"
             role="link" tabindex="0" title="Buka data yang perlu perhatian">
            <div class="card-body infra-summary-card-body">
                <div>
                    <div class="small text-body-secondary">Nilai Kontrak Berisiko</div>
                    <div class="fs-3 fw-bold" id="infra-risk_value">—</div>
                </div>
                <span class="infra-summary-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7h14v11H5zM8 7V5h8v2M8 12h8M12 10v4M10 12h4"/></svg>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 infra-overview-chart-grid">
    <div class="col-lg-6 d-flex"><div class="card h-100 w-100 infra-overview-chart-card"><div class="card-header fw-semibold">Komposisi Status Site</div><div class="card-body"><div id="infra-status-chart" class="infra-overview-chart"></div></div></div></div>
    <div class="col-lg-6 d-flex"><div class="card h-100 w-100 infra-overview-chart-card"><div class="card-header fw-semibold">Site Owner (TP / Telkomsel)</div><div class="card-body"><div id="infra-owner-chart" class="infra-overview-chart"></div></div></div></div>
    <div class="col-12 d-flex"><div class="card h-100 w-100 infra-overview-chart-card"><div class="card-header fw-semibold">Distribusi Tahun Renewal Sewa Lahan</div><div class="card-body"><div id="infra-renewal-chart" class="infra-overview-chart"></div></div></div></div>
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
        if (data.cards.unique_sites !== undefined) {
            $('#infra-records-sub').text(`${new Intl.NumberFormat('id-ID').format(data.cards.unique_sites)} site unik`);
        }
        const fmt = value => new Intl.NumberFormat('id-ID').format(value || 0);
        $('#dataset-caption').text(`${fmt(data.dataset_rows.sewa_lahan)} baris Sewa Lahan (${fmt(data.dataset_rows.sewa_sites)} site unik) + ${fmt(data.dataset_rows.combat)} baris Combat (${fmt(data.dataset_rows.combat_sites)} site unik)`);
        const base = window.infrastructureChartThemeOptions();
        const themed = options => Highcharts.merge(base, options);
        const drilldown = (series) => series.map(point => ({ ...point, custom: { filterField: point.filter_field, filterValue: point.filter_value } }));
        const drilldownClick = scope => function () {
            const custom = this.options?.custom;
            if (!custom?.filterField) return;
            window.openInfrastructureDrilldown(scope, custom.filterField, custom.filterValue, custom.filterValue || custom.filterField);
        };
        const clickOptions = { cursor: 'pointer', point: { events: { click: drilldownClick('all') } } };
        const sewaRenewalClickOptions = { cursor: 'pointer', point: { events: { click: drilldownClick('sewa') } } };

        Highcharts.chart('infra-status-chart', themed(Highcharts.merge({
            chart: { type: 'pie', className: 'infra-analytics-chart' },
            plotOptions: { pie: { dataLabels: { enabled: false } }, series: clickOptions },
            series: [{ name: 'Site', data: drilldown(data.status) }]
        }, window.infrastructurePieLabelOptions(data.status.length))));
        Highcharts.chart('infra-owner-chart', themed(Highcharts.merge({
            chart: { type: 'pie', className: 'infra-analytics-chart' },
            plotOptions: { pie: { innerSize: '55%', dataLabels: { enabled: false } }, series: clickOptions },
            series: [{ name: 'Site', data: drilldown(data.owners) }]
        }, window.infrastructurePieLabelOptions(data.owners.length))));
        Highcharts.chart('infra-renewal-chart', themed(Highcharts.merge({
            chart: { type: 'column', className: 'infra-analytics-chart' },
            xAxis: { type: 'category' },
            yAxis: { title: { text: 'Site unik' } },
            plotOptions: { series: sewaRenewalClickOptions },
            series: [{ name: 'Site', data: drilldown(data.renewal_years) }]
        }, window.infrastructureValueLabelOptions('column', data.renewal_years.length))));
    }).fail(function (xhr) {
        const message = xhr.responseJSON?.message || 'Data Infrastruktur belum dapat dimuat.';
        $('.card-body').first().prepend(`<div class="alert alert-danger mb-3">${$('<div>').text(message).html()}</div>`);
    });
});
</script>
@endpush
