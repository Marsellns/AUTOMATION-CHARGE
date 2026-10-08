<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PrivateDocumentStorage
{
    public static function move(iterable $paths, string $prefix): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('private');

        foreach ($paths as $path) {
            if (! is_string($path) || ! str_starts_with($path, $prefix) || str_contains($path, '..') || ! $public->exists($path)) {
                continue;
            }
            $contents = $public->get($path);
            if (! $private->exists($path) && ! $private->put($path, $contents)) {
                throw new \RuntimeException('Gagal menyalin dokumen ke penyimpanan privat.');
            }
            if (! $private->exists($path) || ! hash_equals(hash('sha256', $contents), hash('sha256', $private->get($path)))) {
                throw new \RuntimeException('Isi salinan dokumen berbeda. Dokumen publik dipertahankan untuk pemulihan.');
            }
            if (! $public->delete($path)) {
                throw new \RuntimeException('Salinan privat terverifikasi, tetapi dokumen publik gagal dihapus.');
            }
        }
    }
}
