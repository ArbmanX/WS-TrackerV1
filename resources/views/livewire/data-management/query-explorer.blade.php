<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Query Explorer</h1>
        <p class="text-base-content/60 text-sm mt-1">Build and run raw SQL SELECT queries against the WorkStudio API</p>
    </div>

    {{-- Error Alert --}}
    @if($error)
        <div class="alert alert-error mb-6 shadow-sm">
            <x-heroicon-o-x-circle class="size-5" />
            <div>
                <p class="font-semibold">Query Failed</p>
                <p class="text-sm">{{ $error }}</p>
            </div>
            <button wire:click="clearResults" class="btn btn-ghost btn-sm">Dismiss</button>
        </div>
    @endif

    {{-- Query Card with Tabs --}}
    <div class="card bg-base-100 shadow-sm mb-6">
        <div class="card-body">
            {{-- Tab Navigation --}}
            <div role="tablist" class="tabs tabs-border mb-4">
                <button
                    wire:click="$set('mode', 'builder')"
                    role="tab"
                    class="tab {{ $mode === 'builder' ? 'tab-active' : '' }}"
                >
                    <x-heroicon-o-command-line class="size-4 mr-1" />
                    Query Builder
                </button>
                <button
                    wire:click="$set('mode', 'saved')"
                    role="tab"
                    class="tab {{ $mode === 'saved' ? 'tab-active' : '' }}"
                >
                    <x-heroicon-o-bookmark class="size-4 mr-1" />
                    Saved Queries
                </button>
            </div>

            {{-- Builder Tab --}}
            @if($mode === 'builder')
                <form wire:submit="runQuery" class="space-y-4">
                    {{-- Row 1: Table + Fields --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Table --}}
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend font-medium">Table</legend>
                            <select
                                id="table-select"
                                wire:model.live="table"
                                class="select w-full"
                            >
                                <option value="">— Select a table —</option>
                                @foreach($this->commonTables as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                                <option value="__custom">Custom table name...</option>
                            </select>
                            @if($table === '__custom' || ($table !== '' && !array_key_exists($table, $this->commonTables)))
                                <input
                                    type="text"
                                    wire:model.blur="table"
                                    placeholder="Enter table name"
                                    class="input w-full mt-2"
                                />
                            @endif
                        </fieldset>

                        {{-- Fields --}}
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend font-medium">Fields</legend>
                            <input
                                id="fields-input"
                                type="text"
                                wire:model="fields"
                                placeholder="* or comma-separated columns"
                                class="input w-full"
                            />
                            <p class="label text-base-content/50">Use * for all columns, or list specific ones: COL1, COL2</p>
                        </fieldset>
                    </div>

                    {{-- Row 2: TOP + WHERE --}}
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {{-- TOP --}}
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend font-medium">TOP (limit)</legend>
                            <input
                                id="top-input"
                                type="number"
                                wire:model="top"
                                min="1"
                                max="500"
                                class="input w-full"
                            />
                            @error('top')
                                <p class="label text-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        {{-- WHERE --}}
                        <fieldset class="fieldset md:col-span-3">
                            <div class="flex items-center justify-between">
                                <legend class="fieldset-legend font-medium">WHERE clause</legend>
                                <span class="text-xs text-base-content/50">Optional</span>
                            </div>
                            <input
                                id="where-input"
                                type="text"
                                wire:model="whereClause"
                                placeholder="e.g. STATUS = 'ACTIV' AND TAKEN = 1"
                                class="input w-full"
                            />
                        </fieldset>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-3 pt-2">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="btn btn-primary"
                        >
                            <span wire:loading.remove wire:target="runQuery">
                                <x-heroicon-o-play class="size-4" />
                            </span>
                            <span wire:loading wire:target="runQuery" class="loading loading-spinner loading-xs"></span>
                            Run Query
                        </button>

                        @if($results || $error)
                            <button
                                type="button"
                                wire:click="clearResults"
                                class="btn btn-ghost btn-sm"
                            >
                                Clear
                            </button>
                        @endif
                    </div>
                </form>
            @endif

            {{-- Saved Queries Tab --}}
            @if($mode === 'saved')
                <form wire:submit="runQuery" class="space-y-4">
                    {{-- Query Selector --}}
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend font-medium">Select Query</legend>
                        <select
                            wire:model.live="selectedSavedQuery"
                            class="select w-full"
                        >
                            <option value="">— Choose a saved query —</option>
                            @foreach($this->savedQueries as $key => $query)
                                <option value="{{ $key }}">{{ $query['name'] }}</option>
                            @endforeach
                        </select>
                    </fieldset>

                    @if($selectedSavedQuery && isset($this->savedQueries[$selectedSavedQuery]))
                        @php $query = $this->savedQueries[$selectedSavedQuery]; @endphp

                        {{-- Description --}}
                        <p class="text-sm text-base-content/60">{{ $query['description'] }}</p>

                        {{-- TOP + Parameters --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- TOP --}}
                            <fieldset class="fieldset">
                                <legend class="fieldset-legend font-medium">TOP (limit)</legend>
                                <input
                                    type="number"
                                    wire:model="top"
                                    min="1"
                                    max="500"
                                    class="input w-full"
                                />
                            </fieldset>

                            {{-- Dynamic Parameter Inputs --}}
                            @foreach($query['params'] as $paramKey => $paramConfig)
                                <fieldset class="fieldset">
                                    <legend class="fieldset-legend font-medium">{{ $paramConfig['label'] }}</legend>
                                    <input
                                        type="text"
                                        wire:model="queryParams.{{ $paramKey }}"
                                        placeholder="{{ $paramConfig['placeholder'] }}"
                                        class="input w-full font-mono"
                                    />
                                </fieldset>
                            @endforeach
                        </div>

                        {{-- SQL Preview --}}
                        <div>
                            <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-1">SQL Template</p>
                            <div class="bg-base-200 rounded-lg p-4 overflow-x-auto">
                                <pre class="text-xs leading-relaxed whitespace-pre-wrap"><code>{{ str_replace([' FROM ', ' LEFT JOIN ', ' WHERE ', ' AND ('], ["\nFROM ", "\nLEFT JOIN ", "\nWHERE ", "\n  AND ("], $query['sql']) }}</code></pre>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center gap-3 pt-2">
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="btn btn-primary"
                            >
                                <span wire:loading.remove wire:target="runQuery">
                                    <x-heroicon-o-play class="size-4" />
                                </span>
                                <span wire:loading wire:target="runQuery" class="loading loading-spinner loading-xs"></span>
                                Run Query
                            </button>

                            @if($results || $error)
                                <button
                                    type="button"
                                    wire:click="clearResults"
                                    class="btn btn-ghost btn-sm"
                                >
                                    Clear
                                </button>
                            @endif
                        </div>
                    @endif
                </form>
            @endif
        </div>
    </div>

    {{-- Results Card --}}
    @if($results !== null)
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                {{-- Results Header --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 mb-4">
                    <h2 class="card-title text-lg">
                        <x-heroicon-o-table-cells class="size-5" />
                        Results
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        <span class="badge badge-primary">{{ $rowCount }} {{ Str::plural('row', $rowCount) }}</span>
                        @if($queryTime !== null)
                            <span class="badge badge-ghost">{{ $queryTime }}s</span>
                        @endif
                    </div>
                </div>

                {{-- Executed SQL --}}
                @if($executedSql)
                    <div class="mb-4">
                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-1">Executed SQL</p>
                        <div class="mockup-code text-sm">
                            <pre><code>{{ $executedSql }}</code></pre>
                        </div>
                    </div>
                @endif

                {{-- Raw JSON Output --}}
                <div>
                    <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-1">Response (JSON)</p>
                    <div class="bg-base-200 rounded-lg p-4 overflow-x-auto max-h-[600px] overflow-y-auto">
                        <pre class="text-xs leading-relaxed"><code>{{ $results }}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
