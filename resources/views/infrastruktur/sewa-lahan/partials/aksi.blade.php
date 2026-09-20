<div class="aksi-cell d-flex gap-1">
    <a href="{{ route('infrastruktur.sewa-lahan.edit', $sewaLahan) }}" class="btn btn-warning btn-sm">Edit</a>
    <a href="{{ route('infrastruktur.sewa-lahan.cetak-sip', $sewaLahan) }}" target="_blank" class="btn btn-outline-brand btn-sm">Cetak SIP</a>
    <form method="POST" action="{{ route('infrastruktur.sewa-lahan.destroy', $sewaLahan) }}"
          onsubmit="return confirm('Hapus sewa lahan {{ $sewaLahan->site_code }}?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
    </form>
</div>
