@extends('layouts.app')

@section('title', 'Tambah File PDF — SIMASTER')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Tambah File PDF</h1>
        <a href="{{ route('infrastruktur.upload-file.index') }}" class="btn btn-outline-secondary btn-sm">← Kembali</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('infrastruktur.upload-file.store') }}" enctype="multipart/form-data">
                @csrf
                @include('infrastruktur.upload-file.partials.form-fields')

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-brand btn-sm">Upload</button>
                    <a href="{{ route('infrastruktur.upload-file.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
