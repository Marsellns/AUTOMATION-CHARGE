<div class="row g-3">
    <div class="col-12">
        <label class="form-label fw-semibold">Keterangan <span class="text-danger">*</span></label>
        <textarea name="keterangan" class="form-control form-control-sm" rows="3" required>{{ old('keterangan', $file->keterangan ?? '') }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">File PDF <span class="text-danger">{{ isset($file) && $file->exists ? '' : '*' }}</span></label>
        <input type="file" name="file" class="form-control form-control-sm" accept=".pdf" {{ isset($file) && $file->exists ? '' : 'required' }}>
        <small class="text-body-secondary">Hanya file .pdf, maks 10 MB.</small>
        @if (isset($file) && $file->file_path)
            <div class="mt-1">
                <small class="text-body-secondary">File saat ini: <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank">{{ basename($file->file_path) }}</a></small>
            </div>
        @endif
    </div>
</div>
