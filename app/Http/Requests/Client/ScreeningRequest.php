<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pre-consultation screening biometric submission.
 * BMI, BMR, TDEE, NRS risk are always computed server-side.
 */
class ScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'height_cm'      => ['required', 'numeric', 'min:30', 'max:300'],
            'weight_kg'      => ['required', 'numeric', 'min:1', 'max:500'],
            'activity_level' => ['required', 'in:sedentary,lightly_active,moderately_active,very_active,extra_active'],
            'nrs_score'      => ['required', 'integer', 'min:0', 'max:7'],
            'symptoms'       => ['nullable', 'string', 'max:2000'],
            // Fallback if profile DOB/sex not set
            'age_years'      => ['nullable', 'integer', 'min:1', 'max:120'],
            'sex'            => ['nullable', 'in:male,female'],
        ];
    }
}
