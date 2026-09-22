<?php

namespace App\Support;

/**
 * Reads the canonical values from a Combat workbook snapshot.
 *
 * Combat imports retain the original DATABASE and DATABASE_REVENUE sheets
 * under separate JSON keys for traceability.  UI dimensions, exports, and
 * drill-downs must use their merged values rather than treating that wrapper
 * as a flat source row.
 */
final class CombatSourceDetails
{
    /**
     * @return array<string, mixed>
     */
    public static function flattened(mixed $sourceDetails): array
    {
        if (is_string($sourceDetails)) {
            $sourceDetails = json_decode($sourceDetails, true);
        }

        if (! is_array($sourceDetails)) {
            return [];
        }

        $database = is_array($sourceDetails['database'] ?? null)
            ? $sourceDetails['database']
            : [];
        $revenue = is_array($sourceDetails['database_revenue'] ?? null)
            ? $sourceDetails['database_revenue']
            : [];

        if ($database === [] && $revenue === []) {
            return $sourceDetails;
        }

        // DATABASE is the master source.  Keep its values ahead of the
        // revenue sheet, then allow any deliberately flat/manual value to
        // take precedence.
        $flatValues = collect($sourceDetails)
            ->except(['database', 'database_revenue'])
            ->all();

        return array_replace($revenue, $database, $flatValues);
    }
}
