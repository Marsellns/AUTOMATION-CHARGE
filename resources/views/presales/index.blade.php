@extends('layouts.app')

@section('title', 'Presales — PO Monitoring')
@section('page-title', 'Presales')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <p class="text-uppercase small fw-bold text-primary mb-1">PO Monitoring</p>
        <h1 class="h3 mb-1">Presales</h1>
        <p class="text-muted mb-0">Pantau dokumen dari upload hingga persetujuan Manager NOP, SQ, NOS, dan NBAE.</p>
    </div>
    @if ($canUpload)
        <a class="btn btn-brand" href="{{ route('presales.create') }}"><i class="fa-solid fa-upload me-2"></i>Upload dokumen PDF</a>
    @endif
</div>

@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Total dokumen</div><div class="fs-2 fw-bold">{{ number_format($stats['total'], 0, ',', '.') }}</div><div class="small text-muted">Terdaftar di Presales</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Menunggu persetujuan</div><div class="fs-2 fw-bold text-primary">{{ number_format($stats['waiting_approval'], 0, ',', '.') }}</div><div class="small text-muted">Pada salah satu tahap manager</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Selesai</div><div class="fs-2 fw-bold text-success">{{ number_format($stats['completed'], 0, ',', '.') }}</div><div class="small text-muted">Disetujui seluruh tahap</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Ditolak</div><div class="fs-2 fw-bold text-danger">{{ number_format($stats['rejected'], 0, ',', '.') }}</div><div class="small text-muted">Perlu tindak lanjut</div></div></div></div>
</div>

@if ($roleStep !== null)
    <div class="alert alert-info mb-4">Tindakan Anda sebagai {{ \App\Models\DocumentCirculation::STEPS[$roleStep] }}: <strong>{{ $stats['action_required'] }} dokumen</strong> menunggu persetujuan.</div>
@endif

<div class="card mb-4"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0">Dokumen dalam proses menurut tahap</h2><span class="small text-muted">{{ $stats['waiting_approval'] }} dokumen aktif</span></div>
    <div class="row g-3">
        @foreach (array_slice(\App\Models\DocumentCirculation::STEPS, 1, null, true) as $step => $label)
            @php($count = (int) ($byStep[$step] ?? 0))
            <div class="col-sm-6 col-xl-3"><a class="text-decoration-none" href="{{ route('presales.index', ['step' => $step]) }}">
                <div class="d-flex justify-content-between small mb-1"><span class="text-body">{{ $label }}</span><strong>{{ $count }}</strong></div>
                <div class="progress" role="img" aria-label="{{ $label }}: {{ $count }} dokumen"><div class="progress-bar" style="width: {{ $stats['waiting_approval'] ? round($count / $stats['waiting_approval'] * 100) : 0 }}%"></div></div>
            </a></div>
        @endforeach
    </div>
</div></div>

<div class="card">
    <div class="card-header bg-transparent border-0 pt-3 pb-0"><h2 class="h6 mb-0">Daftar dokumen</h2></div>
    <div class="card-body">
        <form class="row g-2 align-items-end mb-3" method="GET" action="{{ route('presales.index') }}" data-simaster-filter-panel="Filter Dokumen Presales">
            <div class="col-md-5"><label class="form-label small" for="presalesSearch">Cari dokumen</label><input class="form-control" id="presalesSearch" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Judul, nomor, atau nama file"></div>
            <div class="col-sm-4 col-md-2"><label class="form-label small" for="presalesStatus">Status</label><select class="form-select" id="presalesStatus" name="status"><option value="">Semua status</option>@foreach (['Pending', 'In Progress', 'Completed', 'Rejected'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-sm-4 col-md-3"><label class="form-label small" for="presalesStep">Tahap</label><select class="form-select" id="presalesStep" name="step"><option value="">Semua tahap</option>@foreach (array_slice(\App\Models\DocumentCirculation::STEPS, 1, null, true) as $step => $label)<option value="{{ $step }}" @selected((int) ($filters['step'] ?? 0) === $step)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-sm-4 col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit">Filter</button><a class="btn btn-outline-secondary" href="{{ route('presales.index') }}" aria-label="Reset filter">↺</a></div>
        </form>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Dokumen</th><th>Nomor</th><th>Status</th><th>Tahap saat ini</th><th>Uploader</th><th>Upload (WIB)</th><th></th></tr></thead>
            <tbody>
            @forelse ($documents as $document)
                @php($badge = match($document->status) { 'Completed' => 'success', 'Rejected' => 'danger', 'In Progress' => 'primary', default => 'warning' })
                <tr><td><div class="fw-semibold">{{ $document->document_title }}</div><small class="text-muted">{{ $document->file_name }}</small></td><td>{{ $document->document_number ?: '-' }}</td><td><span class="badge text-bg-{{ $badge }}">{{ $document->status }}</span></td><td>{{ in_array($document->status, ['Pending', 'In Progress'], true) ? $document->currentStepName() : '-' }}</td><td>{{ $document->uploaded_by_name ?: '-' }}</td><td>{{ \App\Models\DocumentCirculation::formatWib($document->created_at) }}</td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('presales.show', $document) }}">Detail</a></td></tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5">{{ request()->hasAny(['search', 'status', 'step']) ? 'Tidak ada dokumen yang sesuai filter.' : 'Belum ada dokumen Presales yang diupload.' }}</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @if ($documents->hasPages()) <div class="card-footer">{{ $documents->links() }}</div> @endif
</div>
@endsection
