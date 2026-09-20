@extends('layouts.app')

@section('title', 'Site Loss - SIMASTER')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">Daftar Site Loss</h1>
            @if ($year !== null)
                <p class="text-muted mb-0">
                    Seluruh site dengan profit/loss negatif pada periode {{ sprintf('%02d/%d', $month, $year) }}.
                </p>
            @else
                <p class="text-muted mb-0">Belum ada data periode site.</p>
            @endif
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('notifications.site-loss.export') }}" class="btn btn-outline-brand btn-sm">Download Excel</a>
            <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary btn-sm">Kembali ke notifikasi</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <strong>Site Loss</strong>
            <span class="badge text-bg-danger">{{ $sites->count() }} site</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Site ID</th>
                        <th>Nama Site</th>
                        <th>Region</th>
                        <th class="text-end pe-4">Profit/Loss</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sites as $metric)
                        <tr>
                            <td class="ps-4 fw-semibold">{{ $metric->site?->site_id ?? '-' }}</td>
                            <td>{{ $metric->site?->site_name ?? '-' }}</td>
                            <td>{{ $metric->site?->region?->nama ?? '-' }}</td>
                            <td class="text-end pe-4 text-danger fw-semibold">
                                Rp {{ number_format((float) $metric->profit_loss, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-5">Tidak ada site Loss pada periode terbaru.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
