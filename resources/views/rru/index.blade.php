@extends('layouts.app')

@section('title', 'Equipment Relocation')

@section('page-title', 'Equipment Relocation')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/equipment-relocation.css') }}">
@endpush

@section('content')

<div class="page-content" id="equipmentRelocationPage" style="width:100%;">

    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->
    <div class="page-header">

        <div>
            <p class="page-eyebrow">EQUIPMENT RELOCATION</p>

            <h1>Equipment Relocation</h1>

            <p class="page-description">
                Monitoring seluruh equipment (RU &amp; BBP), status eligibility (Safe/Not Safe
                to Reloc), serta progress relokasinya antar site.
            </p>
        </div>

        <div class="header-actions">

            <button
                type="button"
                class="btn-primary er-filter-trigger"
                id="erOpenFilterBtn"
            >
                <i class="fa-solid fa-filter"></i>
                Filter
            </button>

        </div>

    </div>

    <div id="erLoadState" class="er-load-state" role="status">Memuat data inventaris dan diagram equipment…</div>


    <!-- =====================================================
         KPI CARDS
    ====================================================== -->
    <div class="kpi-grid">

        <!-- TOTAL BBP -->
        <div
            class="kpi-card clickable-kpi"
            data-er-category="BBP:All"
            role="button"
            tabindex="0"
        >
            <div class="kpi-icon blue">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>

            <div class="kpi-content">
                <span>Total BBP</span>
                <h2 id="erKpiBbpTotal">0</h2>
                <small>Seluruh equipment BBP (Safe &amp; Not Safe)</small>
            </div>
        </div>


        <!-- TOTAL BBP SAFE TO RELOC -->
        <div
            class="kpi-card clickable-kpi"
            data-er-category="BBP:SafeToReloc"
            role="button"
            tabindex="0"
        >
            <div class="kpi-icon green">
                <i class="fa-solid fa-shield-halved"></i>
            </div>

            <div class="kpi-content">
                <span>Total BBP Safe to Reloc</span>
                <h2 id="erKpiBbpSafe">0</h2>
                <small>Equipment BBP not utilize &amp; aman direlokasi</small>
            </div>
        </div>


        <!-- TOTAL RU -->
        <div
            class="kpi-card clickable-kpi"
            data-er-category="RU:All"
            role="button"
            tabindex="0"
        >
            <div class="kpi-icon blue">
                <i class="fa-solid fa-satellite-dish"></i>
            </div>

            <div class="kpi-content">
                <span>Total RU</span>
                <h2 id="erKpiRuTotal">0</h2>
                <small>Seluruh equipment RU (Safe &amp; Not Safe)</small>
            </div>
        </div>


        <!-- TOTAL RU SAFE TO RELOC -->
        <div
            class="kpi-card clickable-kpi"
            data-er-category="RU:SafeToReloc"
            role="button"
            tabindex="0"
        >
            <div class="kpi-icon green">
                <i class="fa-solid fa-shield-halved"></i>
            </div>

            <div class="kpi-content">
                <span>Total RU Safe to Reloc</span>
                <h2 id="erKpiRuSafe">0</h2>
                <small>Equipment RU not utilize &amp; aman direlokasi</small>
            </div>
        </div>


        <!-- ON GOING -->
        <div
            class="kpi-card clickable-kpi"
            data-er-category="All:ON GOING"
            role="button"
            tabindex="0"
        >
            <div class="kpi-icon orange">
                <i class="fa-solid fa-truck-fast"></i>
            </div>

            <div class="kpi-content">
                <span>On Going</span>
                <h2 id="erKpiOnGoing">0</h2>
                <small>Proses relokasi berjalan (RU &amp; BBP)</small>
            </div>
        </div>

    </div>


    <!-- =====================================================
         INSIGHT CHARTS (ROW 1)
    ====================================================== -->
    <div
        class="dashboard-grid"
        style="
            display:grid;
            grid-template-columns:repeat(2, minmax(0,1fr));
            gap:20px;
            width:100%;
        "
    >

        <!-- EQUIPMENT PER NOP (ALL STATUS - Safe & Not Safe) -->
        <div
            class="dashboard-card"
            style="
                height:360px !important;
                min-height:360px !important;
                max-height:none !important;
                padding:22px;
                box-sizing:border-box;
                display:flex;
                flex-direction:column;
                overflow:hidden;
            "
        >

            <div class="card-header">
                <div>
                    <h3>Equipment per NOP</h3>

                    <p>Distribusi seluruh equipment (RU vs BBP) per NOP, baik Safe maupun Not Safe to Reloc.</p>
                </div>
            </div>

            <div
                class="chart-container"
                style="
                    position:relative;
                    width:100%;
                    height:285px !important;
                    min-height:285px !important;
                    flex:1;
                "
            >
                <canvas id="erNopChart"></canvas>
            </div>

        </div>


        <!-- PROGRESS DISTRIBUTION (ALL EQUIPMENT) -->
        <div
            class="dashboard-card"
            style="
                height:360px !important;
                min-height:360px !important;
                max-height:none !important;
                padding:22px;
                box-sizing:border-box;
                display:flex;
                flex-direction:column;
                overflow:hidden;
            "
        >

            <div class="card-header">
                <div>
                    <h3>Status Relokasi</h3>

                    <p>Perbandingan status assignment &amp; progress relokasi untuk seluruh equipment.</p>
                </div>
            </div>

            <div
                class="chart-container"
                style="
                    position:relative;
                    width:100%;
                    height:285px !important;
                    min-height:285px !important;
                    flex:1;
                "
            >
                <canvas id="erStatusChart"></canvas>
            </div>

        </div>

    </div>


    <!-- =====================================================
        INSIGHT CHARTS - SAFE TO RELOC BY TYPE
        ===================================================== -->

    <!-- RU TOP 10 -->
    <div
        class="dashboard-card"
        style="
            width:100%;
            height:380px !important;
            min-height:380px !important;
            max-height:none !important;
            padding:22px;
            box-sizing:border-box;
            display:flex;
            flex-direction:column;
            overflow:hidden;
            margin-top:20px;
        "
    >
        <div class="card-header">
            <div>
                <h3>Equipment by Type — RU</h3>

                <p>Top 10 tipe equipment RU (seluruh status, Safe &amp; Not Safe).</p>
            </div>
        </div>

        <div
            class="chart-container"
            style="
                position:relative;
                width:100%;
                height:300px !important;
                min-height:300px !important;
                flex:1;
            "
        >
            <canvas id="erRuTypeChart"></canvas>
        </div>
    </div>


    <!-- BBP TOP 10 -->
    <div
        class="dashboard-card"
        style="
            width:100%;
            height:380px !important;
            min-height:380px !important;
            max-height:none !important;
            padding:22px;
            box-sizing:border-box;
            display:flex;
            flex-direction:column;
            overflow:hidden;
            margin-top:20px;
        "
    >
        <div class="card-header">
            <div>
                <h3>Equipment by Type — BBP</h3>

                <p>Top 10 tipe equipment BBP (seluruh status, Safe &amp; Not Safe).</p>
            </div>
        </div>

        <div
            class="chart-container"
            style="
                position:relative;
                width:100%;
                height:300px !important;
                min-height:300px !important;
                flex:1;
            "
        >
            <canvas id="erBbpTypeChart"></canvas>
        </div>
    </div>


    <!-- =====================================================
         DETAIL / MONITORING TABLE
    ====================================================== -->
    <div
        class="dashboard-card data-table-section"
        id="erTableSection"
        style="
            width:100%;
            height:auto !important;
            min-height:0 !important;
            max-height:none !important;
            overflow:visible !important;
            margin-top:20px;
        "
    >

        <div class="table-header">

            <div>
                <span class="section-eyebrow">EQUIPMENT RELOCATION</span>

                <h3 id="erTableTitle">
                    Semua Equipment (Safe &amp; Not Safe)
                </h3>

                <p id="erTableDescription">
                    Menampilkan seluruh equipment (RU &amp; BBP), baik Safe maupun Not Safe to Reloc.
                </p>
            </div>

        </div>


        <div class="table-toolbar">

            <div class="table-toolbar-left">

                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        type="text"
                        id="erSearch"
                        placeholder="Cari Site ID, Tipe Equipment, atau Serial Number..."
                    >
                </div>

                <label class="table-rows-selector" for="erPageSizeSelect">
                    Tampilkan

                    <select id="erPageSizeSelect">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>

                    baris
                </label>

            </div>

            <div class="table-toolbar-right">

                <button
                    class="btn-secondary"
                    id="erResetFilter"
                >
                    <i class="fa-solid fa-rotate-left"></i>
                    Reset Filter
                </button>

                <button
                    class="btn-secondary"
                    id="erExportData"
                >
                    <i class="fa-solid fa-download"></i>
                    Export Data
                </button>

            </div>

        </div>


        <div class="table-info">

            <span id="erTableResultInfo">
                Menampilkan seluruh data equipment
            </span>

            <span id="erActiveFilterBadge">
                All Data
            </span>

        </div>


        <div
            class="data-table-wrapper"
            style="
                width:100%;
                overflow-x:auto !important;
                overflow-y:visible !important;
                max-height:none !important;
            "
        >

            <table
                class="data-table"
                style="width:100%; min-width:1450px;"
            >

                <thead>
                    <tr>
                        <th>Aksi</th>
                        <th>Site ID</th>
                        <th>NOP</th>
                        <th>Tipe RU/BBP</th>
                        <th>Serial Number</th>
                        <th>Utilisasi</th>
                        <th>Safe to Reloc</th>
                        <th>Status</th>
                        <th>Donor/Acceptor</th>
                        <th>Site Target/Source</th>
                        <th>PIC</th>
                        <th>Progress</th>
                    </tr>
                </thead>

                <tbody id="erTableBody"></tbody>

            </table>

        </div>


        <!-- PAGINATION -->
        <div class="table-pagination-toolbar table-pagination-toolbar--end">

            <div
                class="table-pagination"
                id="erPagination"
            >

                <button
                    type="button"
                    class="btn-secondary"
                    id="erPrevPage"
                >
                    <i class="fa-solid fa-chevron-left"></i>
                </button>

                <span id="erPaginationInfo">
                    Page 1 of 1
                </span>

                <button
                    type="button"
                    class="btn-secondary"
                    id="erNextPage"
                >
                    <i class="fa-solid fa-chevron-right"></i>
                </button>

            </div>

        </div>

    </div>


    <!-- =====================================================
         GLOBAL FILTER OVERLAY
    ====================================================== -->
    <div
        class="global-filter-overlay"
        id="erFilterOverlay"
        aria-hidden="true"
    >

        <div
            class="global-filter-panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erFilterTitle"
        >

            <div class="global-filter-header">

                <h3
                    class="global-filter-title"
                    id="erFilterTitle"
                >
                    Filter Equipment Relocation
                </h3>

                <button
                    type="button"
                    class="global-filter-close"
                    id="erCloseFilterBtn"
                    aria-label="Tutup filter"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>

            </div>


            <div class="global-filter-body">

                <!-- NOP -->
                <div class="global-filter-group">
                    <label class="global-filter-label">NOP</label>
                    <select id="erFilterNop" class="global-filter-select">
                        <option value="All">Semua NOP</option>
                    </select>
                </div>

                <!-- EQUIPMENT GROUP -->
                <div class="global-filter-group">
                    <label class="global-filter-label">Kategori Equipment</label>
                    <select id="erFilterGroup" class="global-filter-select">
                        <option value="All">Semua Kategori</option>
                        <option value="RU">RU (Radio Unit)</option>
                        <option value="BBP">BBP (Baseband)</option>
                    </select>
                </div>

                <!-- EQUIPMENT TYPE -->
                <div class="global-filter-group">
                    <label class="global-filter-label">Tipe Equipment</label>
                    <select id="erFilterType" class="global-filter-select">
                        <option value="All">Semua Tipe</option>
                    </select>
                </div>

                <!-- SAFE TO RELOC (ELIGIBILITY - separate from Progress) -->
                <!-- Fixed, normalized 2-value vocabulary (SAFE / NOT SAFE),
                     not populated from raw source text - see equipment-relocation.js -->
                <div class="global-filter-group">
                    <label class="global-filter-label">Safe to Reloc</label>
                    <select id="erFilterSafe" class="global-filter-select">
                        <option value="All">Semua Eligibility</option>
                        <option value="SAFE">SAFE</option>
                        <option value="NOT SAFE">NOT SAFE</option>
                    </select>
                </div>

                <!-- STATUS (RELOCATION PROGRESS) -->
                <div class="global-filter-group">
                    <label class="global-filter-label">Status Progress</label>
                    <select id="erFilterStatus" class="global-filter-select">
                        <option value="All">Semua Status</option>
                        <option value="Belum Diisi">Belum Diisi</option>
                        <option value="NOT YET">Not Yet</option>
                        <option value="ON GOING">On Going</option>
                        <option value="DONE">Done</option>
                    </select>
                </div>

                <!-- PIC -->
                <div class="global-filter-group">
                    <label class="global-filter-label">PIC</label>
                    <select id="erFilterPic" class="global-filter-select">
                        <option value="All">Semua PIC</option>
                        <option value="NOP BOGOR">NOP BOGOR</option>
                        <option value="NOP BEKASI">NOP BEKASI</option>
                        <option value="NOP KARAWANG">NOP KARAWANG</option>
                        <option value="NBAE">NBAE</option>
                    </select>
                </div>

            </div>


            <div class="global-filter-footer">

                <button
                    type="button"
                    class="global-filter-btn-reset"
                    id="erClearGlobalFilterBtn"
                >
                    Reset
                </button>

                <button
                    type="button"
                    class="global-filter-btn-apply"
                    id="erApplyGlobalFilterBtn"
                >
                    Terapkan Filter
                </button>

            </div>

        </div>

    </div>

</div>

@endsection

@push('scripts')
    <script>
        window.__equipmentRelocationUrls = {
            inventory: @json(asset('data/equipment_relocation_inventory.json')),
            monitoring: @json(route('equipment-relocation.relocation-data')),
            save: @json(route('equipment-relocation.relocation-data.store')),
            destroy: @json(route('equipment-relocation.relocation-data.destroy'))
        };
        window.__equipmentRelocationCanEdit = @json(auth()->user()->hasRole('admin') || auth()->user()->getRoleNames()->contains(fn ($role) => str_starts_with(strtolower($role), 'manager_') || str_starts_with(strtolower($role), 'manager ')));
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
    <script src="{{ asset('assets/js/equipment-relocation-data.js') }}"></script>
    <script src="{{ asset('assets/js/equipment-relocation.js') }}"></script>
@endpush
