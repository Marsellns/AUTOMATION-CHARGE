@extends('layouts.app')

@section('title', 'Tambah Bongkar Rampung — SIMASTER')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Tambah Bongkar Rampung</h1>
        <a href="{{ route('electricity.centralized.listrik-pln.index') }}" class="btn btn-outline-secondary btn-sm">← Kembali</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Form Tambah Boram</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('electricity.centralized.listrik-pln.bongkar-rampung.store', $listrikPln) }}">
                        @csrf

                        <input type="hidden" name="id_pelanggan" value="{{ $listrikPln->id_pelanggan }}">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="id_pelanggan" class="form-label fw-semibold">ID Pelanggan</label>
                                <input type="text" id="id_pelanggan" class="form-control form-control-sm bg-light" value="{{ $listrikPln->id_pelanggan }}" readonly>
                                <small class="text-body-secondary">Otomatis terisi dari data Listrik PLN</small>
                            </div>
                            <div class="col-md-6">
                                <label for="site_name" class="form-label fw-semibold">Site Name</label>
                                <input type="text" id="site_name" class="form-control form-control-sm bg-light" value="{{ $listrikPln->site_name }}" readonly>
                            </div>

                            <div class="col-md-6">
                                <label for="no_surat_boram" class="form-label">No Surat (Boram) <span class="text-danger">*</span></label>
                                <input type="text" id="no_surat_boram" name="no_surat_boram" class="form-control @error('no_surat_boram') is-invalid @enderror" required>
                                @error('no_surat_boram')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="tanggal_surat_boram" class="form-label">Tanggal Surat (Boram) <span class="text-danger">*</span></label>
                                <input type="date" id="tanggal_surat_boram" name="tanggal_surat_boram" class="form-control @error('tanggal_surat_boram') is-invalid @enderror" required>
                                @error('tanggal_surat_boram')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="mitra_boram" class="form-label">Mitra (Boram) <span class="text-danger">*</span></label>
                                <input type="text" id="mitra_boram" name="mitra_boram" class="form-control @error('mitra_boram') is-invalid @enderror" required>
                                @error('mitra_boram')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-brand btn-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-1"><path d="M13.5 1a1.5 1.5 0 0 1 1.5 1.5v10a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 14V2.5A1.5 1.5 0 0 1 1.5 1h13zM1.5 0h13A2.5 2.5 0 0 1 16 2.5v10A2.5 2.5 0 0 1 13.5 15h-13A2.5 2.5 0 0 1 0 12.5v-10A2.5 2.5 0 0 1 1.5 0z"/><path d="M10.5 3.5a.5.5 0 0 1 1 0v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3z"/></svg>
                                Simpan Data
                            </button>
                            <a href="{{ route('electricity.centralized.listrik-pln.index') }}" class="btn btn-outline-secondary btn-sm">← Kembali</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection