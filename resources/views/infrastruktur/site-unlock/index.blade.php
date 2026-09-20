@extends('layouts.app')

@section('title', 'Data Site Unlock — Infrastruktur Management — SIMASTER')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Infrastruktur Management — Data Site Unlock</h1>
        <div class="d-flex gap-2">
            @role('admin')<a href="{{ route('infrastruktur.upload', 'site-unlock') }}" class="btn btn-sm btn-brand">Upload Excel</a>@endrole
            <a href="{{ route('infrastruktur.upload.export', 'site-unlock') }}" class="btn btn-sm btn-outline-brand">Download Excel</a>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="card mb-3" data-simaster-filter-panel="Pengaturan Data Site Unlock">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <label for="page-size" class="form-label mb-0 small fw-semibold text-nowrap">Show:</label>
                <select id="page-size" class="form-select form-select-sm" style="width:auto">
                    @foreach ([10, 20, 40, 80, 100, 5000] as $size)
                        <option value="{{ $size }}" @selected($size === 10)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="unlock-table" class="display align-middle text-nowrap" style="width:100%">
                <thead><tr>
                    <th>No</th>
                    <th>Site ID</th>
                    <th>Site Name</th>
                    <th>Class</th>
                    <th>City</th>
                    <th>Batch</th>
                    <th>Status</th>
                    <th>Final Status</th>
                    <th>Update By</th>
                    <th>Tanggal</th>
                </tr></thead>
            </table>
        </div>
    </div>
@endsection

@push('styles')
<style>
    /* Badge status colors — mengikuti design system SIMASTER */
    .badge-done   { background: #DCFCE7; color: #166534; }
    .badge-progress { background: #DBEAFE; color: #1E40AF; }
    .badge-pending  { background: #FEF3C7; color: #92400E; }
    .badge-cancel   { background: #FFEDD5; color: #9A3412; }
    .badge-default  { background: #F1F5F9; color: #64748B; }
    .status-badge {
        font-weight: 600; font-size: 0.72rem;
        padding: 0.3em 0.65em; border-radius: 0.45rem;
        display: inline-block; white-space: nowrap;
    }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    // --- Status badge color mapping ---
    function statusBadge(value) {
        if (!value || value === '-') return '<span class="status-badge badge-default">-</span>';
        const v = String(value).toLowerCase();
        let cls = 'badge-default';
        if (v.includes('done') || v.includes('selesai') || v.includes('complete') || v.includes('aktif'))
            cls = 'badge-done';
        else if (v.includes('progress') || v.includes('proses') || v.includes('running'))
            cls = 'badge-progress';
        else if (v.includes('pending') || v.includes('hold') || v.includes('waiting') || v.includes('review'))
            cls = 'badge-pending';
        else if (v.includes('cancel') || v.includes('batal') || v.includes('reject') || v.includes('dismantle'))
            cls = 'badge-cancel';
        return `<span class="status-badge ${cls}">${$('<div>').text(value).html()}</span>`;
    }

    const columns = [
        { data: 'DT_RowIndex', name: 'no', orderable: false, searchable: false },
        { data: 'site_code', name: 'site_code' },
        { data: 'site_name', name: 'site_name' },
        { data: 'site_class', name: 'site_class' },
        { data: 'city', name: 'city' },
        { data: 'batch', name: 'batch' },
        { data: 'status', name: 'status', render: (d) => statusBadge(d) },
        { data: 'final_status', name: 'final_status', render: (d) => statusBadge(d) },
        { data: 'update_by', name: 'update_by' },
        { data: 'tanggal', name: 'tanggal',
          render: (d) => (!d || d === '-') ? '<span class="status-badge badge-default">-</span>' : d },
    ];

    const table = new DataTable('#unlock-table', {
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: { url: @json(route('infrastruktur.site-unlock.data')) },
        columns,
        order: [],
        pageLength: 10,
    });

    $('#page-size').on('change', function () { table.page.len(parseInt($(this).val())).draw(); });
});
</script>
@endpush
