<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Longitudinal outcome tracking between and after consultations.
 * adherence_pct is a 0–100 dietary adherence estimate from RND assessment.
 */
class ProgressRecord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'relationship_id',
        'record_date',
        'weight_kg',
        'blood_pressure',
        'blood_glucose',
        'hba1c',
        'adherence_pct',
        'client_notes',
        'rnd_notes',
    ];

    protected function casts(): array
    {
        return [
            'record_date'  => 'date',
            'weight_kg'    => 'decimal:2',
            'blood_glucose'=> 'decimal:2',
            'hba1c'        => 'decimal:2',
            'created_at'   => 'datetime',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(RndClientRelationship::class, 'relationship_id');
    }
}
