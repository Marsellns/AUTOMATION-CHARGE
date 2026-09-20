@extends('layouts.app')

@section('title', 'Upload Presales')
@section('page-title', 'Upload Presales')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h1 class="h4 mb-1">Upload Dokumen</h1><p class="text-muted mb-0">Dokumen baru akan masuk ke tahap Manager NOP.</p></div>
    <a href="{{ route('presales.index') }}" class="btn btn-outline-secondary">Kembali</a>
</div>
<div class="card" style="max-width: 760px">
    <div class="card-body">
        @if ($errors->any()) <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        <form method="POST" action="{{ route('presales.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3"><label class="form-label">Judul dokumen</label><input name="document_title" class="form-control" value="{{ old('document_title') }}" required></div>
            <div class="mb-3"><label class="form-label">Nomor dokumen</label><input name="document_number" class="form-control" value="{{ old('document_number') }}" required></div>
            <div class="mb-4"><label class="form-label">File PDF</label><input name="document_file" type="file" class="form-control" accept="application/pdf,.pdf" required><small class="text-muted">Maksimal 10 MB.</small></div>
            <button class="btn btn-brand">Upload ke Presales</button>
        </form>
    </div>
</div>
@endsection
