# Comprehensive Overview Dashboard Implementation Plan

## Executive Summary

This plan outlines building a new overview dashboard in WS-TrackerV1, combining the proven UI/UX patterns from WS-Tracker (old project) with the WorkStudio API data services already established in WS-TrackerV1 (new project).

---

## 1. Analysis of WS-Tracker Dashboard (Source UI/UX)

### 1.1 Dashboard Structure

The old WS-Tracker dashboard (`Overview.php` + `overview.blade.php`) provides:

**Key Features:**
- **Header Section**: Title, subtitle, view toggle (cards/table), refresh button
- **Summary Stats Grid**: 4-column responsive grid with stat cards showing:
  - Total Assessments
  - Total Miles
  - Overall Progress (percentage)
  - Active Planners
- **Content Display**: Dual view modes (cards grid vs. table)
- **Detail Panel**: Slide-out panel for region details
- **Loading States**: Full-screen loading overlay with spinner

**Component Hierarchy (WS-Tracker):**
```
Overview.php (Livewire Component)
├── overview.blade.php (View)
│   ├── x-ui.stat-card (4 instances)
│   ├── x-ui.view-toggle
│   ├── x-dashboard.assessments.region-card (foreach)
│   ├── x-dashboard.assessments.region-table
│   └── x-dashboard.assessments.region-panel
```

### 1.2 Key Metrics Identified

From the old dashboard and data services:

| Metric | Source Field | Display Format |
|--------|--------------|----------------|
| Total Assessments | `total_assessments` | Number |
| Total Miles | `total_miles` | Number with "mi" suffix |
| Completed Miles | `completed_miles` | Number |
| Overall Progress | `completed_miles / total_miles * 100` | Percentage |
| Active Planners | `active_planners` | Number |
| Active Circuits | `active_count` | Number |
| QC Circuits | `qc_count` | Number |
| Rework Circuits | `rework_count` | Number |
| Closed Circuits | `closed_count` | Number |

### 1.3 UI Components to Migrate

**Must Create in WS-TrackerV1:**
1. `x-ui.stat-card` - Statistics display card
2. `x-ui.metric-pill` - Compact inline metric
3. `x-ui.view-toggle` - Cards/Table view switcher
4. `x-dashboard.assessments.region-card` - Region summary card
5. `x-dashboard.assessments.region-table` - Tabular region display
6. `x-dashboard.assessments.region-panel` - Slide-out detail panel

---

## 2. WS-TrackerV1 Data Services (Data Layer)

### 2.1 Available Services

Located at `/app/Services/WorkStudio/`:

| Service | Purpose | Key Methods |
|---------|---------|-------------|
| `WorkStudioApiService` | Facade for all API calls | `getSystemWideMetrics()`, `getRegionalMetrics()` |
| `GetQueryService` | Query execution and transformation | `executeAndHandle()`, `transformArrayResponse()` |
| `AssessmentQueries` | SQL query builders | `systemWideDataQuery()`, `groupedByRegionDataQuery()` |

### 2.2 Data Available from WorkStudio API

**System-Wide Query Returns:**
```php
[
    'contractor' => string,
    'total_assessments' => int,
    'active_count' => int,
    'qc_count' => int,
    'rework_count' => int,
    'closed_count' => int,
    'total_miles' => float,
    'completed_miles' => float,
    'active_planners' => int,
]
```

**Regional Query Returns:**
```php
[
    'Region' => string,
    'Total_Circuits' => int,
    'Active_Count' => int,
    'QC_Count' => int,
    'Rework_Count' => int,
    'Closed_Count' => int,
    'Total_Miles' => float,
    'Completed_Miles' => float,
    'Active_Planners' => int,
    'Total_Units' => int,
    'Approved_Count' => int,
    'Pending_Count' => int,
]
```

### 2.3 WS Module Integration

The `_bmad/ws/` module provides additional capabilities:
- Schema intelligence for WorkStudio database
- Query building assistance
- Laravel code generation workflows

---

## 3. Implementation Plan

### Phase 1: Foundation Components

#### 3.1.1 Create UI Components

**File: `resources/views/components/ui/stat-card.blade.php`**

```blade
@props([
    'label' => '',
    'value' => '',
    'suffix' => '',
    'icon' => null,
    'trend' => null,
    'trendLabel' => '',
    'color' => 'primary',
    'size' => 'md',
])

@php
    $sizeClasses = [
        'sm' => 'p-3',
        'md' => 'p-4',
        'lg' => 'p-6',
    ];
    $valueSize = [
        'sm' => 'text-xl',
        'md' => 'text-2xl',
        'lg' => 'text-3xl',
    ];
    $iconSize = [
        'sm' => 'size-6',
        'md' => 'size-8',
        'lg' => 'size-10',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'stat bg-base-100 rounded-box shadow ' . ($sizeClasses[$size] ?? $sizeClasses['md'])]) }}>
    @if($icon)
        <div class="stat-figure text-{{ $color }}">
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="{{ $iconSize[$size] ?? $iconSize['md'] }}" />
        </div>
    @endif

    <div class="stat-title text-base-content/70">{{ $label }}</div>

    <div class="stat-value {{ $valueSize[$size] ?? $valueSize['md'] }} text-{{ $color }}">
        {{ $value }}@if($suffix)<span class="text-base font-normal text-base-content/60 ml-1">{{ $suffix }}</span>@endif
    </div>

    @if($trend !== null)
        <div class="stat-desc flex items-center gap-1 {{ $trend > 0 ? 'text-success' : ($trend < 0 ? 'text-error' : 'text-base-content/60') }}">
            <!-- Trend indicators -->
        </div>
    @endif
</div>
```

**File: `resources/views/components/ui/metric-pill.blade.php`**

```blade
@props([
    'label' => '',
    'value' => '',
    'icon' => null,
    'color' => 'base-content',
])

<div class="flex items-center gap-1.5 text-sm">
    @if($icon)
        <x-dynamic-component :component="'heroicon-o-' . $icon" class="size-4 text-{{ $color }}" />
    @endif
    <span class="font-semibold">{{ $value }}</span>
    <span class="text-base-content/60">{{ $label }}</span>
</div>
```

**File: `resources/views/components/ui/view-toggle.blade.php`**

```blade
@props([
    'current' => 'cards',
    'size' => 'sm',
])

<div {{ $attributes->merge(['class' => 'join']) }} role="group">
    <button wire:click="$set('viewMode', 'cards')"
            @class(['join-item btn btn-' . $size, 'btn-active' => $current === 'cards'])>
        <x-heroicon-o-squares-2x2 class="size-4" />
        <span class="hidden sm:inline ml-1">Cards</span>
    </button>
    <button wire:click="$set('viewMode', 'table')"
            @class(['join-item btn btn-' . $size, 'btn-active' => $current === 'table'])>
        <x-heroicon-o-table-cells class="size-4" />
        <span class="hidden sm:inline ml-1">Table</span>
    </button>
</div>
```

### Phase 2: Dashboard Livewire Component

#### 3.2.1 Main Dashboard Component

**File: `app/Livewire/Dashboard/Overview.php`**

```php
<?php

namespace App\Livewire\Dashboard;

use App\Services\WorkStudio\WorkStudioApiService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layout.app-shell', ['title' => 'Overview Dashboard'])]
class Overview extends Component
{
    #[Url]
    public string $viewMode = 'cards';

    public bool $panelOpen = false;
    public ?string $selectedRegion = null;

    #[Url]
    public string $sortBy = 'Region';

    #[Url]
    public string $sortDir = 'asc';

    #[Computed]
    public function systemMetrics(): Collection
    {
        return app(WorkStudioApiService::class)->getSystemWideMetrics();
    }

    #[Computed]
    public function regionalMetrics(): Collection
    {
        $metrics = app(WorkStudioApiService::class)->getRegionalMetrics();

        return $this->sortMetrics($metrics);
    }

    protected function sortMetrics(Collection $metrics): Collection
    {
        return $metrics->sortBy($this->sortBy, SORT_REGULAR, $this->sortDir === 'desc');
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
        unset($this->regionalMetrics);
    }

    public function openPanel(string $region): void
    {
        $this->selectedRegion = $region;
        $this->panelOpen = true;
        $this->dispatch('open-panel');
    }

    public function closePanel(): void
    {
        $this->panelOpen = false;
        $this->selectedRegion = null;
        $this->dispatch('close-panel');
    }

    public function render()
    {
        return view('livewire.dashboard.overview');
    }
}
```

### Phase 3: Dashboard Sub-Components

#### 3.3.1 Region Card Component

**File: `resources/views/components/dashboard/region-card.blade.php`**

```blade
@props(['region' => null])

@php
    $totalMiles = $region['Total_Miles'] ?? 0;
    $completedMiles = $region['Completed_Miles'] ?? 0;
    $percentComplete = $totalMiles > 0 ? ($completedMiles / $totalMiles) * 100 : 0;
    $milesRemaining = $totalMiles - $completedMiles;
@endphp

<div {{ $attributes->merge(['class' => 'card bg-base-100 shadow hover:shadow-lg transition-shadow cursor-pointer']) }}
     wire:click="openPanel('{{ $region['Region'] }}')">
    <div class="card-body p-4 gap-3">
        {{-- Header --}}
        <div class="flex items-center gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <x-heroicon-o-map-pin class="size-5" />
            </div>
            <div>
                <h3 class="card-title text-base">{{ $region['Region'] ?? 'Unknown' }}</h3>
                <p class="text-xs text-base-content/60">{{ $region['Total_Circuits'] ?? 0 }} circuits</p>
            </div>
        </div>

        {{-- Metrics Row --}}
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-lg bg-base-200/50 p-2.5">
                <x-ui.metric-pill
                    :value="number_format($region['Active_Count'] ?? 0)"
                    label="active"
                    icon="bolt"
                    color="primary"
                />
            </div>
            <div class="rounded-lg bg-base-200/50 p-2.5">
                <x-ui.metric-pill
                    :value="number_format($totalMiles, 0)"
                    label="mi total"
                    icon="map"
                />
            </div>
        </div>

        {{-- Progress --}}
        <div class="space-y-1.5">
            <div class="flex items-center justify-between text-sm">
                <span class="text-base-content/70">Progress</span>
                <span class="font-semibold {{ $percentComplete >= 75 ? 'text-success' : ($percentComplete >= 50 ? 'text-warning' : '') }}">
                    {{ number_format($percentComplete, 0) }}%
                </span>
            </div>
            <progress
                class="progress h-2 {{ $percentComplete >= 75 ? 'progress-success' : ($percentComplete >= 50 ? 'progress-warning' : 'progress-primary') }}"
                value="{{ $percentComplete }}"
                max="100"
            ></progress>
            <div class="flex justify-between text-xs text-base-content/60">
                <span>{{ number_format($completedMiles, 0) }} mi completed</span>
                <span>{{ number_format($milesRemaining, 0) }} mi remaining</span>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between pt-2 border-t border-base-200">
            <x-ui.metric-pill :value="$region['Active_Planners'] ?? 0" label="planners" icon="users" />
            <span class="text-base-content/30">|</span>
            <x-ui.metric-pill :value="number_format($region['Total_Units'] ?? 0)" label="units" />
        </div>
    </div>
</div>
```

#### 3.3.2 Region Table Component

**File: `resources/views/components/dashboard/region-table.blade.php`**

```blade
@props([
    'regions' => collect(),
    'sortBy' => 'Region',
    'sortDir' => 'asc',
])

@php
    $sortIcon = $sortDir === 'asc' ? 'chevron-up' : 'chevron-down';
@endphp

<div class="overflow-x-auto">
    <table class="table table-zebra">
        <thead>
            <tr>
                <th>
                    <button class="btn btn-ghost btn-xs gap-1" wire:click="sort('Region')">
                        Region
                        @if($sortBy === 'Region')
                            <x-dynamic-component :component="'heroicon-o-' . $sortIcon" class="size-3" />
                        @endif
                    </button>
                </th>
                <th class="text-center">Active</th>
                <th class="text-right">Miles</th>
                <th class="text-center">% Complete</th>
                <th class="text-center">Planners</th>
                <th class="w-10"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($regions as $region)
                @php
                    $totalMiles = $region['Total_Miles'] ?? 0;
                    $completedMiles = $region['Completed_Miles'] ?? 0;
                    $percentComplete = $totalMiles > 0 ? ($completedMiles / $totalMiles) * 100 : 0;
                @endphp
                <tr class="hover cursor-pointer" wire:click="openPanel('{{ $region['Region'] }}')">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
                                <x-heroicon-o-map-pin class="size-4" />
                            </div>
                            <div class="font-medium">{{ $region['Region'] }}</div>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-primary badge-sm">{{ $region['Active_Count'] ?? 0 }}</span>
                    </td>
                    <td class="text-right font-medium">
                        {{ number_format($totalMiles, 0) }}
                        <span class="text-xs text-base-content/60">mi</span>
                    </td>
                    <td class="text-center">
                        <div class="flex flex-col items-center gap-1">
                            <span class="font-semibold {{ $percentComplete >= 75 ? 'text-success' : ($percentComplete >= 50 ? 'text-warning' : '') }}">
                                {{ number_format($percentComplete, 0) }}%
                            </span>
                            <progress
                                class="progress w-16 h-1.5 {{ $percentComplete >= 75 ? 'progress-success' : ($percentComplete >= 50 ? 'progress-warning' : 'progress-primary') }}"
                                value="{{ $percentComplete }}"
                                max="100"
                            ></progress>
                        </div>
                    </td>
                    <td class="text-center">{{ $region['Active_Planners'] ?? 0 }}</td>
                    <td>
                        <x-heroicon-o-chevron-right class="size-4 text-base-content/40" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-8 text-base-content/60">
                        No regions found
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
```

### Phase 4: Routing and Integration

#### 4.1 Route Registration

**Update: `routes/web.php`**

```php
// Dashboard routes
Route::middleware(['auth', 'verified', 'onboarding'])->group(function () {
    Route::get('dashboard', \App\Livewire\Dashboard\Overview::class)->name('dashboard');
});
```

### Phase 5: Responsive Design Considerations

#### 5.1 Breakpoint Strategy

| Breakpoint | Layout |
|------------|--------|
| `< 640px` (mobile) | Single column cards, collapsed sidebar |
| `640px - 768px` (sm) | 2-column stat cards, single column regions |
| `768px - 1024px` (md) | 4-column stats, 2-column regions |
| `> 1024px` (lg) | Full sidebar, 3-column regions |
| `> 1280px` (xl) | Full layout with all features visible |

---

## 4. File Structure Summary

```
WS-TrackerV1/
├── app/
│   └── Livewire/
│       └── Dashboard/
│           └── Overview.php                    # Main dashboard component
│
├── resources/
│   └── views/
│       ├── components/
│       │   ├── dashboard/
│       │   │   ├── region-card.blade.php       # Region summary card
│       │   │   ├── region-table.blade.php      # Tabular region display
│       │   │   └── region-panel.blade.php      # Slide-out detail panel
│       │   ├── layout/
│       │   │   └── app-shell.blade.php         # Main app layout
│       │   └── ui/
│       │       ├── stat-card.blade.php         # Statistics card
│       │       ├── metric-pill.blade.php       # Compact metric display
│       │       └── view-toggle.blade.php       # Cards/Table toggle
│       └── livewire/
│           └── dashboard/
│               └── overview.blade.php          # Dashboard view
│
└── routes/
    └── web.php                                 # Updated with dashboard route
```

---

## 5. Implementation Sequence

1. **Phase 1: Foundation**
   - Create UI components (`stat-card`, `metric-pill`, `view-toggle`)
   - Create `app-shell` layout with DaisyUI drawer
   - Set up theme initialization scripts

2. **Phase 2: Dashboard Core**
   - Create `Overview.php` Livewire component
   - Create `overview.blade.php` view
   - Integrate with `WorkStudioApiService`

3. **Phase 3: Region Components**
   - Create `region-card` component
   - Create `region-table` component
   - Create `region-panel` component

4. **Phase 4: Polish**
   - Add loading states and transitions
   - Implement responsive design refinements
   - Add error handling for API failures
   - Write Pest tests for components

---

## 6. Testing Strategy

```php
// tests/Feature/Livewire/Dashboard/OverviewTest.php

use App\Livewire\Dashboard\Overview;
use App\Services\WorkStudio\WorkStudioApiService;
use Livewire\Livewire;

it('renders the overview dashboard', function () {
    $this->mock(WorkStudioApiService::class, function ($mock) {
        $mock->shouldReceive('getSystemWideMetrics')
            ->andReturn(collect([['total_assessments' => 100, 'total_miles' => 500, 'completed_miles' => 250, 'active_planners' => 10]]));
        $mock->shouldReceive('getRegionalMetrics')
            ->andReturn(collect([['Region' => 'Test Region', 'Total_Miles' => 100, 'Completed_Miles' => 50]]));
    });

    Livewire::actingAs($user = User::factory()->create())
        ->test(Overview::class)
        ->assertStatus(200)
        ->assertSee('Regional Overview')
        ->assertSee('100'); // Total assessments
});
```

---

## Critical Files for Implementation

1. **`/home/arbman/WorkStudioDev/WS-Tracker/app/Livewire/Assessments/Dashboard/Overview.php`** - Core Livewire component pattern to follow
2. **`/home/arbman/WorkStudioDev/WS-TrackerV1/app/Services/WorkStudio/Services/GetQueryService.php`** - Data service layer
3. **`/home/arbman/WorkStudioDev/WS-Tracker/resources/views/components/ui/stat-card.blade.php`** - Reusable UI component pattern
4. **`/home/arbman/WorkStudioDev/WS-Tracker/resources/views/components/dashboard/assessments/region-card.blade.php`** - Region card component
5. **`/home/arbman/WorkStudioDev/WS-TrackerV1/resources/views/layouts/app/sidebar.blade.php`** - Current layout structure
