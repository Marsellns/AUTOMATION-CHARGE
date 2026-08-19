<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Region extends Model
{
    /** @use HasFactory<\Database\Factories\RegionFactory> */
    use HasFactory;

    protected $fillable = [
        'kode',
        'nama',
    ];

    /**
     * Sites yang termasuk dalam region ini.
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * Semua metrik bulanan dari seluruh site di region ini.
     * Memungkinkan query seperti: $region->monthlyMetrics()->sum('revenue')
     */
    public function monthlyMetrics(): HasManyThrough
    {
        return $this->hasManyThrough(SiteMonthlyMetric::class, Site::class);
    }
}
