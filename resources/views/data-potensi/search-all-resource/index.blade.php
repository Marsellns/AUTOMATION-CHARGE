@extends('layouts.app')

@section('title', 'Search All Resource — Data Potensi — SIMASTER')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Search All Resource</h1>
    </div>

    <div class="card mb-3" data-simaster-filter-panel="Filter Search All Resource">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <label for="page-size" class="form-label mb-0 small fw-semibold text-nowrap">Show:</label>
                <select id="page-size" class="form-select form-select-sm" style="width:auto">
                    @foreach ([10, 20, 40, 80, 100, 5000] as $size)
                        <option value="{{ $size }}" @selected($size === 10)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label for="owner-filter" class="form-label mb-0 small fw-semibold text-nowrap">Site Owner:</label>
                <select id="owner-filter" class="form-select form-select-sm" style="max-width: 320px">
                    <option value="">All</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner }}">{{ $owner }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label for="city-filter" class="form-label mb-0 small fw-semibold text-nowrap">City:</label>
                <select id="city-filter" class="form-select form-select-sm" style="max-width: 280px">
                    <option value="">All</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city }}">{{ $city }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="search-resource-table" class="display align-middle text-nowrap" style="width:100%; cursor:pointer">
                <thead><tr>
                    <th>No</th><th>Site ID</th><th>DAPOT–Site Name</th><th>DAPOT–Class</th>
                    <th>DAPOT–City</th><th>DAPOT–NOP</th><th>DAPOT–Coverage</th><th>DAPOT–PLN</th>
                    <th>DAPOT–Capacity</th><th>DAPOT–ID Pel</th><th>ANT–Site</th><th>ANT–Site Owner</th>
                    <th>ANT–RTP</th><th>ANT–Type</th><th>ANT–Tgl Update</th><th>ANT–Alamat</th>
                    <th>REV–Site</th><th>REV–Revenue</th><th>COST–Cost</th><th>Profit Value</th><th>Profit Status</th>
                </tr></thead>
            </table>
        </div>
    </div>

    <div class="modal fade" id="search-resource-detail-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Search Resource: <span id="search-resource-detail-title"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered align-middle mb-0"><tbody id="search-resource-detail-body"></tbody></table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    const value = (data) => data === null || data === undefined || data === ''
        ? '-' : $('<div>').text(data).html();

    const table = new DataTable('#search-resource-table', {
        processing: true,
        serverSide: true,
        scrollX: true,
        pageLength: 10,
        lengthChange: false,
        ajax: {
            url: @json(route('data-potensi.search-all-resource.data')),
            data: data => {
                data.site_owner = $('#owner-filter').val();
                data.city = $('#city-filter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'no', orderable: false, searchable: false },
            { data: 'site_id', name: 'site_id', render: value },
            { data: 'site_name', name: 'site_name', render: value },
            { data: 'site_class', name: 'site_class', render: value },
            { data: 'city', name: 'city', render: value },
            { data: 'nop', name: 'nop', render: value },
            { data: 'coverage_type', name: 'coverage_type', render: value },
            { data: 'pln_connection', name: 'pln_connection', render: value },
            { data: 'capacity', name: 'capacity', render: value },
            { data: 'id_pel', name: 'id_pel', render: value },
            { data: 'ant_site', name: 'ant_site', render: value },
            { data: 'ant_site_owner', name: 'ant_site_owner', render: value },
            { data: 'ant_rtp', name: 'ant_rtp', render: value },
            { data: 'ant_type', name: 'ant_type', render: value },
            { data: 'ant_tgl_update', name: 'ant_tgl_update', render: value },
            { data: 'ant_alamat', name: 'ant_alamat', render: value },
            { data: 'rev_site', name: 'rev_site', render: value },
            { data: 'revenue', name: 'revenue', render: value },
            { data: 'cost', name: 'cost', render: value },
            { data: 'profit_value', name: 'profit_value', render: value },
            { data: 'profit_status', name: 'profit_status', render: value }
        ],
        order: []
    });

    $('#page-size').on('change', function () {
        table.page.len(parseInt($(this).val(), 10)).draw();
    });
    $('#owner-filter').on('change', function () { table.ajax.reload(); });
    $('#city-filter').on('change', function () { table.ajax.reload(); });

    $('#search-resource-table tbody').on('click', 'tr', function () {
        const row = table.row(this).data();
        if (!row || !row.site_id) return;
        fetch(`${@json(url('data-potensi/search-all-resource'))}/${encodeURIComponent(row.site_id)}`, {
            headers: { Accept: 'application/json' }
        })
            .then(response => {
                if (!response.ok) throw new Error('Gagal memuat detail Search All Resource.');
                return response.json();
            })
            .then(({ data }) => {
                $('#search-resource-detail-title').text(data.site_id || 'Detail Resource');
                const rows = [
                    ['DAPOT–ID Pel', data.id_pel], ['ANT–Site', data.ant_site],
                    ['ANT–RTP', data.ant_rtp], ['ANT–Type', data.ant_type],
                    ['ANT–Tgl Update', data.ant_tgl_update], ['ANT–Alamat', data.ant_alamat],
                    ['REV–Site', data.rev_site], ['REV–Revenue', data.revenue],
                    ['COST–Cost', data.cost], ['Profit Value', data.profit_value],
                    ['Profit Status', data.profit_status]
                ];
                $('#search-resource-detail-body').html(rows.map(([label, item]) =>
                    `<tr><th>${label}</th><td>${value(item)}</td></tr>`).join(''));
                bootstrap.Modal.getOrCreateInstance('#search-resource-detail-modal').show();
            })
            .catch(error => window.alert(error.message));
    });
});
</script>
@endpush
