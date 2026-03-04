<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_guid',
        'work_order',
        'extension',
        'total_units',
        'approved',
        'pending',
        'refused',
        'no_contact',
        'deferred',
        'ppl_approved',
        'units_requiring_notes',
        'units_with_notes',
        'units_without_notes',
        'notes_compliance_percent',
        'pending_over_threshold',
        'stations_with_work',
        'stations_no_work',
        'stations_not_planned',
        'split_count',
        'split_updated',
        'taken_date',
        'sent_to_qc_date',
        'sent_to_rework_date',
        'closed_date',
        'first_unit_date',
        'last_unit_date',
        'oldest_pending_date',
        'oldest_pending_statname',
        'oldest_pending_unit',
        'oldest_pending_sequence',
        'work_type_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'taken_date' => 'date',
            'sent_to_qc_date' => 'date',
            'sent_to_rework_date' => 'date',
            'closed_date' => 'date',
            'first_unit_date' => 'date',
            'last_unit_date' => 'date',
            'oldest_pending_date' => 'date',
            'notes_compliance_percent' => 'decimal:1',
            'split_updated' => 'boolean',
            'work_type_breakdown' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'job_guid', 'job_guid');
    }
}
