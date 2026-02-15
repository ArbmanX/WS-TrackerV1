<?php

namespace App\Console\Commands\Fetch;

use App\Models\WsUser;
use App\Services\WorkStudio\Client\ApiCredentialManager;
use App\Services\WorkStudio\Shared\Contracts\UserDetailsServiceInterface;
use App\Services\WorkStudio\Shared\Exceptions\UserNotFoundException;
use App\Services\WorkStudio\Shared\Exceptions\WorkStudioApiException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchWsUsers extends Command
{
    protected $signature = 'ws:fetch-users
        {--seed : Upsert usernames and enrich via GETUSERDETAILS}
        {--year= : Scope to a specific year (omit for all years)}';

    protected $description = 'Fetch distinct WS usernames from SS and VEGJOB tables';

    public function handle(UserDetailsServiceInterface $userDetailsService): int
    {
        $year = $this->option('year');

        $this->info($year
            ? "Fetching distinct users for year: {$year}"
            : 'Fetching all distinct users (no year filter)');

        $usernames = $this->fetchDistinctUsernames($year);

        if ($usernames === null) {
            return self::FAILURE;
        }

        $this->info("Found {$usernames->count()} distinct usernames.");

        if ($usernames->isEmpty()) {
            $this->warn('No usernames returned from API.');

            return self::SUCCESS;
        }

        if ($this->option('seed')) {
            $this->upsertUsernames($usernames->all());
            $this->enrichUsers($userDetailsService);
        } else {
            $this->table(['username'], $usernames->map(fn (string $u) => [$u])->toArray());
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>|null
     */
    private function fetchDistinctUsernames(?string $year): ?\Illuminate\Support\Collection
    {
        $credentials = app(ApiCredentialManager::class)->getServiceAccountCredentials();
        $baseUrl = rtrim((string) config('workstudio.base_url'), '/');

        if ($year) {
            $yearJoin = 'INNER JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID '
                ."WHERE WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$year}%' ";

            $vegJoin = 'INNER JOIN SS ON VEGJOB.JOBGUID = SS.JOBGUID '
                .'INNER JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID '
                ."WHERE WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$year}%' ";
        } else {
            $yearJoin = 'WHERE 1=1 ';
            $vegJoin = 'INNER JOIN SS ON VEGJOB.JOBGUID = SS.JOBGUID WHERE 1=1 ';
        }

        $sql = 'SELECT DISTINCT TAKENBY AS username FROM SS '
            .$yearJoin
            ."AND TAKENBY IS NOT NULL AND TAKENBY != '' "
            .'UNION '
            .'SELECT DISTINCT MODIFIEDBY AS username FROM SS '
            .$yearJoin
            ."AND MODIFIEDBY IS NOT NULL AND MODIFIEDBY != '' "
            .'UNION '
            .'SELECT DISTINCT VEGJOB.AUDIT_USER AS username FROM VEGJOB '
            .$vegJoin
            ."AND VEGJOB.AUDIT_USER IS NOT NULL AND VEGJOB.AUDIT_USER != '' "
            .'UNION '
            .'SELECT DISTINCT VEGJOB.FRSTR_USER AS username FROM VEGJOB '
            .$vegJoin
            ."AND VEGJOB.FRSTR_USER IS NOT NULL AND VEGJOB.FRSTR_USER != '' "
            .'UNION '
            .'SELECT DISTINCT VEGJOB.GF_USER AS username FROM VEGJOB '
            .$vegJoin
            ."AND VEGJOB.GF_USER IS NOT NULL AND VEGJOB.GF_USER != '' "
            .'ORDER BY username ASC';

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

            return collect($data['Data'])->map(fn (array $row) => $row[0])->unique()->values();
        } catch (\Throwable $e) {
            $this->error("API request failed: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Upsert usernames into ws_users, extracting domain from the DOMAIN\username format.
     *
     * @param  array<int, string>  $usernames
     */
    private function upsertUsernames(array $usernames): void
    {
        $created = 0;
        $existing = 0;

        foreach ($usernames as $wsUsername) {
            $domain = str_contains($wsUsername, '\\')
                ? explode('\\', $wsUsername, 2)[0]
                : 'UNKNOWN';

            $user = WsUser::firstOrCreate(
                ['username' => $wsUsername],
                ['domain' => $domain]
            );

            if ($user->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        $this->info("Users: {$created} created, {$existing} already existed.");
    }

    /**
     * Enrich all users that haven't been synced yet (or all if none synced).
     */
    private function enrichUsers(UserDetailsServiceInterface $userDetailsService): void
    {
        $users = WsUser::whereNull('last_synced_at')->get();

        if ($users->isEmpty()) {
            $this->info('All users already enriched.');

            return;
        }

        $this->info("Enriching {$users->count()} users via GETUSERDETAILS...");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $enriched = 0;
        $notFound = 0;
        $errors = 0;
        $callCount = 0;

        $rateLimitDelay = (int) config('workstudio.sync.rate_limit_delay', 500000);
        $callsBeforeDelay = (int) config('workstudio.sync.calls_before_delay', 5);

        foreach ($users as $user) {
            try {
                $details = $userDetailsService->getDetails($user->username);

                $user->update([
                    'display_name' => $details['full_name'] ?: null,
                    'email' => $details['email'] ?: null,
                    'is_enabled' => $details['enabled'],
                    'groups' => $details['groups'] ?: null,
                    'domain' => $details['domain'] ?: $user->domain,
                    'last_synced_at' => now(),
                ]);

                $enriched++;
            } catch (UserNotFoundException) {
                $user->update(['last_synced_at' => now()]);
                $notFound++;
            } catch (WorkStudioApiException $e) {
                $this->newLine();
                $this->warn("  Failed to enrich {$user->username}: {$e->getMessage()}");
                $errors++;
            }

            $callCount++;
            if ($callCount % $callsBeforeDelay === 0) {
                usleep($rateLimitDelay);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Enrichment complete: {$enriched} enriched, {$notFound} not found, {$errors} errors.");
    }
}
