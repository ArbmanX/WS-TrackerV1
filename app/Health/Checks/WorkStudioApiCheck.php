<?php

namespace App\Health\Checks;

use Illuminate\Support\Facades\Http;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class WorkStudioApiCheck extends Check
{
    protected int $timeout = 10;

    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('workstudio.base_url', '');
    }

    /**
     * Set custom timeout for the health check.
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Run the health check.
     */
    public function run(): Result
    {
        if (empty($this->baseUrl)) {
            return Result::make()
                ->failed('WorkStudio API URL not configured');
        }

        try {
            // Use GETECHO protocol for a simple ping
            $url = rtrim($this->baseUrl, '/').'/GETECHO';

            $response = Http::timeout($this->timeout)
                ->connectTimeout($this->timeout)
                ->withOptions(['verify' => false])
                ->get($url);

            if ($response->successful()) {
                return Result::make()
                    ->ok('WorkStudio API is reachable')
                    ->meta([
                        'url' => $this->baseUrl,
                        'status_code' => $response->status(),
                        'response_time_ms' => $response->transferStats?->getTransferTime() * 1000,
                    ]);
            }

            return Result::make()
                ->failed("WorkStudio API returned status {$response->status()}")
                ->meta([
                    'url' => $this->baseUrl,
                    'status_code' => $response->status(),
                ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return Result::make()
                ->failed('WorkStudio API connection failed: '.$e->getMessage())
                ->meta([
                    'url' => $this->baseUrl,
                    'error' => $e->getMessage(),
                ]);
        } catch (\Exception $e) {
            return Result::make()
                ->failed('WorkStudio API check error: '.$e->getMessage())
                ->meta([
                    'url' => $this->baseUrl,
                    'error' => $e->getMessage(),
                ]);
        }
    }
}
