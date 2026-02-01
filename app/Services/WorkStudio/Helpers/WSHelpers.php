<?php
namespace App\Services\WorkStudio\Helpers;

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
}
