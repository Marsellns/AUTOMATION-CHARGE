<div class="aksi-cell d-flex gap-1">
    <a href="{{ route('po-hq.edit', $po) }}" class="btn btn-warning btn-sm">Edit</a>
    <form method="POST" action="{{ route('po-hq.destroy', $po) }}"
          onsubmit="return confirm('Hapus PO {{ $po->po_number }}?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
    </form>
</div>
