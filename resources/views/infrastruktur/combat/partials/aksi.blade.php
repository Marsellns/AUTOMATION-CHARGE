<div class="aksi-cell d-flex gap-1">
    <a href="{{ route('infrastruktur.combat.edit', $combat) }}" class="btn btn-warning btn-sm">Edit</a>
    <form method="POST" action="{{ route('infrastruktur.combat.destroy', $combat) }}"
          onsubmit="return confirm('Hapus combat site {{ $combat->site_code }}?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
    </form>
</div>
