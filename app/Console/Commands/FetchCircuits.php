<?php

namespace App\Console\Commands;

use App\Models\Circuit;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchCircuits extends Command
{
    protected $signature = 'ws:fetch-circuits
        {--save : Save results to database/data/circuits.php}
        {--seed : Seed results directly into circuits table}
        {--dry-run : Show what would happen without changes}
        {--year= : Override scope year (default from config)}';

    protected $description = 'Fetch distinct circuit line names and regions from WorkStudio API';

    public function handle(): int
    {
        $year = $this->option('year') ?? config('ws_assessment_query.scope_year');

        $this->info("Fetching circuits for year: {$year}");

        $rows = $this->fetchFromApi($year);

        if ($rows === null) {
            return self::FAILURE;
        }

        $this->info("Found {$rows->count()} circuits.");

        if ($rows->isEmpty()) {
            $this->warn('No circuits returned from API.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(['line_name', 'region', 'total_mile', 'raw_line_name'], $rows->toArray());
            $this->warn('Dry run — no changes made.');

            return self::SUCCESS;
        }

        if ($this->option('save')) {
            $this->saveDataFile($rows->toArray());
        }

        if ($this->option('seed')) {
            $this->seedCircuits($rows->toArray(), $year);
        }

        if (! $this->option('save') && ! $this->option('seed')) {
            $this->table(['line_name', 'region', 'total_mile', 'raw_line_name'], $rows->toArray());
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{line_name: string, raw_line_name: string, region: string}>|null
     */
    private function fetchFromApi(string $year): ?\Illuminate\Support\Collection
    {
        $username = config('workstudio.service_account.username');
        $password = config('workstudio.service_account.password');
        $baseUrl = rtrim((string) config('workstudio.base_url'), '/');

        $sql = 'SELECT DISTINCT VEGJOB.LINENAME AS line_name, VEGJOB.REGION AS region, VEGJOB.LENGTH as total_miles '
            . 'FROM SS '
            . 'INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID '
            . 'LEFT JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID '
            . "WHERE WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$year}%' "
            . "AND VEGJOB.LINENAME IS NOT NULL AND VEGJOB.LINENAME != '' AND SS.JOBTYPE LIKE 'Assessment%'"
            . 'ORDER BY VEGJOB.LINENAME ASC';

        $payload = [
            'Protocol' => 'GETQUERY',
            'DBParameters' => "USER NAME={$username}\r\nPASSWORD={$password}\r\n",
            'SQL' => $sql,
        ];

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($username, $password)
                ->timeout(120)
                ->connectTimeout(30)
                ->post("{$baseUrl}/GETQUERY", $payload);

            $data = $response->json();

            if (! $data) {
                $this->error("Empty response (HTTP {$response->status()}).");

                return null;
            }

            if (isset($data['protocol']) && $data['protocol'] === 'ERROR') {
                $this->error($data['errorMessage'] ?? 'Unknown API error.');

                return null;
            }

            if (! isset($data['Heading'], $data['Data'])) {
                $this->error('Unexpected response format — missing Heading/Data.');

                return null;
            }

            return collect($data['Data'])->map(function (array $row) use ($data): array {
                $mapped = array_combine($data['Heading'], $row);
                $mapped['raw_line_name'] = $mapped['line_name'];
                $mapped['line_name'] = trim(str_replace(['69/12 KV ', ' LINE', '69/12KV ', '69/12 ', '138/12 KV ', '138/12KV '], '', $mapped['line_name']));

                return $mapped;
            });
        } catch (\Throwable $e) {
            $this->error("API request failed: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Save circuits to the database/data/circuits.php file.
     *
     * @param  array<int, array{line_name: string, region: string}>  $circuits
     */
    private function saveDataFile(array $circuits): void
    {
        $path = database_path('data/circuits.php');
        $export = var_export($circuits, true);

        $content = "<?php\n\nreturn {$export};\n";

        file_put_contents($path, $content);
        $this->info('Saved ' . count($circuits) . ' circuits to database/data/circuits.php');
    }

    /**
     * Seed circuits directly into the database.
     *
     * Properties are keyed by scope year (e.g. {"2026": [], "2025": []}).
     * Existing year keys are preserved on update.
     *
     * @param  array<int, array{line_name: string, region: string}>  $circuits
     */
    private function seedCircuits(array $circuits, string $year): void
    {
        $regionMap = Region::pluck('id', 'display_name')->all();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($circuits as $circuit) {
            $regionId = $regionMap[$circuit['region'] ?? ''] ?? null;

            $existing = Circuit::where('line_name', $circuit['line_name'])->first();

            if ($existing) {
                $properties = $existing->properties ?? [];
                $properties[$year] = ['total_miles' => $circuit['total_miles']];
                $properties['raw_line_name'] = $circuit['raw_line_name'] ?? null;

                $existing->region_id = $regionId;
                $existing->properties = $properties;

                if ($existing->isDirty()) {
                    $existing->last_seen_at = now();
                    $existing->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                Circuit::create([
                    'line_name' => $circuit['line_name'],
                    'region_id' => $regionId,
                    'properties' => [
                        'raw_line_name' => $circuit['raw_line_name'] ?? null,
                        $year => ['total_miles' => $circuit['total_miles']]
                    ],
                    'last_seen_at' => now(),
                ]);
                $created++;
            }
        }

        $this->info("Seeded circuits: {$created} created, {$updated} updated, {$skipped} unchanged.");
    }
}
