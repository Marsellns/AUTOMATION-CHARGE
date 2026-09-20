{{-- Form fields partial — reusable untuk edit (dan create di masa depan) --}}
<div class="row g-3">
    {{-- === Identitas Site === --}}
    <div class="col-12"><h6 class="text-uppercase text-body-secondary border-bottom pb-1 mb-0">Identitas Site</h6></div>

    <div class="col-md-4">
        <label for="site_code" class="form-label">Site ID <span class="text-danger">*</span></label>
        <input type="text" id="site_code" name="site_code" value="{{ old('site_code', $sewaLahan->site_code) }}"
               class="form-control @error('site_code') is-invalid @enderror" required>
        @error('site_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="site_name" class="form-label">Site Name</label>
        <input type="text" id="site_name" name="site_name" value="{{ old('site_name', $sewaLahan->site_name) }}"
               class="form-control @error('site_name') is-invalid @enderror">
        @error('site_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="tahun_renewal" class="form-label">Tahun Renewal</label>
        <input type="number" id="tahun_renewal" name="tahun_renewal"
               value="{{ old('tahun_renewal', $sewaLahan->tahun_renewal) }}"
               class="form-control @error('tahun_renewal') is-invalid @enderror" min="2000" max="2099">
        @error('tahun_renewal')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- === Status === --}}
    <div class="col-12 mt-3"><h6 class="text-uppercase text-body-secondary border-bottom pb-1 mb-0">Status</h6></div>

    <div class="col-md-6">
        <label for="status_dokumen" class="form-label">Status Dokumen</label>
        <select id="status_dokumen" name="status_dokumen" class="form-select @error('status_dokumen') is-invalid @enderror">
            <option value="">— pilih —</option>
            @foreach (\App\Models\SewaLahanRenewal::STATUS_DOKUMEN as $status)
                <option value="{{ $status }}" @selected(old('status_dokumen', $sewaLahan->status_dokumen) === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status_dokumen')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="status_perpanjangan" class="form-label">Status Perpanjangan</label>
        <input type="text" id="status_perpanjangan" name="status_perpanjangan"
               value="{{ old('status_perpanjangan', $sewaLahan->status_perpanjangan) }}"
               class="form-control @error('status_perpanjangan') is-invalid @enderror">
        @error('status_perpanjangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- === PKS Baru === --}}
    <div class="col-12 mt-3"><h6 class="text-uppercase text-body-secondary border-bottom pb-1 mb-0">PKS Baru</h6></div>

    <div class="col-md-4">
        <label for="no_pks_baru" class="form-label">No PKS Baru</label>
        <input type="text" id="no_pks_baru" name="no_pks_baru" value="{{ old('no_pks_baru', $sewaLahan->no_pks_baru) }}"
               class="form-control @error('no_pks_baru') is-invalid @enderror">
        @error('no_pks_baru')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="start_date_baru" class="form-label">Start Date Baru</label>
        <input type="date" id="start_date_baru" name="start_date_baru"
               value="{{ old('start_date_baru', $sewaLahan->start_date_baru?->format('Y-m-d')) }}"
               class="form-control @error('start_date_baru') is-invalid @enderror">
        @error('start_date_baru')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="end_date_baru" class="form-label">End Date Baru</label>
        <input type="date" id="end_date_baru" name="end_date_baru"
               value="{{ old('end_date_baru', $sewaLahan->end_date_baru?->format('Y-m-d')) }}"
               class="form-control @error('end_date_baru') is-invalid @enderror">
        @error('end_date_baru')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="harga_baru" class="form-label">Harga Baru</label>
        <input type="number" step="0.01" id="harga_baru" name="harga_baru"
               value="{{ old('harga_baru', $sewaLahan->harga_baru) }}"
               class="form-control @error('harga_baru') is-invalid @enderror">
        @error('harga_baru')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="total_harga_baru" class="form-label">Total Harga Baru</label>
        <input type="number" step="0.01" id="total_harga_baru" name="total_harga_baru"
               value="{{ old('total_harga_baru', $sewaLahan->total_harga_baru) }}"
               class="form-control @error('total_harga_baru') is-invalid @enderror">
        @error('total_harga_baru')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- === BAK & SIP === --}}
    <div class="col-12 mt-3"><h6 class="text-uppercase text-body-secondary border-bottom pb-1 mb-0">BAK & SIP</h6></div>

    <div class="col-md-3">
        <label for="no_bak_baru" class="form-label">No BAK Baru</label>
        <input type="text" id="no_bak_baru" name="no_bak_baru" value="{{ old('no_bak_baru', $sewaLahan->no_bak_baru) }}"
               class="form-control @error('no_bak_baru') is-invalid @enderror">
        @error('no_bak_baru')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label for="tgl_bak_baru" class="form-label">Tgl BAK Baru</label>
        <input type="date" id="tgl_bak_baru" name="tgl_bak_baru"
               value="{{ old('tgl_bak_baru', $sewaLahan->tgl_bak_baru?->format('Y-m-d')) }}"
               class="form-control @error('tgl_bak_baru') is-invalid @enderror">
        @error('tgl_bak_baru')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label for="no_sip" class="form-label">No SIP</label>
        <input type="text" id="no_sip" name="no_sip" value="{{ old('no_sip', $sewaLahan->no_sip) }}"
               class="form-control @error('no_sip') is-invalid @enderror">
        @error('no_sip')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label for="tgl_terima_sip" class="form-label">Tgl Terima SIP</label>
        <input type="date" id="tgl_terima_sip" name="tgl_terima_sip"
               value="{{ old('tgl_terima_sip', $sewaLahan->tgl_terima_sip?->format('Y-m-d')) }}"
               class="form-control @error('tgl_terima_sip') is-invalid @enderror">
        @error('tgl_terima_sip')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- === Negosiasi === --}}
    <div class="col-12 mt-3"><h6 class="text-uppercase text-body-secondary border-bottom pb-1 mb-0">Penawaran & Negosiasi</h6></div>

    @for ($i = 1; $i <= 3; $i++)
    <div class="col-md-3">
        <label for="penawaran_{{ $i }}" class="form-label">Penawaran {{ $i }}</label>
        <input type="number" step="0.01" id="penawaran_{{ $i }}" name="penawaran_{{ $i }}"
               value="{{ old("penawaran_{$i}", $sewaLahan->{"penawaran_{$i}"}) }}"
               class="form-control @error("penawaran_{$i}") is-invalid @enderror">
        @error("penawaran_{$i}")<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label for="nego_{{ $i }}" class="form-label">Nego {{ $i }}</label>
        <input type="number" step="0.01" id="nego_{{ $i }}" name="nego_{{ $i }}"
               value="{{ old("nego_{$i}", $sewaLahan->{"nego_{$i}"}) }}"
               class="form-control @error("nego_{$i}") is-invalid @enderror">
        @error("nego_{$i}")<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @endfor

    {{-- === PKS Lama === --}}
    <div class="col-12 mt-3"><h6 class="text-uppercase text-body-secondary border-bottom pb-1 mb-0">PKS Lama</h6></div>

    <div class="col-md-3">
        <label for="no_pks_lama" class="form-label">No PKS Lama</label>
        <input type="text" id="no_pks_lama" name="no_pks_lama" value="{{ old('no_pks_lama', $sewaLahan->no_pks_lama) }}"
               class="form-control @error('no_pks_lama') is-invalid @enderror">
        @error('no_pks_lama')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label for="start_date_lama" class="form-label">Start Date Lama</label>
        <input type="date" id="start_date_lama" name="start_date_lama"
               value="{{ old('start_date_lama', $sewaLahan->start_date_lama?->format('Y-m-d')) }}"
               class="form-control @error('start_date_lama') is-invalid @enderror">
        @error('start_date_lama')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label for="end_date_lama" class="form-label">End Date Lama</label>
        <input type="date" id="end_date_lama" name="end_date_lama"
               value="{{ old('end_date_lama', $sewaLahan->end_date_lama?->format('Y-m-d')) }}"
               class="form-control @error('end_date_lama') is-invalid @enderror">
        @error('end_date_lama')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label for="harga_lama" class="form-label">Harga Lama</label>
        <input type="number" step="0.01" id="harga_lama" name="harga_lama"
               value="{{ old('harga_lama', $sewaLahan->harga_lama) }}"
               class="form-control @error('harga_lama') is-invalid @enderror">
        @error('harga_lama')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- === Lain-lain === --}}
    <div class="col-12 mt-3"><h6 class="text-uppercase text-body-secondary border-bottom pb-1 mb-0">Lain-lain</h6></div>

    <div class="col-12">
        <label for="keterangan" class="form-label">Keterangan</label>
        <textarea id="keterangan" name="keterangan" rows="3"
                  class="form-control @error('keterangan') is-invalid @enderror">{{ old('keterangan', $sewaLahan->keterangan) }}</textarea>
        @error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
