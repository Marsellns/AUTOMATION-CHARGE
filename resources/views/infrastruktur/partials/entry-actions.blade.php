@role('admin')
<div class="d-flex flex-wrap gap-2" aria-label="Pilihan tambah data">
    <button type="button" class="btn btn-sm btn-brand" data-bs-toggle="modal" data-bs-target="#infrastructureManualModal" aria-haspopup="dialog">Tambah Manual</button>
    <a href="{{ route('infrastruktur.upload', $dataset) }}" class="btn btn-sm btn-outline-brand">Upload Excel</a>
</div>
@endrole
