@extends('layouts.app')

@section('title', $title.' — SIMAWAR')

@section('content')
    <header class="page-hero d-flex align-items-center gap-3 mb-4">
        <img src="{{ asset('assets/modules/'.$image.'.svg') }}" alt="" width="64" height="64">
        <div><h1 class="h4 mb-1">{{ $title }}</h1><p class="text-body-secondary mb-0">{{ $description }}</p></div>
    </header>

    <div class="row g-3 mb-4">
        @foreach ($stats as $label => $value)
            <div class="col-md-4 col-12"><div class="card h-100"><div class="card-body">
                <div class="small text-body-secondary mb-1">{{ $label }}</div>
                <div class="fs-3 fw-bold">{{ number_format($value, 0, ',', '.') }}</div>
            </div></div></div>
        @endforeach
    </div>

    <div class="row g-4">
        @foreach ($charts as $index => $chart)
            <div class="col-xl-6">
                <section class="card h-100">
                    <header class="card-header d-flex justify-content-between align-items-center gap-2">
                        <h2 class="h6 mb-0">{{ $chart['title'] }}</h2>
                        <a href="{{ route($chart['route']) }}" class="btn btn-sm btn-outline-secondary text-nowrap">Buka data</a>
                    </header>
                    <div class="card-body"><div id="module-chart-{{ $index }}" style="min-height:330px" aria-label="{{ $chart['title'] }}"></div></div>
                </section>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
$(function () {
    const definitions = @json($charts);
    const charts = [];
    const format = value => Number(value).toLocaleString('id-ID');
    const escapeHtml = value => $('<div>').text(String(value)).html();
    function renderModuleCharts() {
        const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const text = dark ? '#e2e8f0' : '#334155';
        definitions.forEach((definition, index) => {
            const container = document.getElementById(`module-chart-${index}`);
            charts[index]?.destroy();
            if (!definition.data.some(point => point.y > 0)) {
                container.textContent = 'Belum ada data untuk ditampilkan.';
                container.className = 'd-flex align-items-center justify-content-center text-body-secondary';
                return;
            }
            const pie = definition.type === 'pie';
            charts[index] = Highcharts.chart(container, {
                chart: { type: definition.type, backgroundColor: 'transparent', height: 330, style: { fontFamily: 'inherit' } },
                title: { text: null }, credits: { enabled: false },
                colors: window.SimasterChartPalette.series,
                xAxis: { type: 'category', labels: { style: { color: text } }, lineColor: dark ? '#334155' : '#e2e8f0' },
                yAxis: { min: 0, allowDecimals: false, title: { text: definition.unit, style: { color: text } }, labels: { style: { color: text } }, gridLineColor: dark ? '#334155' : '#e2e8f0' },
                legend: { enabled: pie, itemStyle: { color: text } },
                tooltip: { backgroundColor: dark ? '#1e293b' : '#fff', style: { color: text }, formatter() { return `${escapeHtml(this.key)}: <b>${format(this.y)}</b> ${definition.unit}${pie ? ` (${Highcharts.numberFormat(this.percentage, 1)}%)` : ''}`; } },
                plotOptions: {
                    series: { borderWidth: 0, colorByPoint: true, dataLabels: { enabled: !pie, style: { color: text, textOutline: 'none' } } },
                    pie: { innerSize: '55%', showInLegend: true, dataLabels: { enabled: definition.data.length <= 8, format: '{point.name}: {point.percentage:.1f}%', style: { color: text, textOutline: 'none' } } },
                },
                series: [{ name: definition.unit, data: definition.data }],
            });
        });
    }
    renderModuleCharts();
    window.addEventListener('simaster:theme-changed', renderModuleCharts);
});
</script>
@endpush
