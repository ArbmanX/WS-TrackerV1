<?php

namespace App\Services\WorkStudio\Shared\Helpers;

use Carbon\CarbonImmutable;

class WSSQLCaster
{
    /**
     * Field registry — WS field name to cast type.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        // OLE Automation dates (ftFloat used as datetime)
        'EDITDATE' => 'ole_datetime',
        'TAKENDATE' => 'ole_datetime',
        'HISTORYDATE' => 'ole_datetime',
        'ASSDDATE' => 'ole_datetime',
        // Regular dates (ftDate)
        'STAKEDDATE' => 'date',
        'CREATEDATE' => 'date',
        'COMPLETEDDATE' => 'date',
    ];

    private const DEFAULT_OLE_FORMAT = 'yyyy-MM-dd HH:mm:ss';

    /**
     * Return the appropriate SQL fragment for casting a WS column.
     *
     * Accepts TABLE.FIELD format (strips table prefix for lookup).
     * Unknown fields pass through unchanged.
     */
    public static function cast(string $column, ?string $format = null): string
    {
        $field = str_contains($column, '.') ? substr($column, strpos($column, '.') + 1) : $column;

        $type = self::FIELDS[$field] ?? null;

        return match ($type) {
            'ole_datetime' => self::oleDatetimeSql($column, $format ?? self::DEFAULT_OLE_FORMAT),
            'date' => $column,
            default => $column,
        };
    }

    /**
     * Convert an OLE Automation date float to CarbonImmutable (UTC).
     *
     * OLE epoch: December 30, 1899. The float's integer part is days since epoch,
     * fractional part is fraction of a 24-hour day.
     */
    public static function oleDateToCarbon(float $oleDate): CarbonImmutable
    {
        $epoch = CarbonImmutable::create(1899, 12, 30, 0, 0, 0, 'UTC');

        $days = (int) $oleDate;
        $fraction = $oleDate - $days;
        $seconds = (int) round($fraction * 86400);

        return $epoch->addDays($days)->addSeconds($seconds);
    }

    /**
     * Build SQL fragment for OLE Automation datetime conversion to Eastern time.
     */
    private static function oleDatetimeSql(string $column, string $format): string
    {
        return "FORMAT(CAST(CAST({$column} AS DATETIME) AT TIME ZONE 'UTC' AT TIME ZONE 'Eastern Standard Time' AS DATETIME), '{$format}')";
    }
}
