@extends('layouts.app')

@section('title', 'Edit Listrik PLN — SIMASTER')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Listrik PLN: {{ $listrikPln->id_pelanggan }}</h1>
        <a href="{{ route('electricity.centralized.listrik-pln.index') }}" class="btn btn-outline-secondary btn-sm">← Kembali</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('electricity.centralized.listrik-pln.update', $listrikPln) }}">
                @csrf
                @method('PUT')
                @include('electricity.centralized.listrik-pln.partials.form-fields')

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-brand btn-sm">Simpan Perubahan</button>
                    <a href="{{ route('electricity.centralized.listrik-pln.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection