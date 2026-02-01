<?php
namespace App\Services\WorkStudio\AssessmentsDx\Queries;

class SqlFieldBuilder
{
    /**
     * Build a SQL SELECT clause from config fields for a given table.
     *
     * @param  string  $configKey  The key in config/workstudio_fields.php (e.g., 'VEGUNIT', 'Stations')
     * @param  string  $tableAlias  Optional alias prefix for the fields (e.g., 'V' becomes 'V.FIELD')
     * @return string Comma-separated field list for SQL SELECT
     */
    public static function select(string $configKey, string $tableAlias = ''): string
    {
        $fields = config("workstudio_fields.{$configKey}", []);

        if (empty($fields)) {
            return '*';
        }

        $prefix = $tableAlias ? "{$tableAlias}." : '';

        return implode(",\n                    ", array_map(
            fn ($field) => "{$prefix}{$field}",
            $fields
        ));
    }

    /**
     * Build a SQL SELECT clause with aliased field names (TABLE_FIELD format).
     *
     * @param  string  $configKey  The key in config/workstudio_fields.php
     * @param  string  $tableAlias  Alias prefix for the fields
     * @return string Comma-separated field list with AS aliases
     */
    public static function selectWithAlias(string $configKey, string $tableAlias = ''): string
    {
        $fields = config("workstudio_fields.{$configKey}", []);

        if (empty($fields)) {
            return '*';
        }

        $prefix = $tableAlias ? "{$tableAlias}." : '';

        return implode(",\n                    ", array_map(
            fn ($field) => "{$prefix}{$field} AS {$field}",
            $fields
        ));
    }

    /**
     * Get raw field array from config.
     *
     * @param  string  $configKey  The key in config/workstudio_fields.php
     * @return array<string> Array of field names
     */
    public static function getFields(string $configKey): array
    {
        return config("workstudio_fields.{$configKey}", []);
    }
}
