@extends('layouts.app')

@section('title', 'Edit File PDF — SIMASTER')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit File PDF</h1>
        <a href="{{ route('infrastruktur.upload-file.index') }}" class="btn btn-outline-secondary btn-sm">← Kembali</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('infrastruktur.upload-file.update', $file) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('infrastruktur.upload-file.partials.form-fields')

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-brand btn-sm">Simpan Perubahan</button>
                    <a href="{{ route('infrastruktur.upload-file.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
