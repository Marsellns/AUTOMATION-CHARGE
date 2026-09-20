{{-- Form fields partial — reusable for edit --}}
<div class="row g-3">
    {{-- === Identitas Pelanggan === --}}
    <div class="col-12"><h6 class="text-uppercase text-body-secondary border-bottom pb-1 mb-0">Identitas Pelanggan</h6></div>

    <div class="col-md-4">
        <label for="id_pelanggan" class="form-label">ID Pelanggan <span class="text-danger">*</span></label>
        <input type="text" id="id_pelanggan" name="id_pelanggan" value="{{ old('id_pelanggan', $listrikPln->id_pelanggan) }}"
               class="form-control @error('id_pelanggan') is-invalid @enderror" required>
        @error('id_pelanggan')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="site_id" class="form-label">Site ID</label>
        <input type="text" id="site_id" name="site_id" value="{{ old('site_id', $listrikPln->site_id) }}"
               class="form-control @error('site_id') is-invalid @enderror">
        @error('site_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="site_name" class="form-label">Site Name</label>
        <input type="text" id="site_name" name="site_name" value="{{ old('site_name', $listrikPln->site_name) }}"
               class="form-control @error('site_name') is-invalid @enderror">
        @error('site_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="alamat" class="form-label">Alamat</label>
        <textarea id="alamat" name="alamat" rows="2" class="form-control @error('alamat') is-invalid @enderror">{{ old('alamat', $listrikPln->alamat) }}</textarea>
        @error('alamat')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="status_aktif_site" class="form-label">Status Aktif Site</label>
        <select id="status_aktif_site" name="status_aktif_site" class="form-select @error('status_aktif_site') is-invalid @enderror">
            <option value="">— pilih —</option>
            @foreach (\App\Models\ListrikPln::STATUS_AKTIF_SITE as $status)
                <option value="{{ $status }}" @selected(old('status_aktif_site', $listrikPln->status_aktif_site) === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status_aktif_site')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="nama_pelanggan" class="form-label">Nama Pelanggan</label>
        <input type="text" id="nama_pelanggan" name="nama_pelanggan" value="{{ old('nama_pelanggan', $listrikPln->nama_pelanggan) }}"
               class="form-control @error('nama_pelanggan') is-invalid @enderror">
        @error('nama_pelanggan')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="daya_va" class="form-label">Daya (VA)</label>
        <input type="number" id="daya_va" name="daya_va" value="{{ old('daya_va', $listrikPln->daya_va) }}"
               class="form-control @error('daya_va') is-invalid @enderror" min="0" step="1">
        @error('daya_va')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="phasa" class="form-label">Phasa</label>
        <select id="phasa" name="phasa" class="form-select @error('phasa') is-invalid @enderror">
            <option value="">— pilih —</option>
            @foreach (\App\Models\ListrikPln::PHASA as $phasa)
                <option value="{{ $phasa }}" @selected(old('phasa', $listrikPln->phasa) === $phasa)>{{ $phasa }}</option>
            @endforeach
        </select>
        @error('phasa')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="status_amr" class="form-label">Status AMR</label>
        <select id="status_amr" name="status_amr" class="form-select @error('status_amr') is-invalid @enderror">
            <option value="">— pilih —</option>
            @foreach (\App\Models\ListrikPln::STATUS_AMR as $status)
                <option value="{{ $status }}" @selected(old('status_amr', $listrikPln->status_amr) === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status_amr')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="gol_tarif" class="form-label">Golongan Tarif</label>
        <input type="text" id="gol_tarif" name="gol_tarif" value="{{ old('gol_tarif', $listrikPln->gol_tarif) }}"
               class="form-control @error('gol_tarif') is-invalid @enderror">
        @error('gol_tarif')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="unit_layanan_pln" class="form-label">Unit Layanan PLN</label>
        <input type="text" id="unit_layanan_pln" name="unit_layanan_pln" value="{{ old('unit_layanan_pln', $listrikPln->unit_layanan_pln) }}"
               class="form-control @error('unit_layanan_pln') is-invalid @enderror">
        @error('unit_layanan_pln')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="jenis_bayar" class="form-label">Jenis Bayar</label>
        <select id="jenis_bayar" name="jenis_bayar" class="form-select @error('jenis_bayar') is-invalid @enderror">
            <option value="">— pilih —</option>
            @foreach (\App\Models\ListrikPln::JENIS_BAYAR as $jenis)
                <option value="{{ $jenis }}" @selected(old('jenis_bayar', $listrikPln->jenis_bayar) === $jenis)>{{ $jenis }}</option>
            @endforeach
        </select>
        @error('jenis_bayar')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="tp_owner" class="form-label">TP Owner</label>
        <input type="text" id="tp_owner" name="tp_owner" value="{{ old('tp_owner', $listrikPln->tp_owner) }}"
               class="form-control @error('tp_owner') is-invalid @enderror">
        @error('tp_owner')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="nop" class="form-label">NOP (Nomor Objek Pajak)</label>
        <input type="text" id="nop" name="nop" value="{{ old('nop', $listrikPln->nop) }}"
               class="form-control @error('nop') is-invalid @enderror">
        @error('nop')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>