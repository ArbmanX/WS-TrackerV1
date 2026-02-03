<?php

namespace App\Services\WorkStudio\Services;

use App\Services\WorkStudio\AssessmentsDx\Queries\AssessmentQueries;
use App\Services\WorkStudio\Helpers\ExecutionTimer;
use App\Services\WorkStudio\Managers\ApiCredentialManager;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetQueryService
{
    //  TODO: eventually will need to get signed in users credentials to use for parameters.
    // credential manager is already pluged in

    public $sqlState;

    public function __construct(
        private ?ApiCredentialManager $credentialManager = null,
    ) {}

    /**
     * Execute a raw SQL query against the WorkStudio API.
     *
     * @param  string  $sql  The SQL query to execute
     * @param  int|null  $userId  Optional user ID for credentials
     * @return array The raw response data
     *
     * @throws Exception
     */
    public function executeQuery(string $sql, ?int $userId = null): ?array
    {
        $credentials = $this->getCredentials($userId);

        // TODO: SECURITY - Hardcoded credentials must be replaced with credential manager
        // @see ApiCredentialManager::getCredentials() - credentials should come from $credentials variable above
        $payload = [
            'Protocol' => 'GETQUERY',
            'DBParameters' => "USER NAME=ASPLUNDH\\cnewcombe\r\nPASSWORD=chrism\r\n", // TODO: Use $credentials['username'] and $credentials['password']
            'SQL' => $sql,
        ];

        $url = rtrim(config('workstudio.base_url'), '/').'/GETQUERY';
        $this->sqlState = $sql;

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            // TODO: SECURITY - Hardcoded credentials must be replaced with $credentials from credential manager
            $response = Http::withBasicAuth('ASPLUNDH\cnewcombe', 'chrism') // TODO: Use $credentials['username'], $credentials['password']
                ->timeout(120)
                ->connectTimeout(30)
                ->withOptions(['on_stats' => function (\GuzzleHttp\TransferStats $stats) {
                    $transferTime = $stats->getTransferTime(); // seconds
                    logger()->info("Transfer time: {$transferTime}s");
                }])
                ->post($url, $payload);

            $data = $response->json();

            if (isset($data['protocol']) && $data['protocol'] == 'ERROR' || isset($data['errorMessage'])) {
                Log::error('WorkStudio API returned error', [
                    'Status_Code' => 500,
                    'error' => $data['protocol'].' '.$data['errorMessage'] ?? 'Unknown',
                    'sql' => substr($sql, 0, 500),

                ]);

                throw new Exception(json_encode(
                    [
                        'Status_Code' => $response->status(),
                        'Message' => $data['protocol'].' in the '.class_basename($this).' '.$data['errorMessage'],
                        'SQL' => json_encode($sql, JSON_PRETTY_PRINT),
                    ]
                ) ?? 'Unknown API error', 500);
            }

            $response->throw();

            return $data;
        } catch (Exception $e) {
            Log::error('WorkStudio GETQUERY failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'sql' => substr($sql, 0, 500), // Log first 500 chars of SQL
            ]);

            throw $e;
        }
    }

    public function executeAndHandle(string $sql, ?int $userId = null): Collection|array
    {
        $response = $this->executeQuery($sql, $userId);

        if (isset($response['Heading']) && str_contains($response['Heading'][0], 'JSON_')) {
            return $this->transformJsonResponse($response);
        }

        if (isset($response['Heading']) && count($response) > 1) {
            return $this->transformArrayResponse($response);
        }

        return collect([]);
    }

    public function transformArrayResponse(array $response): Collection
    {
        if (! isset($response['Data']) || ! isset($response['Heading'])) {
            return collect([]);
        }

        $prepared = collect($response['Data'])->map(function ($row) use ($response) {
            return array_combine($response['Heading'], $row);
        });

        return $prepared;
    }

    public function transformJsonResponse(array $response): Collection
    {

        if (! isset($response['Data']) || empty($response['Data'])) {
            return collect([]);
        }

        // FOR JSON PATH responses come back as chunked strings in Data array
        // Each row contains a single element which is a JSON string fragment
        $jsonString = implode('', array_map(fn ($row) => $row[0], $response['Data']));

        // Remove control characters that might break JSON parsing
        $jsonString = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $jsonString);

        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse JSON response from WorkStudio', [
                'error' => json_last_error_msg(),
                'raw_length' => strlen($jsonString),
            ]);

            return collect([]);
        }

        return collect([$data]) ?? [];
    }

    // TODO
    // Exctact the code below to its own class, it only gets the structure sql statement as a string

    public function getJobGuids(): Collection
    {
        $sql =
        AssessmentQueries::getAllJobGUIDsForEntireScopeYear();

        return $this->executeAndHandle($sql, null);
    }

    public function getSystemWideMetrics(): Collection
    {
        $sql =
        AssessmentQueries::systemWideDataQuery();

        return $this->executeAndHandle($sql, null);
    }

    public function getRegionalMetrics(): Collection
    {
        $sql =
        AssessmentQueries::groupedByRegionDataQuery();

        return $this->executeAndHandle($sql, null);
    }

    public function getDailyActivitiesForAllAssessments(): Collection
    {
        $sql =
        AssessmentQueries::getAllAssessmentsDailyActivities();

        return $this->executeAndHandle($sql, null);
    }

    public function getAll(): Collection
    {
        $jobGuid = '{9C2BFF24-4C3D-42D5-9E4E-7FCBEFAE7DF2}';
        $sql =
        AssessmentQueries::getAllByJobGuid($jobGuid);

        dd($sql);
        return $this->executeAndHandle($sql, null);
    }

    public function queryAll(): Collection
    {
        $timer = new ExecutionTimer;
        $timer->startTotal();

        $timer->start('systemWideDataQuery');
        $sql =
        AssessmentQueries::systemWideDataQuery();
        $systemWideDataQuery = $this->executeAndHandle($sql, null);
        $timer->stop('systemWideDataQuery');

        $timer->start('groupedByRegionDataQuery');
        $sql =
        AssessmentQueries::groupedByRegionDataQuery();
        $groupedByRegionDataQuery = $this->executeAndHandle($sql, null);
        $timer->stop('groupedByRegionDataQuery');

        $timer->start('groupedByCircuitDataQuery');
        $sql =
        AssessmentQueries::groupedByCircuitDataQuery();
        $groupedByCircuitDataQuery = $this->executeAndHandle($sql, null);
        $timer->stop('groupedByCircuitDataQuery');

        dump('$systemWideDataQuery', $systemWideDataQuery);
        dump('$groupedByRegionDataQuery', $groupedByRegionDataQuery);
        dump('$groupedByCircuitDataQuery', $groupedByCircuitDataQuery);
        $timer->logTotalTime();

        return collect($groupedByCircuitDataQuery);
    }

    /**
     * Get credentials for API requests.
     */
    private function getCredentials(?int $userId = null): array
    {
        if ($this->credentialManager) {
            return $this->credentialManager->getCredentials($userId);
        }

        // Fallback to config if no credential manager
        return [
            'username' => config('workstudio.service_account.username'),
            'password' => config('workstudio.service_account.password'),
        ];
    }
}
