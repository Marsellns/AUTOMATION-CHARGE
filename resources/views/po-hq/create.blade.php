@extends('layouts.app')

@section('title', 'Tambah PO — SIMASTER')

@section('content')
    <h1 class="h4 mb-3">Tambah PO HQ</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('po-hq.store') }}">
                @csrf
                @include('po-hq.partials.form-fields')

                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-brand btn-sm">Simpan</button>
                    <a href="{{ route('po-hq.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
