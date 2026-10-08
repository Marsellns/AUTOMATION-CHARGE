<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Bapss extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bapss';

    protected $fillable = [
        'site_code', 'site_name', 'tgl_bapss', 'tgl_dismantle', 'remark',
        'pdf_bapss', 'pdf_ba_dismantle', 'source_documents', 'update_by', 'tgl_update',
    ];

    protected $casts = ['tgl_bapss' => 'date', 'tgl_dismantle' => 'date', 'tgl_update' => 'datetime', 'source_documents' => 'array'];

    public function pdfAvailable(string $column): bool
    {
        return $this->pdfPath($column) !== null;
    }

    public function pdfPath(string $column, array $visited = []): ?string
    {
        if (! in_array($column, ['pdf_bapss', 'pdf_ba_dismantle'], true) || in_array($this->id, $visited, true) || count($visited) > 20) {
            return null;
        }
        $path = $this->{$column};
        if (is_string($path)
            && preg_match('#^bapss/[A-Za-z0-9][A-Za-z0-9._/-]*\.pdf$#i', $path) === 1
            && ! str_contains($path, '..')
            && Storage::disk('private')->exists($path)) {
            return $path;
        }
        if ($path) {
            return null;
        }
        if (preg_match('/dokumen\s+(BAPSS dan BA Dismantle|BA Dismantle)\s+sama dengan\s+([A-Z0-9]+)/i', (string) $this->remark, $match)
            && ($column === 'pdf_ba_dismantle' || str_starts_with(strtoupper($match[1]), 'BAPSS'))) {
            $source = static::query()->whereRaw('UPPER(TRIM(site_code)) = ?', [strtoupper($match[2])])->latest('id')->first();
            return $source?->pdfPath($column, array_merge($visited, [$this->id]));
        }
        return null;
    }

    public function missingPdfLabel(string $column): string
    {
        $source = (string) ($this->source_documents[$column] ?? '');
        $shared = preg_match('/dokumen\s+(BAPSS dan BA Dismantle|BA Dismantle)\s+sama dengan/i', (string) $this->remark, $match);
        return strcasecmp($source, 'Download') === 0 || ($shared && ($column === 'pdf_ba_dismantle' || str_starts_with(strtoupper($match[1]), 'BAPSS')))
            ? 'Berkas sumber belum tersedia' : '-';
    }
}
