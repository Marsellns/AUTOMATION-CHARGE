@extends('layouts.app')

@section('title', 'Upload File PDF — Infrastruktur Management — SIMASTER')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Infrastruktur Management — Upload File PDF</h1>
    </div>

    {{-- Toolbar --}}
    <div class="card mb-3" data-simaster-filter-panel="Pengaturan Daftar File PDF">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <label for="page-size" class="form-label mb-0 small fw-semibold text-nowrap">Show:</label>
                <select id="page-size" class="form-select form-select-sm" style="width:auto">
                    @foreach ([10, 20, 40, 80, 100] as $size)
                        <option value="{{ $size }}" @selected($size === 10)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            @role('admin')
            <a href="{{ route('infrastruktur.upload-file.create') }}" class="btn btn-sm btn-brand ms-auto">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-1"><path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/></svg>
                Tambah File
            </a>
            @endrole
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="upload-table" class="display align-middle text-nowrap" style="width:100%">
                <thead><tr>
                    <th>No</th>
                    <th>Keterangan</th>
                    <th>File</th>
                    <th>Update By</th>
                    <th>Update Time</th>
                    @role('admin') <th>Aksi</th> @endrole
                </tr></thead>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    const isAdmin = {{ auth()->user()->hasRole('admin') ? 'true' : 'false' }};

    const columns = [
        { data: 'DT_RowIndex', name: 'no', orderable: false, searchable: false },
        { data: 'keterangan', name: 'keterangan' },
        { data: 'file_link', name: 'file_path', orderable: false, searchable: false },
        { data: 'update_by', name: 'update_by' },
        { data: 'update_time', name: 'update_time' },
    ];
    if (isAdmin) {
        columns.push({ data: 'aksi', name: 'aksi', orderable: false, searchable: false });
    }

    const table = new DataTable('#upload-table', {
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: { url: @json(route('infrastruktur.upload-file.data')) },
        columns,
        order: [],
        pageLength: 10,
    });

    $('#page-size').on('change', function () { table.page.len(parseInt($(this).val())).draw(); });
});
</script>
@endpush
