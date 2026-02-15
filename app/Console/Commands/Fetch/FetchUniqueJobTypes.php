<?php

namespace App\Console\Commands\Fetch;

use App\Services\WorkStudio\Client\ApiCredentialManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchUniqueJobTypes extends Command
{
    protected $signature = 'ws:fetch-job-types
        {--save : Save results to database/data/job_types.php}';

    protected $description = 'Fetch all unique job types from the WorkStudio API';

    public function handle(): int
    {
        $this->info('Fetching unique job types...');

        $rows = $this->fetchFromApi();

        if ($rows === null) {
            return self::FAILURE;
        }

        $this->info("Found {$rows->count()} unique job types.");

        if ($rows->isEmpty()) {
            $this->warn('No job types returned from API.');

            return self::SUCCESS;
        }

        $this->table(['job_type'], $rows->map(fn (string $type) => ['job_type' => $type])->toArray());

        if ($this->option('save')) {
            $this->saveDataFile($rows->values()->toArray());
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>|null
     */
    private function fetchFromApi(): ?\Illuminate\Support\Collection
    {
        $credentials = app(ApiCredentialManager::class)->getServiceAccountCredentials();
        $baseUrl = rtrim((string) config('workstudio.base_url'), '/');

        $sql = "SELECT DISTINCT SS.JOBTYPE AS job_type FROM SS WHERE SS.JOBTYPE IS NOT NULL AND SS.JOBTYPE != '' ORDER BY SS.JOBTYPE ASC";

        $payload = [
            'Protocol' => 'GETQUERY',
            'DBParameters' => ApiCredentialManager::formatDbParameters($credentials['username'], $credentials['password']),
            'SQL' => $sql,
        ];

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($credentials['username'], $credentials['password'])
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

            return collect($data['Data'])->map(fn (array $row) => trim((string) $row[0]));
        } catch (\Throwable $e) {
            $this->error("API request failed: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * @param  array<int, string>  $jobTypes
     */
    private function saveDataFile(array $jobTypes): void
    {
        $path = database_path('data/job_types.php');
        $export = var_export($jobTypes, true);

        $content = "<?php\n\nreturn {$export};\n";

        file_put_contents($path, $content);
        $this->info('Saved '.count($jobTypes).' job types to database/data/job_types.php');
    }
}
