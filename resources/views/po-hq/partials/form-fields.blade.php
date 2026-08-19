<div class="row g-3">
    <div class="col-md-6">
        <label for="po_number" class="form-label">PO Number <span class="text-danger">*</span></label>
        <input type="text" id="po_number" name="po_number" value="{{ old('po_number', $po->po_number) }}"
               class="form-control @error('po_number') is-invalid @enderror" required>
        @error('po_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="agreement_number" class="form-label">Agreement Number</label>
        <input type="text" id="agreement_number" name="agreement_number" value="{{ old('agreement_number', $po->agreement_number) }}"
               class="form-control @error('agreement_number') is-invalid @enderror">
        @error('agreement_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="vendor_name" class="form-label">Vendor Name <span class="text-danger">*</span></label>
        <input type="text" id="vendor_name" name="vendor_name" value="{{ old('vendor_name', $po->vendor_name) }}"
               class="form-control @error('vendor_name') is-invalid @enderror" required>
        @error('vendor_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label for="expense_type" class="form-label">Capex/Opex <span class="text-danger">*</span></label>
        <select id="expense_type" name="expense_type" class="form-select @error('expense_type') is-invalid @enderror" required>
            <option value="">— pilih —</option>
            @foreach (\App\Models\PoHq::EXPENSE_TYPES as $type)
                <option value="{{ $type }}" @selected(old('expense_type', $po->expense_type) === $type)>{{ $type }}</option>
            @endforeach
        </select>
        @error('expense_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="">— pilih —</option>
            @foreach (\App\Models\PoHq::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $po->status ?? 'Draft') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="location" class="form-label">Location</label>
        <input type="text" id="location" name="location" value="{{ old('location', $po->location) }}"
               class="form-control @error('location') is-invalid @enderror">
        @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea id="description" name="description" rows="3"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $po->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
