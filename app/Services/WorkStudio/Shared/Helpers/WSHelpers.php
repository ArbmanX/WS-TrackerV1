<?php
namespace App\Services\WorkStudio\Shared\Helpers;

class WSHelpers
{

        /**
     * Format array for SQL IN clause
     */
    public static function toSqlInClause(array $collection): string
    {
        return collect($collection)
            ->map(fn($r) => "'{$r}'")
            ->implode(', ');
    }

    /**
     * Parse a WorkStudio /Date()/ wrapped value into a clean date string.
     * Returns null for sentinel zero-dates (1899-12-30) and empty values.
     */
    public static function parseWsDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = preg_replace('/^\/Date\((.+)\)\/$/', '$1', $value);

        if (str_starts_with($date, '1899') || $date === '1900-01-01') {
            return null;
        }

        return $date;
    }
}
