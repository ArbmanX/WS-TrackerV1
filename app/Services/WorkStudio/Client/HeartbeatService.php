<?php

namespace App\Services\WorkStudio\Client;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HeartbeatService
{
    public function __construct(
        private ApiCredentialManager $credentialManager,
    ) {}

    /**
     * Check if the WorkStudio API server is responsive.
     *
     * Sends a request to the HEARTBEAT endpoint which returns
     * a protocol "OK" response when the server is alive.
     */
    public function isAlive(): bool
    {
        $url = rtrim(config('workstudio.base_url'), '/').'/HEARTBEAT';

        try {
            $credentials = $this->credentialManager->getServiceAccountCredentials();

            $response = Http::workstudio()
                ->withBasicAuth(
                    $credentials['username'],
                    $credentials['password'],
                )
                ->timeout(10)
                ->connectTimeout(5)
                ->post($url);

            $data = $response->json();

            return $response->successful()
                && isset($data['protocol'])
                && strtoupper($data['protocol']) === 'OK';
        } catch (\Exception $e) {
            Log::warning('WorkStudio heartbeat failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
