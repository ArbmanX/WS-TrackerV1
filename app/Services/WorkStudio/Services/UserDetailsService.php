<?php

namespace App\Services\WorkStudio\Services;

use App\Services\WorkStudio\Contracts\UserDetailsServiceInterface;
use App\Services\WorkStudio\Exceptions\UserNotFoundException;
use App\Services\WorkStudio\Exceptions\WorkStudioApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserDetailsService implements UserDetailsServiceInterface
{
    /**
     * Get user details from WorkStudio API using GETUSERDETAILS protocol.
     *
     * @param  string  $username  The WorkStudio username (format: DOMAIN\username)
     * @return array{
     *     username: string,
     *     full_name: string,
     *     domain: string,
     *     email: string,
     *     enabled: bool,
     *     groups: array<string>
     * }
     *
     * @throws UserNotFoundException
     * @throws WorkStudioApiException
     */
    public function getDetails(string $username): array
    {
        $url = rtrim(config('workstudio.base_url'), '/').'/GETUSERDETAILS';

        $payload = [
            'Protocol' => 'GETUSERDETAILS',
            'Username' => $username,
        ];

        try {
            $response = Http::withBasicAuth(
                config('workstudio.service_account.username'),
                config('workstudio.service_account.password')
            )
                ->timeout(config('workstudio.timeout', 30))
                ->connectTimeout(config('workstudio.connect_timeout', 10))
                ->post($url, $payload);

            $data = $response->json();

            if (isset($data['protocol']) && strtoupper($data['protocol']) === 'ERROR') {
                $errorMessage = $data['errorMessage'] ?? 'Unknown error';

                if (str_contains(strtolower($errorMessage), 'user not found')) {
                    throw new UserNotFoundException($username);
                }

                throw new WorkStudioApiException($errorMessage);
            }

            if (! isset($data['UserObject'])) {
                throw new WorkStudioApiException('Invalid response: UserObject missing');
            }

            $userObject = $data['UserObject'];

            return [
                'username' => $userObject['UserName'] ?? '',
                'full_name' => $userObject['FullName'] ?? '',
                'domain' => $userObject['DomainName'] ?? '',
                'email' => $userObject['EmailAddress'] ?? '',
                'enabled' => $userObject['Enabled'] ?? false,
                'groups' => $data['Groups'] ?? [],
            ];
        } catch (UserNotFoundException|WorkStudioApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('WorkStudio GETUSERDETAILS failed', [
                'url' => $url,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            throw new WorkStudioApiException($e->getMessage());
        }
    }
}
