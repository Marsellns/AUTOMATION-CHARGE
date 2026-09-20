@extends('layouts.app')

@section('title', 'Edit BAPSS — SIMASTER')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit BAPSS: {{ $bapss->site_code }}</h1>
        <a href="{{ route('infrastruktur.bapss.index') }}" class="btn btn-outline-secondary btn-sm">← Kembali</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('infrastruktur.bapss.update', $bapss) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('infrastruktur.bapss.partials.form-fields')

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-brand btn-sm">Simpan Perubahan</button>
                    <a href="{{ route('infrastruktur.bapss.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
