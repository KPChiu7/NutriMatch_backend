<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ScreeningRequest;
use App\Models\Appointment;
use App\Models\PreConsultationScreening;
use App\Services\NutritionCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles pre-consultation screening form submission by clients.
 * BMI, BMR, TDEE, and NRS-2002 risk are auto-computed server-side.
 */
class PreConsultationScreeningController extends Controller
{
    public function __construct(
        private NutritionCalculatorService $calculator
    ) {}

    /**
     * Submit pre-consultation biometrics for an appointment.
     * Computed values (BMI, BMR, TDEE, NRS risk) are calculated server-side.
     */
    public function store(ScreeningRequest $request): JsonResponse
    {
        // Ensure the appointment belongs to this client
        $appointment = Appointment::whereHas('relationship', fn($q) =>
                $q->where('client_id', $request->user()->id)
            )
            ->whereIn('status', ['pending', 'confirmed'])
            ->findOrFail($request->appointment_id);

        // Prevent duplicate submissions
        if ($appointment->preConsultationScreening) {
            return response()->json([
                'message' => 'Pre-consultation screening has already been submitted for this appointment.',
            ], 422);
        }

        // Get age from client profile for BMR computation
        $clientProfile = $request->user()->clientProfile;
        $ageYears      = $clientProfile?->date_of_birth
            ? $clientProfile->date_of_birth->age
            : $request->age_years; // fallback if DOB not set

        $sex = $clientProfile?->sex ?? $request->sex;

        // Compute clinical values server-side
        $computed = $this->calculator->computeScreening(
            weightKg:      $request->weight_kg,
            heightCm:      $request->height_cm,
            ageYears:      $ageYears,
            sex:           $sex,
            activityLevel: $request->activity_level,
            nrsScore:      $request->nrs_score,
        );

        $screening = PreConsultationScreening::create([
            'client_id'      => $request->user()->id,
            'appointment_id' => $request->appointment_id,
            'height_cm'      => $request->height_cm,
            'weight_kg'      => $request->weight_kg,
            'activity_level' => $request->activity_level,
            'nrs_score'      => $request->nrs_score,
            'symptoms'       => $request->symptoms,
            // Server-computed values
            'bmi'            => $computed['bmi'],
            'bmi_category'   => $computed['bmi_category'],
            'bmr_kcal'       => $computed['bmr_kcal'],
            'tdee_kcal'      => $computed['tdee_kcal'],
            'nrs_risk'       => $computed['nrs_risk'],
        ]);

        return response()->json([
            'message'   => 'Pre-consultation screening submitted.',
            'screening' => $screening,
        ], 201);
    }

    /**
     * View the screening result for an appointment.
     */
    public function show(int $appointmentId, Request $request): JsonResponse
    {
        $appointment = Appointment::whereHas('relationship', fn($q) =>
                $q->where('client_id', $request->user()->id)
            )
            ->findOrFail($appointmentId);

        $screening = $appointment->preConsultationScreening;

        if (! $screening) {
            return response()->json(['message' => 'No screening found for this appointment.'], 404);
        }

        return response()->json(['screening' => $screening]);
    }
}
