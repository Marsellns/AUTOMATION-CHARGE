<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Choose the same representative row for a Site ID in charts and tables.
 * The Combat master row normally has more populated fields than its revenue
 * copy; ties favor the most recently imported or edited row.
 */
final class InfrastructureCanonicalSites
{
    public static function fromRows(Collection $rows): Collection
    {
        return $rows
            ->sort(static fn ($left, $right): int => (self::score($right) <=> self::score($left))
                ?: ((int) $right->id <=> (int) $left->id))
            ->unique(fn ($row): string => strtoupper(trim((string) $row->site_code)))
            ->values();
    }

    public static function score(object $row): int
    {
        $details = CombatSourceDetails::flattened($row->source_details);

        return (filled($row->status_dokumen) ? 16 : 0)
            + (filled($row->status_perpanjangan) ? 8 : 0)
            + (filled($row->end_date_baru ?? $row->end_date_lama) ? 4 : 0)
            + (filled($details['status'] ?? null) ? 2 : 0)
            + (filled($row->site_name) ? 1 : 0);
    }
}
