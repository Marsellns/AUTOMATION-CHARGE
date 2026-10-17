@role('admin')
<a href="{{ route('operations.create', $dataset) }}" class="btn btn-outline-brand btn-sm">{{ $entryLabel ?? 'Tambah / Upload Data' }}</a>
@endrole
