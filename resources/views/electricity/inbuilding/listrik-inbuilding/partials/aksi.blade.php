<div class="d-flex align-items-center gap-1 justify-content-end">
    <button type="button"
            class="btn btn-outline-warning btn-sm btn-edit-inbuilding"
            data-id="{{ $item->id }}"
            title="Edit Data">
        Edit
    </button>

    <form action="{{ route('electricity.inbuilding.listrik-inbuilding.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data Listrik Inbuilding site {{ $item->site_id }}?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus Data">
            Hapus
        </button>
    </form>
</div>
