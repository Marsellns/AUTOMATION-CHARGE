<?php

namespace App\Support;

use App\Models\SiteOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One ownership rule for Infrastructure charts, portfolio menus, and tables.
 *
 * The Sewa Lahan/Combat workbook is authoritative whenever it supplies an
 * Ownership (or TP) value. SiteOwner is only a lookup fallback for rows where
 * the workbook leaves both values empty. This avoids a separate SiteOwner
 * dataset changing an explicit source classification.
 */
class InfrastructureOwnership
{
    public const TELKOMSEL = 'Telkomsel';
    public const TP = 'TP';
    public const OTHER = 'Lainnya / Tidak Terpetakan';

    public static function bucketForRow(object $row, Collection $ownersBySite): string
    {
        $details = CombatSourceDetails::flattened($row->source_details ?? null);
        $label = self::firstSourceLabel($details['ownership'] ?? null, $details['tp'] ?? null);

        if ($label === '') {
            $owner = $ownersBySite->get(strtoupper(trim((string) ($row->site_code ?? ''))));
            $label = self::cleanLabel($owner?->site_owner ?? null);
        }

        return self::bucketForLabel($label);
    }

    public static function bucketForLabel(mixed $label): string
    {
        $value = mb_strtolower(self::cleanLabel($label), 'UTF-8');

        if (str_contains($value, 'telkomsel')) {
            return self::TELKOMSEL;
        }

        if (str_contains($value, 'tp') || str_contains($value, 'tower')) {
            return self::TP;
        }

        return self::OTHER;
    }

    /**
     * Apply the same precedence as bucketForRow() to a server-side table.
     */
    public static function applyBucketFilter(Builder $query, string $bucket): void
    {
        $bucket = self::canonicalBucket($bucket);
        $sourceLabel = self::sourceLabelExpression();
        $directCondition = self::bucketCondition($sourceLabel, $bucket);

        $query->where(function (Builder $filter) use ($bucket, $sourceLabel, $directCondition): void {
            // An explicit workbook value is decisive, even when the SiteOwner
            // lookup has a different operator/company label.
            $filter->where(function (Builder $explicit) use ($sourceLabel, $directCondition): void {
                $explicit->whereRaw("({$sourceLabel}) <> ''")
                    ->whereRaw($directCondition);
            })->orWhere(function (Builder $fallback) use ($bucket, $sourceLabel): void {
                $fallback->whereRaw("({$sourceLabel}) = ''");

                if ($bucket === self::OTHER) {
                    $mappedCodes = self::siteCodesForBuckets([self::TELKOMSEL, self::TP]);
                    if ($mappedCodes->isNotEmpty()) {
                        $fallback->whereNotIn(DB::raw('UPPER(TRIM(site_code))'), $mappedCodes->all());
                    }

                    return;
                }

                $mappedCodes = self::siteCodesForBuckets([$bucket]);
                if ($mappedCodes->isEmpty()) {
                    $fallback->whereRaw('1 = 0');

                    return;
                }

                $fallback->whereIn(DB::raw('UPPER(TRIM(site_code))'), $mappedCodes->all());
            });
        });
    }

    private static function canonicalBucket(string $value): string
    {
        return match (mb_strtolower(trim($value), 'UTF-8')) {
            'telkomsel' => self::TELKOMSEL,
            'tp' => self::TP,
            default => self::OTHER,
        };
    }

    private static function siteCodesForBuckets(array $buckets): Collection
    {
        return SiteOwner::query()
            ->get(['site_code', 'site_owner'])
            ->filter(fn (SiteOwner $owner): bool => in_array(self::bucketForLabel($owner->site_owner), $buckets, true))
            ->map(fn (SiteOwner $owner): string => strtoupper(trim((string) $owner->site_code)))
            ->filter()
            ->unique()
            ->values();
    }

    private static function sourceLabelExpression(): string
    {
        // MySQL JSON null becomes the string "null" after JSON_UNQUOTE.
        // Normalize it alongside ordinary blanks and a dash so SQL follows
        // the same missing-value rule as PHP's decoded source_details array.
        $value = static function (string $key): string {
            $paths = [
                "$.{$key}",
                "$.database.{$key}",
                "$.database_revenue.{$key}",
            ];
            $values = array_map(
                static fn (string $path): string => "NULLIF(NULLIF(LOWER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_details, '{$path}')), ''))), 'null'), '-')",
                $paths
            );

            return 'COALESCE('.implode(', ', $values).", '')";
        };
        $ownership = $value('ownership');
        $tp = $value('tp');

        return "COALESCE({$ownership}, {$tp}, '')";
    }

    private static function bucketCondition(string $sourceLabel, string $bucket): string
    {
        return match ($bucket) {
            self::TELKOMSEL => "({$sourceLabel}) LIKE '%telkomsel%'",
            self::TP => "(({$sourceLabel}) LIKE '%tp%' OR ({$sourceLabel}) LIKE '%tower%')",
            default => "(({$sourceLabel}) NOT LIKE '%telkomsel%' AND ({$sourceLabel}) NOT LIKE '%tp%' AND ({$sourceLabel}) NOT LIKE '%tower%')",
        };
    }

    private static function firstSourceLabel(mixed ...$labels): string
    {
        foreach ($labels as $label) {
            $clean = self::cleanLabel($label);
            if ($clean !== '') {
                return $clean;
            }
        }

        return '';
    }

    private static function cleanLabel(mixed $label): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', (string) ($label ?? '')) ?? '');

        return $clean === '-' || mb_strtolower($clean, 'UTF-8') === 'null' ? '' : $clean;
    }
}
