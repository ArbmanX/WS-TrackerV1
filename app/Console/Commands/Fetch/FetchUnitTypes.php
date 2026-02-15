<?php

namespace App\Console\Commands\Fetch;

use App\Models\UnitType;
use App\Services\WorkStudio\Client\ApiCredentialManager;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class FetchUnitTypes extends Command
{
    protected $signature = 'ws:fetch-unit-types
        {--seed : Upsert results into unit_types table}';

    protected $description = 'Fetch unit types from WorkStudio UNITS table and upsert into unit_types';

    public function handle(): int
    {
        $this->info('Fetching unit types from WorkStudio API...');

        $rows = $this->fetchFromApi();

        if ($rows === null) {
            return self::FAILURE;
        }

        $this->info("Found {$rows->count()} unit types from API.");

        if ($rows->isEmpty()) {
            $this->warn('No unit types returned from API.');

            return self::SUCCESS;
        }

        if ($this->option('seed')) {
            $this->upsertUnitTypes($rows);
        } else {
            $this->displayTable($rows);
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array<string, mixed>>|null
     */
    private function fetchFromApi(): ?Collection
    {
        $credentials = app(ApiCredentialManager::class)->getServiceAccountCredentials();
        $baseUrl = rtrim((string) config('workstudio.base_url'), '/');

        $sql = 'SELECT UNIT, UNITSSNAME, UNITSETID, SUMMARYGRP, ENTITYNAME FROM UNITS ORDER BY UNIT';

        $payload = [
            'Protocol' => 'GETQUERY',
            'DBParameters' => ApiCredentialManager::formatDbParameters($credentials['username'], $credentials['password']),
            'SQL' => $sql,
        ];

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($credentials['username'], $credentials['password'])
                ->timeout(180)
                ->connectTimeout(30)
                ->post("{$baseUrl}/GETQUERY", $payload);

            $data = $response->json();

            if (! $data) {
                $this->error("Empty response (HTTP {$response->status()}).");

                return null;
            }

            if (isset($data['protocol']) && $data['protocol'] === 'ERROR' || isset($data['errorMessage'])) {
                $this->error($data['errorMessage'] ?? 'Unknown API error.');

                return null;
            }

            if (! isset($data['Heading'], $data['Data'])) {
                return collect();
            }

            $headings = $data['Heading'];

            return collect($data['Data'])->map(fn (array $row) => array_combine($headings, $row));
        } catch (\Throwable $e) {
            $this->error("API request failed: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function displayTable(Collection $rows): void
    {
        $preview = $rows->map(fn (array $row) => [
            $row['UNIT'],
            $row['UNITSSNAME'] ?? '',
            $row['SUMMARYGRP'] ?? '',
            $this->isWorkUnit($row['SUMMARYGRP'] ?? null) ? 'Yes' : 'No',
        ]);

        $this->table(['UNIT', 'UNITSSNAME', 'SUMMARYGRP', 'work_unit'], $preview->toArray());
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function upsertUnitTypes(Collection $rows): void
    {
        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $unitType = UnitType::updateOrCreate(
                ['unit' => $row['UNIT']],
                [
                    'unitssname' => $row['UNITSSNAME'] ?: null,
                    'unitsetid' => $row['UNITSETID'] ?: null,
                    'summarygrp' => $row['SUMMARYGRP'] ?: null,
                    'entityname' => $row['ENTITYNAME'] ?: null,
                    'work_unit' => $this->isWorkUnit($row['SUMMARYGRP'] ?? null),
                    'last_synced_at' => now(),
                ],
            );

            if ($unitType->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Unit types synced: {$created} created, {$updated} updated.");
    }

    /**
     * Determine if a unit is a work unit based on its SUMMARYGRP value.
     *
     * Work units have a non-empty SUMMARYGRP that is not 'Summary-NonWork'.
     */
    private function isWorkUnit(?string $summarygrp): bool
    {
        if ($summarygrp === null || $summarygrp === '') {
            return false;
        }

        return $summarygrp !== 'Summary-NonWork';
    }
}
