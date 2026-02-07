<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchUniqueCycleTypes extends Command
{
    protected $signature = 'ws:fetch-cycle-types
        {--dry-run : Show results without saving to file}';

    protected $description = 'Fetch all unique cycle types from the WorkStudio API';

    public function handle(): int
    {
        $this->info('Fetching unique cycle types...');

        $rows = $this->fetchFromApi();

        if ($rows === null) {
            return self::FAILURE;
        }

        $this->info("Found {$rows->count()} unique cycle types.");

        if ($rows->isEmpty()) {
            $this->warn('No cycle types returned from API.');

            return self::SUCCESS;
        }

        $this->table(['cycle_type'], $rows->map(fn (string $type) => ['cycle_type' => $type])->toArray());

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no file saved.');

            return self::SUCCESS;
        }

        $this->saveDataFile($rows->values()->toArray());

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>|null
     */
    private function fetchFromApi(): ?\Illuminate\Support\Collection
    {
        $username = config('workstudio.service_account.username');
        $password = config('workstudio.service_account.password');
        $baseUrl = rtrim((string) config('workstudio.base_url'), '/');

        $sql = "SELECT DISTINCT VEGJOB.CYCLETYPE AS cycle_type FROM VEGJOB WHERE VEGJOB.CYCLETYPE IS NOT NULL AND VEGJOB.CYCLETYPE != '' ORDER BY VEGJOB.CYCLETYPE ASC";

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

            return collect($data['Data'])->map(fn (array $row) => trim((string) $row[0]));
        } catch (\Throwable $e) {
            $this->error("API request failed: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * @param  array<int, string>  $cycleTypes
     */
    private function saveDataFile(array $cycleTypes): void
    {
        $path = database_path('data/cycle_types.php');
        $export = var_export($cycleTypes, true);

        $content = "<?php\n\nreturn {$export};\n";

        file_put_contents($path, $content);
        $this->info('Saved ' . count($cycleTypes) . ' cycle types to database/data/cycle_types.php');
    }
}
