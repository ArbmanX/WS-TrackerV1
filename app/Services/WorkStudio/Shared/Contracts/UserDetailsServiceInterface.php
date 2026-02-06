<?php

namespace App\Services\WorkStudio\Shared\Contracts;

interface UserDetailsServiceInterface
{
    /**
     * Get user details from WorkStudio API.
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
     * @throws \App\Services\WorkStudio\Shared\Exceptions\UserNotFoundException
     * @throws \App\Services\WorkStudio\Shared\Exceptions\WorkStudioApiException
     */
    public function getDetails(string $username): array;
}
