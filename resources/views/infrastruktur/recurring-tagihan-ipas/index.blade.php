@extends('layouts.app')

@section('title', 'Recurring (Tagihan Ipas) — Infrastruktur Management — SIMAWAR')

@section('content')
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Infrastruktur Management — Recurring (Tagihan Ipas)</h1>
        <div class="d-flex flex-wrap gap-2">
            @include('infrastruktur.partials.entry-actions', ['dataset' => 'recurring-tagihan-ipas'])
            <a href="{{ route('infrastruktur.upload.export', 'recurring-tagihan-ipas') }}" class="btn btn-sm btn-outline-brand">Download Excel</a>
            <a href="{{ route('infrastruktur.upload.export', ['dataset' => 'recurring-tagihan-ipas', 'detail' => 1]) }}" class="btn btn-sm btn-outline-brand">Download Rincian CSV</a>
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
            <p class="small text-muted mb-3">Satu baris per Site ID. Termin dan periode merangkum nomor yang tersedia; rentang tanggal mencakup seluruh kontrak. Total Tagihan adalah jumlah semua rincian, bukan nilai per termin. Klik site untuk melihat setiap siklus kontrak dan dokumen sumbernya.</p>
            <table id="tagihan-table" class="display align-middle" style="width:100%">
                <thead><tr>
                    <th>No</th><th>Site ID</th><th>Site Name</th><th>TP</th>
                    <th>Contract Type</th><th>Termin</th><th>Periode Ke</th>
                    <th>Rentang Termin</th><th>Jumlah Rincian</th><th>Total Tagihan</th><th>Batch Name</th>
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
    @include('infrastruktur.partials.manual-entry-modal', ['dataset' => 'recurring-tagihan-ipas'])
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
        ...['site_code', 'site_name', 'tp', 'contract_type', 'termin', 'periode_ke'].map((key) => ({ data: key, name: key, render: DataTable.render.text(), defaultContent: '-' })),
        { data: 'termin_start', name: 'termin_start', render: (data, type, row) => `${date(data)} s.d. ${date(row.termin_end)}` },
        { data: 'term_count', name: 'term_count', searchable: false },
        {
            data: 'amount', name: 'amount', className: 'text-end',
            render: (data) => data == null || data === '' ? '-' : new Intl.NumberFormat('id-ID').format(data),
        },
        { data: 'batch_name', name: 'batch_name', render: DataTable.render.text(), defaultContent: '-' },
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

    let detailRequest;
    $('#tagihan-table tbody').on('click', 'tr', function () {
        const row = table.row(this).data();
        if (!row) return;

        $('#tagihan-detail-title').text(row.site_code || 'Detail Tagihan IPAS');
        $('#tagihan-detail-subtitle').text(`${row.site_name || ''} • ${row.term_count} rincian • ${date(row.termin_start)} s.d. ${date(row.termin_end)}`);
        const container = $('#tagihan-detail-fields').empty();
        if (detailRequest) detailRequest.abort();
        container.append($('<div>').addClass('col-12 text-muted').text('Memuat rincian termin…'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('tagihan-detail-modal')).show();
        const url = @json(route('infrastruktur.recurring-tagihan-ipas.show', ['site' => 0])).replace(/\/0$/, `/${row.id}`);
        detailRequest = $.getJSON(url).done(({ data }) => {
            container.empty();
            data.forEach((term) => {
                const details = term.source_details || {};
                const section = $('<section>').addClass('col-12 border-bottom pb-3');
                section.append($('<h3>').addClass('fs-6').text(`Termin ${term.termin || '-'} • Periode ${term.periode_ke || '-'} • ${date(term.termin_start)} s.d. ${date(term.termin_end)}`));
                section.append($('<p>').addClass('small mb-2').text([
                    term.tp, term.contract_type,
                    `Kontrak: ${details.contract_no || '-'} / Siklus: ${details.infra_contract || '-'}`,
                    `Nominal: ${term.amount == null ? '-' : new Intl.NumberFormat('id-ID').format(term.amount)}`,
                    `Batch: ${term.batch_name || '-'}`,
                ].filter(Boolean).join(' • ')));
                const source = $('<details>').append($('<summary>').addClass('small').text('Lihat detail sumber / PO / invoice'));
                const fields = $('<div>').addClass('row g-3 mt-1');
                Object.entries(details).forEach(([key, value]) => {
                    if (value === null || value === '') return;
                    const field = $('<div>').addClass('col-12 col-md-6 col-xl-4');
                    field.append($('<div>').addClass('small text-muted').text(labels[key] || key.replaceAll('_', ' ')));
                    field.append($('<div>').addClass('text-break').text(String(value)));
                    fields.append(field);
                });
                section.append(source.append(fields));
                container.append(section);
            });
            if (!data.length) container.append($('<div>').addClass('col-12 text-muted').text('Belum ada rincian termin.'));
        }).fail((xhr, status) => {
            if (status !== 'abort') container.empty().append($('<div>').addClass('col-12 text-danger').text('Rincian gagal dimuat. Tutup dan buka kembali site untuk mencoba lagi.'));
        });
    });

    $('#filter-tp').on('change', () => table.ajax.reload());
    $('#page-size').on('change', function () { table.page.len(parseInt($(this).val())).draw(); });
});
</script>
@endpush
