@php($panels = [
    'nop' => [
        ['id' => 'nop-financial', 'title' => 'Revenue, Cost, dan Net P&L per NOP', 'note' => null],
        ['id' => 'nop-margin', 'title' => 'Margin P&L per NOP', 'note' => 'Margin dihitung dari total Net P&L dibagi total Revenue. Revenue nol ditampilkan tanpa margin.'],
    ],
    'monthly' => [
        ['id' => 'growth', 'title' => 'Tren Revenue, Cost, dan Net P&L', 'note' => 'Mengikuti periode terpilih. Pilih Semua Bulan untuk tren tahunan.'],
        ['id' => 'monthly-margin', 'title' => 'Tren Margin Bulanan', 'note' => 'Perubahan margin finansial berdasarkan bulan yang tersedia dalam dataset.'],
    ],
    'nop-pnl' => [
        ['id' => 'nop-profit-loss', 'title' => 'Kontribusi Profit dan Loss per NOP', 'note' => 'Jumlah laba site profit dan jumlah kerugian site loss ditampilkan terpisah.'],
        ['id' => 'nop-status', 'title' => 'Komposisi Site Profit/Loss per NOP', 'note' => 'Nilai P&L nol mengikuti kategori Loss / Impas.'],
    ],
    'city' => [
        ['id' => 'city-financial', 'title' => 'Profit dan Loss per City', 'note' => 'Semua kota dari master Data Potensi ditampilkan.'],
        ['id' => 'city-status', 'title' => 'Performa Site per City', 'note' => 'Site yang belum memiliki pemetaan kota dikelompokkan sebagai Belum dipetakan.'],
    ],
])
@foreach ($panels as $key => $charts)
    <section id="pnl-panel-{{ $key }}" data-pnl-panel="{{ $key }}" role="tabpanel" aria-labelledby="pnl-tab-{{ $key }}" hidden>
        <div class="row g-3 mb-3">
            @foreach ($charts as $chart)
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header"><h2 class="h6 mb-1">{{ $chart['title'] }}</h2>@if ($chart['note'])<p class="small text-body-secondary mb-0">{{ $chart['note'] }}</p>@endif</div>
                        <div class="card-body"><div class="pnl-chart-scroll"><div id="pnl-{{ $chart['id'] }}-chart" class="pnl-chart" aria-label="{{ $chart['title'] }}"></div></div></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endforeach
