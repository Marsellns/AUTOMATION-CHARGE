<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SiteMonthlyMetric extends Model
{
    /** @use HasFactory<\Database\Factories\SiteMonthlyMetricFactory> */
    use HasFactory;
    use LogsActivity;

    // profit_loss sengaja TIDAK fillable: selalu dihitung otomatis dari
    // revenue - cost lewat event saving di bawah, tidak bisa diinput manual.
    protected $fillable = [
        'site_id',
        'bulan',
        'tahun',
        'revenue',
        'cost',
        'opex_freq', 'opex_isr', 'opex_trans', 'opex_power', 'opex_rm',
        'total_direct_dep', 'rev_voice', 'rev_sms', 'rev_broath', 'rev_digi', 'rev_tapout',
    ];

    /**
     * profit_loss SELALU dihitung ulang dari revenue - cost setiap kali
     * model di-save (create maupun update), siapa pun yang menyimpannya.
     */
    protected static function booted(): void
    {
        static::saving(function (SiteMonthlyMetric $metric) {
            $metric->profit_loss = round((float) $metric->revenue - (float) $metric->cost, 2);
        });
    }

    /**
     * Audit trail: catat create/update/delete beserta nilai lama & baru.
     * Nilai lama otomatis tersimpan di properties.old (API activitylog v5).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['site_id', 'bulan', 'tahun', 'revenue', 'cost', 'profit_loss'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tahun' => 'integer',
            'revenue' => 'decimal:2',
            'cost' => 'decimal:2',
            'opex_freq' => 'decimal:2',
            'opex_isr' => 'decimal:2',
            'opex_trans' => 'decimal:2',
            'opex_power' => 'decimal:2',
            'opex_rm' => 'decimal:2',
            'total_direct_dep' => 'decimal:2',
            'rev_voice' => 'decimal:2',
            'rev_sms' => 'decimal:2',
            'rev_broath' => 'decimal:2',
            'rev_digi' => 'decimal:2',
            'rev_tapout' => 'decimal:2',
            'profit_loss' => 'decimal:2',
            'is_anomaly' => 'boolean',
        ];
    }

    /**
     * Site pemilik metric ini.
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Label periode, misal "Jan 2025", "Jun 2026".
     */
    public function getPeriodeLabelAttribute(): string
    {
        $bulanNama = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        return ($bulanNama[$this->bulan] ?? '?') . ' ' . $this->tahun;
    }

    /**
     * Status PnL untuk bulan ini: 'Profit' jika profit_loss > 0,
     * selain itu 'Loss' (termasuk impas / 0).
     * Usage: $metric->status
     */
    public function getStatusAttribute(): string
    {
        return (float) $this->profit_loss > 0 ? 'Profit' : 'Loss';
    }
}
