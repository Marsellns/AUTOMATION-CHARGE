<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Site ID <span class="text-danger">*</span></label>
        <input type="text" name="site_code" class="form-control form-control-sm" value="{{ old('site_code', $bapss->site_code) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Site Name</label>
        <input type="text" name="site_name" class="form-control form-control-sm" value="{{ old('site_name', $bapss->site_name) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Tgl BAPSS</label>
        <input type="date" name="tgl_bapss" class="form-control form-control-sm" value="{{ old('tgl_bapss', $bapss->tgl_bapss?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Tgl Dismantle</label>
        <input type="date" name="tgl_dismantle" class="form-control form-control-sm" value="{{ old('tgl_dismantle', $bapss->tgl_dismantle?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Remark</label>
        <input type="text" name="remark" class="form-control form-control-sm" value="{{ old('remark', $bapss->remark) }}">
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Upload PDF BAPSS</label>
        <input type="file" name="pdf_bapss_file" class="form-control form-control-sm" accept=".pdf">
        @if ($bapss->pdf_bapss && $bapss->pdf_bapss !== '-')
            <small class="text-body-secondary">File saat ini: <a href="{{ asset('storage/' . $bapss->pdf_bapss) }}" target="_blank">{{ basename($bapss->pdf_bapss) }}</a></small>
        @endif
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Upload PDF BA Dismantle</label>
        <input type="file" name="pdf_ba_dismantle_file" class="form-control form-control-sm" accept=".pdf">
        @if ($bapss->pdf_ba_dismantle && $bapss->pdf_ba_dismantle !== '-')
            <small class="text-body-secondary">File saat ini: <a href="{{ asset('storage/' . $bapss->pdf_ba_dismantle) }}" target="_blank">{{ basename($bapss->pdf_ba_dismantle) }}</a></small>
        @endif
    </div>
</div>
