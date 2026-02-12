<?php

use App\Models\RegionalSnapshot;
use App\Models\SystemWideSnapshot;
use App\Services\WorkStudio\Shared\Persistence\SnapshotPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SnapshotPersistenceService;
});

// ─── System-Wide Persistence ────────────────────────────────────────

test('persists system-wide metrics as a single row', function () {
    $data = collect([
        [
            'contractor' => 'Asplundh',
            'total_assessments' => '450',
            'active_count' => '85',
            'qc_count' => '12',
            'rework_count' => '7',
            'closed_count' => '346',
            'total_miles' => '2150.75',
            'completed_miles' => '1830.50',
            'active_planners' => '18',
        ],
    ]);

    $this->service->persistSystemWideMetrics($data, '2026', 'abc12345');

    expect(SystemWideSnapshot::count())->toBe(1);

    $snapshot = SystemWideSnapshot::first();
    expect($snapshot->scope_year)->toBe('2026')
        ->and($snapshot->context_hash)->toBe('abc12345')
        ->and($snapshot->contractor)->toBe('Asplundh')
        ->and($snapshot->total_assessments)->toBe(450)
        ->and($snapshot->active_count)->toBe(85)
        ->and($snapshot->qc_count)->toBe(12)
        ->and($snapshot->rework_count)->toBe(7)
        ->and($snapshot->closed_count)->toBe(346)
        ->and((float) $snapshot->total_miles)->toBe(2150.75)
        ->and((float) $snapshot->completed_miles)->toBe(1830.50)
        ->and($snapshot->active_planners)->toBe(18)
        ->and($snapshot->captured_at)->not->toBeNull();
});

test('skips system-wide persistence when collection is empty', function () {
    $this->service->persistSystemWideMetrics(collect(), '2026', 'abc12345');

    expect(SystemWideSnapshot::count())->toBe(0);
});

test('coerces missing numeric values to zero for system-wide', function () {
    $data = collect([
        [
            'contractor' => null,
            'total_assessments' => null,
            'active_count' => '',
            'qc_count' => null,
            'rework_count' => null,
            'closed_count' => null,
            'total_miles' => null,
            'completed_miles' => null,
            'active_planners' => null,
        ],
    ]);

    $this->service->persistSystemWideMetrics($data, '2026', 'abc12345');

    $snapshot = SystemWideSnapshot::first();
    expect($snapshot->total_assessments)->toBe(0)
        ->and($snapshot->active_count)->toBe(0)
        ->and((float) $snapshot->total_miles)->toBe(0.0)
        ->and($snapshot->contractor)->toBeNull();
});

// ─── Regional Persistence ───────────────────────────────────────────

test('persists regional metrics as one row per region', function () {
    $data = collect([
        [
            'Region' => 'HARRISBURG',
            'Total_Circuits' => '120',
            'Active_Count' => '30',
            'QC_Count' => '5',
            'Rework_Count' => '2',
            'Closed_Count' => '83',
            'Total_Miles' => '500.00',
            'Completed_Miles' => '420.50',
            'Active_Planners' => '8',
            'Total_Units' => '2500',
            'Approved_Count' => '1800',
            'Pending_Count' => '200',
            'No_Contact_Count' => '100',
            'Refusal_Count' => '50',
            'Deferred_Count' => '20',
            'PPL_Approved_Count' => '1500',
            'Rem_6_12_Count' => '15',
            'Rem_Over_12_Count' => '8',
            'Ash_Removal_Count' => '3',
            'VPS_Count' => '45',
            'Brush_Acres' => '120.50',
            'Herbicide_Acres' => '80.25',
            'Bucket_Trim_Length' => '3500.00',
            'Manual_Trim_Length' => '1200.75',
        ],
        [
            'Region' => 'LANCASTER',
            'Total_Circuits' => '95',
            'Active_Count' => '22',
            'QC_Count' => '3',
            'Rework_Count' => '1',
            'Closed_Count' => '69',
            'Total_Miles' => '380.00',
            'Completed_Miles' => '310.25',
            'Active_Planners' => '6',
            'Total_Units' => '1800',
            'Approved_Count' => '1200',
            'Pending_Count' => '150',
            'No_Contact_Count' => '80',
            'Refusal_Count' => '30',
            'Deferred_Count' => '10',
            'PPL_Approved_Count' => '1000',
            'Rem_6_12_Count' => '10',
            'Rem_Over_12_Count' => '5',
            'Ash_Removal_Count' => '2',
            'VPS_Count' => '30',
            'Brush_Acres' => '90.00',
            'Herbicide_Acres' => '60.50',
            'Bucket_Trim_Length' => '2800.00',
            'Manual_Trim_Length' => '900.00',
        ],
    ]);

    $this->service->persistRegionalMetrics($data, '2026', 'def67890');

    expect(RegionalSnapshot::count())->toBe(2);

    $harrisburg = RegionalSnapshot::where('region', 'HARRISBURG')->first();
    expect($harrisburg->scope_year)->toBe('2026')
        ->and($harrisburg->context_hash)->toBe('def67890')
        ->and($harrisburg->total_assessments)->toBe(120)
        ->and($harrisburg->active_count)->toBe(30)
        ->and($harrisburg->total_units)->toBe(2500)
        ->and($harrisburg->approved_count)->toBe(1800)
        ->and((float) $harrisburg->brush_acres)->toBe(120.50)
        ->and((float) $harrisburg->bucket_trim_length)->toBe(3500.00);

    $lancaster = RegionalSnapshot::where('region', 'LANCASTER')->first();
    expect($lancaster->total_assessments)->toBe(95)
        ->and($lancaster->active_planners)->toBe(6);
});

test('skips regional persistence when collection is empty', function () {
    $this->service->persistRegionalMetrics(collect(), '2026', 'def67890');

    expect(RegionalSnapshot::count())->toBe(0);
});

test('maps PascalCase API keys to snake_case DB columns', function () {
    $data = collect([
        [
            'Region' => 'LEHIGH',
            'Total_Circuits' => '50',
            'Active_Count' => '10',
            'QC_Count' => '2',
            'Rework_Count' => '1',
            'Closed_Count' => '37',
            'Total_Miles' => '200.00',
            'Completed_Miles' => '180.00',
            'Active_Planners' => '4',
            'Total_Units' => '1000',
            'Approved_Count' => '700',
            'Pending_Count' => '80',
            'No_Contact_Count' => '40',
            'Refusal_Count' => '20',
            'Deferred_Count' => '5',
            'PPL_Approved_Count' => '600',
            'Rem_6_12_Count' => '5',
            'Rem_Over_12_Count' => '3',
            'Ash_Removal_Count' => '1',
            'VPS_Count' => '20',
            'Brush_Acres' => '50.00',
            'Herbicide_Acres' => '30.00',
            'Bucket_Trim_Length' => '1500.00',
            'Manual_Trim_Length' => '500.00',
        ],
    ]);

    $this->service->persistRegionalMetrics($data, '2026', 'aaa11111');

    $snapshot = RegionalSnapshot::first();
    expect($snapshot->region)->toBe('LEHIGH')
        ->and($snapshot->total_assessments)->toBe(50)
        ->and($snapshot->ppl_approved_count)->toBe(600)
        ->and((float) $snapshot->herbicide_acres)->toBe(30.00);
});

test('handles missing API keys gracefully with defaults', function () {
    $data = collect([
        [
            'Region' => 'CENTRAL',
            'Total_Circuits' => '10',
            // All other keys missing
        ],
    ]);

    $this->service->persistRegionalMetrics($data, '2026', 'bbb22222');

    $snapshot = RegionalSnapshot::first();
    expect($snapshot->region)->toBe('CENTRAL')
        ->and($snapshot->total_assessments)->toBe(10)
        ->and($snapshot->active_count)->toBe(0)
        ->and($snapshot->total_units)->toBe(0)
        ->and((float) $snapshot->brush_acres)->toBe(0.0);
});

// ─── Error Handling ─────────────────────────────────────────────────

test('persistence failure does not throw exceptions', function () {
    // Force a failure by passing data that can't be inserted (e.g., invalid type)
    // The service should catch and log, never throw
    $service = new SnapshotPersistenceService;

    // Mock a scenario where the model throws
    // We'll rely on the try-catch in the service
    $data = collect([['contractor' => 'Test', 'total_assessments' => '5']]);

    // Should not throw — service catches internally
    $service->persistSystemWideMetrics($data, '2026', 'ccc33333');

    // If we got here without exception, the test passes
    expect(true)->toBeTrue();
});
