<?php

namespace App\Services\WorkStudio\Client;

use App\Services\WorkStudio\Assessments\Queries\AssessmentQueries;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetQueryService
{
    public function __construct(
        private ApiCredentialManager $credentialManager,
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
        $credentials = $this->credentialManager->getCredentials($userId);

        $payload = [
            'Protocol' => 'GETQUERY',
            'DBParameters' => ApiCredentialManager::formatDbParameters($credentials['username'], $credentials['password']),
            'SQL' => $sql,
        ];

        $url = rtrim(config('workstudio.base_url'), '/').'/GETQUERY';

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($credentials['username'], $credentials['password'])
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

    public function getJobGuids(UserQueryContext $context): Collection
    {
        $queries = new AssessmentQueries($context);
        $sql = $queries->getAllJobGUIDsForEntireScopeYear();

        return $this->executeAndHandle($sql, $context->userId);
    }

    public function getSystemWideMetrics(UserQueryContext $context): Collection
    {
        $queries = new AssessmentQueries($context);
        $sql = $queries->systemWideDataQuery();

        return $this->executeAndHandle($sql, $context->userId);
    }

    public function getRegionalMetrics(UserQueryContext $context): Collection
    {
        $queries = new AssessmentQueries($context);
        $sql = $queries->groupedByRegionDataQuery();

        return $this->executeAndHandle($sql, $context->userId);
    }

    public function getDailyActivitiesForAllAssessments(UserQueryContext $context): Collection
    {
        $queries = new AssessmentQueries($context);
        $sql = $queries->getAllAssessmentsDailyActivities();

        return $this->executeAndHandle($sql, $context->userId);
    }

    /**
     * Get active assessments ordered by oldest unit first.
     *
     * Filters:
     *   - STATUS = 'ACTIV'
     *   - TAKEN = true (checked out)
     *   - Username domain matches user's domain
     *   - Assessment started (completed miles > 0)
     *
     * @param  int  $limit  Number of results (default 50)
     */
    public function getActiveAssessmentsOrderedByOldest(UserQueryContext $context, int $limit = 50): Collection
    {
        $queries = new AssessmentQueries($context);
        $sql = $queries->getActiveAssessmentsOrderedByOldest($limit);

        return $this->executeAndHandle($sql, $context->userId);
    }

    /**
     * Get distinct values of a field from a table, scoped to active assessments.
     *
     * @param  string  $table  Table name (e.g., 'VEGUNIT', 'STATIONS')
     * @param  string  $field  Column name (e.g., 'LASTNAME', 'CITY')
     * @param  int  $limit  Max rows to return (default 500)
     */
    public function getDistinctFieldValues(UserQueryContext $context, string $table, string $field, int $limit = 500): Collection
    {
        $queries = new AssessmentQueries($context);
        $sql = $queries->getDistinctFieldValues($table, $field, $limit);

        return $this->executeAndHandle($sql, $context->userId);
    }
}
