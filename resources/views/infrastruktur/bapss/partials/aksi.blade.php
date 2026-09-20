<div class="aksi-cell d-flex gap-1">
    <a href="{{ route('infrastruktur.bapss.edit', $bapss) }}" class="btn btn-warning btn-sm">Edit</a>
    <form method="POST" action="{{ route('infrastruktur.bapss.destroy', $bapss) }}"
          onsubmit="return confirm('Hapus BAPSS {{ $bapss->site_code }}?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
    </form>
</div>
