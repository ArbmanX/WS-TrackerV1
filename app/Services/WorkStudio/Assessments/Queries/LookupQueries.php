<?php

namespace App\Services\WorkStudio\Assessments\Queries;

class LookupQueries extends AbstractQueryBuilder
{
    /**
     * Get distinct values of any field from any table, scoped to active
     * assessments for the current scope year and contractor.
     *
     * @param  string  $table  Table name (e.g., 'VEGUNIT', 'STATIONS')
     * @param  string  $field  Column name (e.g., 'LASTNAME', 'PERMSTAT')
     * @param  int  $limit  Max rows to return (default 500)
     */
    public function getDistinctFieldValues(string $table, string $field, int $limit = 500): string
    {
        // Validate table/field names — alphanumeric + underscore only
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field)) {
            throw new \InvalidArgumentException('Invalid table or field name.');
        }

        // Determine the qualified column reference and any extra JOIN needed
        $upperTable = strtoupper($table);
        $qualifiedField = "{$table}.{$field}";
        $extraJoin = '';

        if ($upperTable !== 'SS' && $upperTable !== 'VEGJOB') {
            $extraJoin = "INNER JOIN {$table} ON SS.JOBGUID = {$table}.JOBGUID";
        }

        $from = $this->baseFromClause();

        return "SELECT TOP ({$limit})
                    {$qualifiedField} AS value,
                    COUNT(*) AS record_count
                {$from}
                {$extraJoin}
                WHERE VEGJOB.REGION IN ({$this->resourceGroupsSql})
                AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'
                AND SS.STATUS = 'ACTIV'
                AND SS.TAKEN = 1
                AND VEGJOB.CONTRACTOR IN ({$this->contractorsSql})
                AND SS.JOBTYPE IN ({$this->jobTypesSql})
                AND VEGJOB.CYCLETYPE NOT IN ({$this->excludedCycleTypesSql})
                AND {$qualifiedField} IS NOT NULL
                AND {$qualifiedField} != ''
                GROUP BY {$qualifiedField}
                ORDER BY record_count DESC";
    }
}
