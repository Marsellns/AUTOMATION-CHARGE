<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Site extends Model
{
    /** @use HasFactory<\Database\Factories\SiteFactory> */
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'site_id',
        'site_name',
        'region_id',
    ];

    /**
     * Audit trail: catat create/update/delete beserta nilai lama & baru.
     * Nilai lama otomatis tersimpan di properties.old (API activitylog v5).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['site_id', 'site_name', 'region_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Region tempat site ini berada.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Data metrik bulanan (revenue, cost, profit/loss) untuk site ini.
     */
    public function monthlyMetrics(): HasMany
    {
        return $this->hasMany(SiteMonthlyMetric::class);
    }

    /**
     * Scope: site yang PUNYA data metrik pada bulan+tahun tertentu (Aktif).
     * Usage: Site::activeIn(6, 2026)->get()
     */
    public function scopeActiveIn(Builder $query, int $bulan, int $tahun): Builder
    {
        return $query->whereHas('monthlyMetrics', function (Builder $metrics) use ($bulan, $tahun) {
            $metrics->where('bulan', $bulan)->where('tahun', $tahun);
        });
    }

    /**
     * Scope: site yang TIDAK punya data metrik pada bulan+tahun tertentu
     * ("Tidak Aktif" — site baru aktif di tengah periode atau sudah berhenti).
     * Usage: Site::inactiveIn(6, 2026)->count()
     */
    public function scopeInactiveIn(Builder $query, int $bulan, int $tahun): Builder
    {
        return $query->whereDoesntHave('monthlyMetrics', function (Builder $metrics) use ($bulan, $tahun) {
            $metrics->where('bulan', $bulan)->where('tahun', $tahun);
        });
    }

    /**
     * Total profit/loss across semua bulan.
     * Usage: $site->total_profit_loss
     */
    public function getTotalProfitLossAttribute(): float
    {
        return (float) $this->monthlyMetrics()->sum('profit_loss');
    }

    /**
     * Total revenue across semua bulan.
     * Usage: $site->total_revenue
     */
    public function getTotalRevenueAttribute(): float
    {
        return (float) $this->monthlyMetrics()->sum('revenue');
    }

    /**
     * Total cost across semua bulan.
     * Usage: $site->total_cost
     */
    public function getTotalCostAttribute(): float
    {
        return (float) $this->monthlyMetrics()->sum('cost');
    }
}
