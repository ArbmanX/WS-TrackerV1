<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitType extends Model
{
    /** @use HasFactory<\Database\Factories\UnitTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'unit',
        'unitssname',
        'unitsetid',
        'summarygrp',
        'entityname',
        'work_unit',
        'last_synced_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'work_unit' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }
}
