<?php

namespace App\Console\Commands;

use App\Models\Bapss;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AttachBapssDocuments extends Command
{
    protected $signature = 'dataset:attach-bapss-pdfs {manifest : JSON berisi file, type (bapss/dismantle), site_ids}';

    protected $description = 'Hubungkan PDF sumber terverifikasi ke BAPSS melalui manifest, pada disk privat';

    public function handle(): int
    {
        try {
            $manifestPath = realpath((string) $this->argument('manifest'));
            if ($manifestPath === false || ! is_file($manifestPath)) {
                throw new \RuntimeException('Manifest tidak ditemukan.');
            }
            $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($manifest) || ! array_is_list($manifest) || $manifest === []) {
                throw new \RuntimeException('Manifest harus berupa daftar yang tidak kosong.');
            }
            $plan = [];
            foreach ($manifest as $entry) {
                if (! in_array($entry['type'] ?? null, ['bapss', 'dismantle'], true) || empty($entry['site_ids']) || ! is_array($entry['site_ids'])) {
                    throw new \RuntimeException('Entri manifest tidak valid.');
                }
                $file = realpath(dirname($manifestPath).'/'.($entry['file'] ?? ''));
                $sourceDirectory = dirname($manifestPath).DIRECTORY_SEPARATOR;
                if ($file === false || ! str_starts_with($file, $sourceDirectory) || ! is_file($file) || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf') {
                    throw new \RuntimeException('PDF harus ada di dalam folder manifest.');
                }
                $stream = fopen($file, 'rb');
                if ($stream === false) {
                    throw new \RuntimeException('PDF tidak dapat dibaca.');
                }
                $signature = fread($stream, 5);
                fclose($stream);
                if ($signature !== '%PDF-') {
                    throw new \RuntimeException('Isi file bukan PDF.');
                }
                $hash = hash_file('sha256', $file);
                $target = 'bapss/'.$hash.'.pdf';
                $column = $entry['type'] === 'bapss' ? 'pdf_bapss' : 'pdf_ba_dismantle';
                foreach ($entry['site_ids'] as $siteId) {
                    $record = Bapss::query()->whereRaw('UPPER(TRIM(site_code)) = ?', [strtoupper(trim((string) $siteId))])->sole();
                    if ($record->pdfAvailable($column) && $record->pdfPath($column) !== $target) {
                        throw new \RuntimeException('Dokumen yang sudah tersedia tidak diganti: '.$record->site_code);
                    }
                    $identity = $record->id.'|'.$column;
                    if (isset($plan[$identity]) && $plan[$identity]['target'] !== $target) {
                        throw new \RuntimeException('Manifest memberikan dua dokumen berbeda untuk field yang sama.');
                    }
                    $plan[$identity] = compact('file', 'hash', 'target', 'column', 'record');
                }
            }
            DB::transaction(function () use ($plan): void {
                $disk = Storage::disk('private');
                foreach ($plan as $item) {
                    if (! $disk->exists($item['target'])) {
                        $stream = fopen($item['file'], 'rb');
                        if ($stream === false) {
                            throw new \RuntimeException('PDF tidak dapat dibaca.');
                        }
                        try {
                            if (! $disk->put($item['target'], $stream)) {
                                throw new \RuntimeException('Penyalinan PDF gagal.');
                            }
                        } finally {
                            fclose($stream);
                        }
                    }
                    if (! hash_equals($item['hash'], hash_file('sha256', $disk->path($item['target'])))) {
                        throw new \RuntimeException('Hash salinan PDF berbeda.');
                    }
                    $item['record']->update([$item['column'] => $item['target']]);
                }
            });
            $this->info(count($plan).' tautan dokumen terhubung ke disk privat.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Dokumen tidak dihubungkan: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
