@extends('layouts.app')

@section('title', $document->document_title.' — Presales')
@section('page-title', 'Detail Presales')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div><h1 class="h4 mb-1">{{ $document->document_title }}</h1><p class="text-muted mb-0">{{ $document->document_number ?: 'Nomor belum diisi' }}</p></div>
    <a href="{{ route('presales.index') }}" class="btn btn-outline-secondary">Kembali</a>
</div>
@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0">Status sirkulasi</h2><span class="badge text-bg-{{ $document->status === 'Completed' ? 'success' : ($document->status === 'Rejected' ? 'danger' : 'warning') }}">{{ $document->status }}</span></div>
            <div class="row g-2">
                @foreach ($steps as $step => $label)
                    @php($approved = $document->approvals->first(fn($approval) => $approval->step === $step && $approval->action === 'approved'))
                    @php($rejected = $document->approvals->first(fn($approval) => $approval->step === $step && $approval->action === 'rejected'))
                    @php($completed = $approved || ($step === 1 && $document->current_step > 1))
                    <div class="col-12"><div class="border rounded p-3 d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-{{ $completed ? 'success' : ($rejected ? 'danger' : ($document->current_step === $step && !in_array($document->status, ['Completed','Rejected'], true) ? 'primary' : 'light')) }} text-{{ $completed || $rejected || ($document->current_step === $step && !in_array($document->status, ['Completed','Rejected'], true)) ? 'white' : 'dark' }} d-flex align-items-center justify-content-center" style="width:34px;height:34px">{{ $completed ? '✓' : $step }}</div>
                        <div class="flex-grow-1"><div class="fw-semibold">{{ $label }}</div><small class="text-muted">@if($step === 1 && $document->current_step > 1) Dokumen berhasil diupload @elseif($approved) Disetujui oleh {{ $approved->approver_name }} @elseif($rejected) Ditolak oleh {{ $rejected->approver_name }} @elseif($document->current_step === $step && !in_array($document->status, ['Completed','Rejected'], true)) Menunggu tindakan @else Menunggu tahap sebelumnya @endif</small></div>
                    </div></div>
                @endforeach
            </div>
        </div></div>

        @if ($canAct)
            <div class="card mb-3"><div class="card-body"><h2 class="h6">Tindakan {{ $document->currentStepName() }}</h2><form method="POST" action="{{ route('presales.status', $document) }}">@csrf
                <textarea class="form-control mb-2" name="comments" rows="3" placeholder="Komentar persetujuan / alasan penolakan (wajib jika ditolak)">{{ old('comments') }}</textarea>
                <div class="d-flex gap-2"><button name="action" value="approve" class="btn btn-success">Setujui &amp; teruskan</button><button name="action" value="reject" class="btn btn-outline-danger">Tolak</button></div>
            </form></div></div>
        @endif

        <div class="card"><div class="card-body"><h2 class="h6">Riwayat tindakan</h2>
            @forelse($document->approvals as $approval)<div class="border-bottom py-2"><div class="d-flex justify-content-between"><span class="fw-semibold">{{ $approval->approver_name }} — {{ $steps[$approval->step] ?? 'Tahap '.$approval->step }}</span><small>{{ $approval->acted_at?->format('d M Y H:i') }}</small></div><span class="badge text-bg-{{ $approval->action === 'approved' ? 'success' : 'danger' }}">{{ $approval->action === 'approved' ? 'Disetujui' : 'Ditolak' }}</span>@if($approval->comments)<div class="small text-muted mt-1">{{ $approval->comments }}</div>@endif</div>@empty <p class="text-muted mb-0">Belum ada tindakan approval.</p>@endforelse
        </div></div>
    </div>
    <div class="col-lg-4"><div class="card"><div class="card-body"><h2 class="h6">Informasi dokumen</h2><dl class="row small mb-0"><dt class="col-5">File</dt><dd class="col-7"><a target="_blank" rel="noopener" href="{{ route('presales.file', $document) }}">{{ $document->file_name }}</a></dd><dt class="col-5">Uploader</dt><dd class="col-7">{{ $document->uploaded_by_name ?: '-' }}</dd><dt class="col-5">Tanggal</dt><dd class="col-7">{{ $document->created_at?->format('d M Y H:i') }}</dd>@if($document->rejected_reason)<dt class="col-5 text-danger">Catatan</dt><dd class="col-7 text-danger">{{ $document->rejected_reason }}</dd>@endif</dl></div></div></div>
</div>
@endsection
