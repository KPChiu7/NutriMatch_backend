<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * All 4 NCP (Nutrition Care Process) phases in one longitudinal record.
 * One record per appointment encounter.
 *
 * Phases:
 *  1. Assessment   — biometrics, labs
 *  2. Diagnosis    — PES (Problem, Etiology, Signs/Symptoms)
 *  3. Intervention — diet prescription, macros
 *  4. Monitoring   — goal status, notes
 *
 * @property string $status draft|completed
 */
class NcpRecord extends Model
{
    protected $fillable = [
        'relationship_id',
        'appointment_id',
        'encounter_date',
        'status',
        // Phase 1: Assessment
        'weight_kg',
        'height_cm',
        'bmi',
        'blood_pressure',
        'blood_glucose',
        'hba1c',
        'lab_notes',
        'assessment_notes',
        // Phase 2: Diagnosis (PES)
        'pes_problem',
        'pes_etiology',
        'pes_signs',
        // Phase 3: Intervention
        'diet_prescription',
        'target_kcal',
        'target_protein_g',
        'target_carb_g',
        'target_fat_g',
        'intervention_notes',
        // Phase 4: Monitoring
        'monitoring_notes',
        'goal_status',
    ];

    protected function casts(): array
    {
        return [
            'encounter_date'  => 'date',
            'weight_kg'       => 'decimal:2',
            'height_cm'       => 'decimal:2',
            'bmi'             => 'decimal:2',
            'blood_glucose'   => 'decimal:2',
            'hba1c'           => 'decimal:2',
            'target_kcal'     => 'decimal:2',
            'target_protein_g'=> 'decimal:2',
            'target_carb_g'   => 'decimal:2',
            'target_fat_g'    => 'decimal:2',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(RndClientRelationship::class, 'relationship_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
