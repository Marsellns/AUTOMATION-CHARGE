<div class="aksi-cell d-flex gap-1">
    <a href="{{ route('infrastruktur.upload-file.edit', $file) }}" class="btn btn-warning btn-sm">Edit</a>
    <form method="POST" action="{{ route('infrastruktur.upload-file.destroy', $file) }}"
          onsubmit="return confirm('Hapus file {{ basename($file->file_path) }}?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
    </form>
</div>
