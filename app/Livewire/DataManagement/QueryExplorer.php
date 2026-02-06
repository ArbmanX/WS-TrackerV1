<?php

declare(strict_types=1);

namespace App\Livewire\DataManagement;

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
    public string $table = '';

    public string $fields = '*';

    #[Validate('required|integer|min:1|max:500')]
    public int $top = 10;

    public string $whereClause = '';

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
    ];

    public function runQuery(): void
    {
        $this->reset('results', 'error', 'executedSql', 'queryTime', 'rowCount');

        $this->validate([
            'top' => 'required|integer|min:1|max:500',
        ]);

        $table = trim($this->table);
        if ($table === '') {
            $this->error = 'Table name is required.';

            return;
        }

        $fields = trim($this->fields) ?: '*';
        $sql = "SELECT TOP {$this->top} {$fields} FROM {$table}";

        $where = trim($this->whereClause);
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }

        $this->executedSql = $sql;

        $username = config('workstudio.service_account.username');
        $password = config('workstudio.service_account.password');
        $baseUrl = rtrim((string) config('workstudio.base_url'), '/');

        $payload = [
            'Protocol' => 'GETQUERY',
            'DBParameters' => "USER NAME={$username}\r\nPASSWORD={$password}\r\n",
            'SQL' => $sql,
        ];

        try {
            $start = microtime(true);

            $response = Http::withBasicAuth($username, $password)
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

    public function render(): \Illuminate\View\View
    {
        return view('livewire.data-management.query-explorer');
    }
}
