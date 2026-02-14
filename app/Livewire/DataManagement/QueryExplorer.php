<?php

declare(strict_types=1);

namespace App\Livewire\DataManagement;

use App\Services\WorkStudio\Client\ApiCredentialManager;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layout.app-shell', [
    'title' => 'Query Explorer',
    'breadcrumbs' => [
        ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home'],
        ['label' => 'Data Management'],
        ['label' => 'Query Explorer'],
    ],
])]
class QueryExplorer extends Component
{
    public string $mode = 'builder';

    public string $table = '';

    public string $fields = '*';

    #[Validate('required|integer|min:1|max:500')]
    public int $top = 10;

    public string $whereClause = '';

    public string $selectedSavedQuery = '';

    /** @var array<string, string> */
    public array $queryParams = [];

    public ?string $results = null;

    public string $error = '';

    public string $executedSql = '';

    public ?float $queryTime = null;

    public int $rowCount = 0;

    /** @var array<string, string> */
    private const COMMON_TABLES = [
        'VEGJOB' => 'VEGJOB — Job/Circuit master',
        'VEGUNIT' => 'VEGUNIT — Unit/Assessment records',
        'STATIONS' => 'STATIONS — Station footage data',
        'SSUNITS' => 'SSUNITS — Unit status tracking',
    ];

    /** @var array<string, array<string, mixed>> */
    private const SAVED_QUERIES = [
        'assessment_all_units' => [
            'name' => 'Assessment — All Units',
            'description' => 'All units (active + trashed) for an assessment job, joined with SSUNITS status.',
            'sql' => "SELECT TOP {top} vu.UNIT, vu.UNITGUID, vu.STATNAME, vu.TRASH, vu.SPECIES, vu.TREATTYPE, vu.LASTEDITBY, vu.LASTEDITDT, su.STATUS as SSUNITS_STATUS FROM VEGUNIT vu LEFT JOIN SSUNITS su ON vu.JOBGUID = su.JOBGUID AND vu.SEQUENCE = su.SEQUENCE WHERE vu.JOBGUID = '{jobGuid}'",
            'params' => [
                'jobGuid' => ['label' => 'Assessment Job GUID', 'placeholder' => '{XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX}'],
            ],
        ],
        'assessment_trashed_units' => [
            'name' => 'Assessment — Trashed Units Only',
            'description' => 'Only deleted/trashed units (TRASH=1 or SSUNITS STATUS=R) for an assessment.',
            'sql' => "SELECT TOP {top} vu.UNIT, vu.UNITGUID, vu.STATNAME, vu.TRASH, vu.SPECIES, vu.TREATTYPE, vu.LASTEDITBY, vu.LASTEDITDT, su.STATUS as SSUNITS_STATUS FROM VEGUNIT vu LEFT JOIN SSUNITS su ON vu.JOBGUID = su.JOBGUID AND vu.SEQUENCE = su.SEQUENCE WHERE vu.JOBGUID = '{jobGuid}' AND (vu.TRASH = 1 OR su.STATUS = 'R')",
            'params' => [
                'jobGuid' => ['label' => 'Assessment Job GUID', 'placeholder' => '{XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX}'],
            ],
        ],
    ];

    public function updatedMode(): void
    {
        $this->reset('results', 'error', 'executedSql', 'queryTime', 'rowCount');
    }

    public function updatedSelectedSavedQuery(): void
    {
        $this->queryParams = [];
        $this->reset('results', 'error', 'executedSql', 'queryTime', 'rowCount');
    }

    public function runQuery(): void
    {
        $this->reset('results', 'error', 'executedSql', 'queryTime', 'rowCount');

        $sql = $this->mode === 'saved'
            ? $this->buildSavedQuerySql()
            : $this->buildBuilderSql();

        if ($sql === null) {
            return;
        }

        $this->executedSql = $sql;
        $this->executeQuery($sql);
    }

    private function buildBuilderSql(): ?string
    {
        $this->validate(['top' => 'required|integer|min:1|max:500']);

        $table = trim($this->table);
        if ($table === '') {
            $this->error = 'Table name is required.';

            return null;
        }

        $fields = trim($this->fields) ?: '*';
        $sql = "SELECT TOP {$this->top} {$fields} FROM {$table}";

        $where = trim($this->whereClause);
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }

        return $sql;
    }

    /**
     * Build SQL from a saved query template by substituting parameters.
     */
    public function buildSavedQuerySql(): ?string
    {
        $template = self::SAVED_QUERIES[$this->selectedSavedQuery] ?? null;

        if (! $template) {
            $this->error = 'Please select a saved query.';

            return null;
        }

        foreach ($template['params'] as $key => $config) {
            if (empty(trim($this->queryParams[$key] ?? ''))) {
                $this->error = "{$config['label']} is required.";

                return null;
            }
        }

        $sql = str_replace('{top}', (string) $this->top, $template['sql']);

        foreach ($template['params'] as $key => $config) {
            $sql = str_replace("{{$key}}", trim($this->queryParams[$key]), $sql);
        }

        return $sql;
    }

    private function executeQuery(string $sql): void
    {
        $credentialManager = app(ApiCredentialManager::class);
        $credentials = $credentialManager->getServiceAccountCredentials();
        $baseUrl = rtrim((string) config('workstudio.base_url'), '/');

        $payload = [
            'Protocol' => 'GETQUERY',
            'DBParameters' => ApiCredentialManager::formatDbParameters($credentials['username'], $credentials['password']),
            'SQL' => $sql,
        ];

        try {
            $start = microtime(true);
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($credentials['username'], $credentials['password'])
                ->timeout(120)
                ->connectTimeout(30)
                ->post("{$baseUrl}/GETQUERY", $payload);

            $this->queryTime = round(microtime(true) - $start, 3);

            $data = $response->json();

            if (! $data) {
                $this->error = "Empty response (HTTP {$response->status()}).";

                return;
            }

            if (isset($data['protocol']) && $data['protocol'] === 'ERROR') {
                $this->error = $data['errorMessage'] ?? 'Unknown API error.';

                return;
            }

            if (! isset($data['Heading'], $data['Data'])) {
                $this->results = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $this->rowCount = 0;

                return;
            }

            $rows = collect($data['Data'])->map(
                fn (array $row): array => array_combine($data['Heading'], $row)
            );

            $this->rowCount = $rows->count();
            $this->results = json_encode($rows->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function clearResults(): void
    {
        $this->reset('results', 'error', 'executedSql', 'queryTime', 'rowCount');
    }

    /**
     * @return array<string, string>
     */
    public function getCommonTablesProperty(): array
    {
        return self::COMMON_TABLES;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getSavedQueriesProperty(): array
    {
        return self::SAVED_QUERIES;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.data-management.query-explorer');
    }
}
