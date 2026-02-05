<?php

namespace App\Services\WorkStudio;

use App\Services\WorkStudio\Contracts\WorkStudioApiInterface;
use App\Services\WorkStudio\Managers\ApiCredentialManager;
use App\Services\WorkStudio\Services\GetQueryService;
use App\Services\WorkStudio\ValueObjects\UserQueryContext;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WorkStudio API Service - Single Entry Point
 *
 * Acts as facade for all WorkStudio API interactions.
 * Delegates query execution to GetQueryService.
 */
class WorkStudioApiService implements WorkStudioApiInterface
{
    private ?int $currentUserId = null;

    public function __construct(
        private ?ApiCredentialManager $credentialManager = null,
        private ?GetQueryService $queryService = null,
    ) {
        // Auto-resolve GetQueryService if not injected (for backwards compatibility)
        $this->queryService ??= app(GetQueryService::class);
    }

    /**
     * Check if the WorkStudio API is reachable.
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::workstudio()->get($this->getBaseUrlWithoutPath());

            return ! $response->serverError();
        } catch (ConnectionException $e) {
            Log::warning('WorkStudio API health check failed', [
                'url' => $this->getBaseUrlWithoutPath(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the current API credentials info (without exposing password).
     */
    public function getCurrentCredentialsInfo(): array
    {
        return $this->credentialManager->getCredentialsInfo($this->currentUserId);
    }

    /**
     * Make the actual HTTP request to WorkStudio with retry logic.
     */
    private function makeRequest(array $payload, array $credentials, ?int $userId): array
    {
        $url = rtrim(config('workstudio.base_url'), '/').'/'.($payload['Protocol'] ?? 'GETVIEWDATA');
        $maxRetries = config('workstudio.max_retries', 5);

        $response = Http::workstudio()
            ->withBasicAuth($credentials['username'], $credentials['password'])
            ->retry(
                $maxRetries,
                function (int $attempt, Exception $exception) use ($url) {
                    // Exponential backoff: 1s, 2s, 4s, 8s... capped at 30s
                    $delay = min(1000 * pow(2, $attempt - 1), 30000);

                    Log::warning('WorkStudio API request failed, retrying', [
                        'url' => $url,
                        'attempt' => $attempt,
                        'max_retries' => config('workstudio.max_retries', 5),
                        'delay_ms' => $delay,
                        'error' => $exception->getMessage(),
                        'exception_class' => get_class($exception),
                    ]);

                    return $delay;
                },
                function (Exception $exception, PendingRequest $request) use ($userId) {
                    // Don't retry 401 authentication errors
                    if ($exception instanceof RequestException && $exception->response?->status() === 401) {
                        $this->credentialManager->markFailed($userId);

                        Log::error('WorkStudio API authentication failed', [
                            'user_id' => $userId,
                        ]);

                        return false;
                    }

                    return true;
                }
            )
            ->post($url, $payload);

        $response->throw();

        $this->credentialManager->markSuccess($userId);

        $data = $response->json();

        if (! isset($data['Protocol']) || $data['Protocol'] !== 'DATASET') {
            Log::warning('Unexpected WorkStudio API response format', [
                'protocol' => $data['Protocol'] ?? 'missing',
            ]);
        }

        return $data;
    }

    /**
     * Get the base URL without the protocol path.
     */
    private function getBaseUrlWithoutPath(): string
    {
        $baseUrl = config('workstudio.base_url');

        return preg_replace('#/DDOProtocol/?$#', '', $baseUrl);
    }

    /**
     * Get the display caption for a status code.
     */
    private function getStatusCaption(string $status): string
    {
        $statuses = config('workstudio.statuses', []);

        foreach ($statuses as $config) {
            if (($config['value'] ?? '') === $status) {
                return $config['caption'] ?? $status;
            }
        }

        return $status;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Service Delegation Methods
    |--------------------------------------------------------------------------
    | These methods delegate to GetQueryService for actual query execution.
    | WorkStudioApiService acts as the single entry point (facade pattern).
    */

    /**
     * Execute a raw SQL query against the WorkStudio API.
     */
    public function executeQuery(string $sql, ?int $userId = null): ?array
    {
        return $this->queryService->executeQuery($sql, $userId);
    }

    /**
     * Get all job GUIDs for the entire scope year.
     */
    public function getJobGuids(UserQueryContext $context): Collection
    {
        return $this->queryService->getJobGuids($context);
    }

    /**
     * Get system-wide aggregated metrics.
     */
    public function getSystemWideMetrics(UserQueryContext $context): Collection
    {
        return $this->queryService->getSystemWideMetrics($context);
    }

    /**
     * Get metrics grouped by region.
     */
    public function getRegionalMetrics(UserQueryContext $context): Collection
    {
        return $this->queryService->getRegionalMetrics($context);
    }

    /**
     * Get active assessments ordered by oldest assessed unit.
     */
    public function getActiveAssessmentsOrderedByOldest(UserQueryContext $context, int $limit = 50): Collection
    {
        return $this->queryService->getActiveAssessmentsOrderedByOldest($context, $limit);
    }
}
