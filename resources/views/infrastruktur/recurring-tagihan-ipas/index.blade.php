@extends('layouts.app')

@section('title', 'Recurring (Tagihan Ipas) — Infrastruktur Management — SIMASTER')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Infrastruktur Management — Recurring (Tagihan Ipas)</h1>
        <div class="d-flex gap-2">
            @role('admin')<a href="{{ route('infrastruktur.upload', 'recurring-tagihan-ipas') }}" class="btn btn-sm btn-brand">Upload Excel</a>@endrole
            <a href="{{ route('infrastruktur.upload.export', 'recurring-tagihan-ipas') }}" class="btn btn-sm btn-outline-brand">Download Excel</a>
        </div>
    </div>

    <div class="card mb-3" data-simaster-filter-panel="Filter Tagihan IPAS">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <label for="filter-tp" class="form-label mb-0 small fw-semibold text-nowrap">Filter TP:</label>
                <select id="filter-tp" class="form-select form-select-sm" style="width:auto;min-width:160px">
                    <option value="all">Semua TP</option>
                    @foreach ($tpList as $tp)
                        <option value="{{ $tp }}">{{ $tp }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label for="page-size" class="form-label mb-0 small fw-semibold text-nowrap">Show:</label>
                <select id="page-size" class="form-select form-select-sm" style="width:auto">
                    @foreach ([10, 20, 40, 80, 100, 500] as $size)
                        <option value="{{ $size }}" @selected($size === 10)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <p class="small text-muted mb-2">Klik salah satu baris untuk melihat seluruh detail kontrak, PR/PO, invoice, dan GR dari sumber data.</p>
            <table id="tagihan-table" class="display align-middle text-nowrap" style="width:100%">
                <thead><tr>
                    <th>No</th><th>Site ID</th><th>Site Name</th><th>TP</th>
                    <th>Contract Type</th><th>Termin</th><th>Periode Ke</th>
                    <th>Termin Start</th><th>Termin End</th><th>Amount</th><th>Batch Name</th>
                </tr></thead>
            </table>
        </div>
    </div>

    <div class="modal fade" id="tagihan-detail-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="tagihan-detail-title">Detail Tagihan IPAS</h2>
                        <div class="small text-muted" id="tagihan-detail-subtitle"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3" id="tagihan-detail-fields"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    const date = (data) => {
        if (!data) return '-';
        const d = new Date(data);
        return isNaN(d) ? data : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    };
    const columns = [
        { data: 'DT_RowIndex', name: 'no', orderable: false, searchable: false },
        { data: 'site_code', name: 'site_code' },
        { data: 'site_name', name: 'site_name' },
        { data: 'tp', name: 'tp' },
        { data: 'contract_type', name: 'contract_type' },
        { data: 'termin', name: 'termin' },
        { data: 'periode_ke', name: 'periode_ke' },
        { data: 'termin_start', name: 'termin_start', render: date },
        { data: 'termin_end', name: 'termin_end', render: date },
        {
            data: 'amount', name: 'amount', className: 'text-end',
            render: (data) => data == null || data === '' ? '-' : new Intl.NumberFormat('id-ID').format(data),
        },
        { data: 'batch_name', name: 'batch_name' },
    ];
    const table = new DataTable('#tagihan-table', {
        processing: true, serverSide: true, scrollX: true,
        ajax: {
            url: @json(route('infrastruktur.recurring-tagihan-ipas.data')),
            data: (d) => { d.tp = $('#filter-tp').val(); },
        },
        columns, order: [], pageLength: 10,
        createdRow: (row) => {
            $(row).css('cursor', 'pointer').attr('title', 'Klik untuk melihat detail lengkap');
        },
    });

    const labels = {
        recurring: 'Recurring', siteid: 'Site ID', site_name: 'Site Name', contract_type: 'Contract Type',
        termin: 'Termin', periode_ke: 'Periode Ke', termin_start: 'Termin Start', termin_end: 'Termin End',
        termin_amount: 'Termin Amount', status: 'Status', tp: 'TP', tp_type: 'TP Type', region: 'Region',
        region2: 'Region (2)', contract_no: 'Contract No', sap_contract_no: 'SAP Contract No',
        contract_description: 'Contract Description', infra_contract: 'Infra Contract', contract_type2: 'Contract Type (2)',
        first_contract_ref: 'First Contract Ref', sow: 'SOW', sow_detail: 'SOW Detail', pr_number: 'PR Number',
        pr_date_year: 'PR Date Year', po_number: 'PO Number', po_date_year: 'PO Date Year', pr_date: 'PR Date',
        po_date: 'PO Date', collaboration: 'Collaboration', collaboration_date: 'Collaboration Date',
        invoice_number: 'Invoice Number', invoice_date: 'Invoice Date', gr_number: 'GR Number', gr_date: 'GR Date',
        batch_name: 'Batch Name',
    };

    $('#tagihan-table tbody').on('click', 'tr', function () {
        const row = table.row(this).data();
        if (!row) return;

        let details = row.source_details || {};
        if (typeof details === 'string') {
            try { details = JSON.parse(details); } catch (_) { details = {}; }
        }

        $('#tagihan-detail-title').text(row.site_code || 'Detail Tagihan IPAS');
        $('#tagihan-detail-subtitle').text([row.site_name, row.tp].filter(Boolean).join(' • '));

        const container = $('#tagihan-detail-fields').empty();
        Object.entries(details).forEach(([key, value]) => {
            if (value === null || value === '') return;
            const label = labels[key] || key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
            const field = $('<div>').addClass('col-12 col-md-6 col-xl-4');
            field.append($('<div>').addClass('small text-muted').text(label));
            field.append($('<div>').addClass('fw-semibold text-break').text(String(value)));
            container.append(field);
        });

        if (!container.children().length) {
            container.append($('<div>').addClass('col-12 text-muted').text('Detail sumber tidak tersedia.'));
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('tagihan-detail-modal')).show();
    });

    $('#filter-tp').on('change', () => table.ajax.reload());
    $('#page-size').on('change', function () { table.page.len(parseInt($(this).val())).draw(); });
});
</script>
@endpush
