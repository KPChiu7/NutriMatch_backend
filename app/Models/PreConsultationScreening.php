<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Client-submitted pre-consultation biometrics and computed risk scores.
 * BMI is computed using WHO Asia-Pacific thresholds.
 * NRS-2002 score (0-7) determines nutritional risk level.
 */
class PreConsultationScreening extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'appointment_id',
        'height_cm',
        'weight_kg',
        'bmi',
        'bmi_category',
        'bmr_kcal',
        'tdee_kcal',
        'activity_level',
        'nrs_score',
        'nrs_risk',
        'symptoms',
    ];

    protected function casts(): array
    {
        return [
            'height_cm' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'bmi'       => 'decimal:2',
            'bmr_kcal'  => 'decimal:2',
            'tdee_kcal' => 'decimal:2',
            'created_at'=> 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
