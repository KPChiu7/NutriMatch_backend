<?php

namespace App\Http\Requests\Rnd;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates NCP record creation and update.
 * All clinical fields are optional on draft — only required on finalize.
 */
class NcpRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRnd();
    }

    public function rules(): array
    {
        return [
            'relationship_id'    => ['required_without:id', 'integer', 'exists:rnd_client_relationships,id'],
            'appointment_id'     => ['nullable', 'integer', 'exists:appointments,id'],
            'encounter_date'     => ['required', 'date', 'before_or_equal:today'],
            // Phase 1 — Assessment
            'weight_kg'          => ['nullable', 'numeric', 'min:1', 'max:500'],
            'height_cm'          => ['nullable', 'numeric', 'min:30', 'max:300'],
            'blood_pressure'     => ['nullable', 'string', 'max:20'],
            'blood_glucose'      => ['nullable', 'numeric', 'min:0'],
            'hba1c'              => ['nullable', 'numeric', 'min:0', 'max:20'],
            'lab_notes'          => ['nullable', 'string', 'max:5000'],
            'assessment_notes'   => ['nullable', 'string', 'max:5000'],
            // Phase 2 — Diagnosis (PES)
            'pes_problem'        => ['nullable', 'string', 'max:500'],
            'pes_etiology'       => ['nullable', 'string', 'max:5000'],
            'pes_signs'          => ['nullable', 'string', 'max:5000'],
            // Phase 3 — Intervention
            'diet_prescription'  => ['nullable', 'string', 'max:5000'],
            'target_kcal'        => ['nullable', 'numeric', 'min:0'],
            'target_protein_g'   => ['nullable', 'numeric', 'min:0'],
            'target_carb_g'      => ['nullable', 'numeric', 'min:0'],
            'target_fat_g'       => ['nullable', 'numeric', 'min:0'],
            'intervention_notes' => ['nullable', 'string', 'max:5000'],
            // Phase 4 — Monitoring
            'monitoring_notes'   => ['nullable', 'string', 'max:5000'],
            'goal_status'        => ['nullable', 'in:met,partially_met,not_met,ongoing'],
        ];
    }
}
