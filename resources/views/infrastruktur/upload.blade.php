@extends('layouts.app')

@section('title', 'Upload '.$definition['label'].' — SIMASTER')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Upload {{ $definition['label'] }}</h1>
    <a href="{{ route('infrastruktur.'.$dataset.'.index') }}" class="btn btn-outline-secondary btn-sm">← Kembali</a>
</div>
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
<div class="card">
 <div class="card-body">
  <form method="POST" enctype="multipart/form-data" action="{{ route('infrastruktur.upload.store', $dataset) }}">
   @csrf
   <label class="form-label fw-semibold" for="dataset-file">File Excel/CSV <span class="text-danger">*</span></label>
   <input id="dataset-file" type="file" name="dataset_file" class="form-control" accept=".xlsx,.xls,.csv" required>
   <small class="text-body-secondary d-block mt-2">Maksimal 50 MB. Gunakan format kolom dataset yang tersedia.</small>
   <div class="mt-3 d-flex gap-2">
    <button class="btn btn-brand" type="submit">Upload dan simpan</button>
    <a class="btn btn-outline-primary" href="{{ route('infrastruktur.upload.template', $dataset) }}">Template Excel</a>
    <a class="btn btn-outline-secondary" href="{{ route('infrastruktur.'.$dataset.'.index') }}">Batal</a>
   </div>
  </form>
  <div class="alert alert-info mt-4 mb-0">Upload mengganti snapshot data modul ini. Baris tanpa Site ID dilewati dan data akan langsung tampil di halaman modul.</div>
 </div>
</div>
@endsection
