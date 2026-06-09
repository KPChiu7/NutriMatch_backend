<?php

namespace App\Services;

/**
 * Clinical nutrition calculator service.
 *
 * Implements:
 *  - BMI with WHO Asia-Pacific classification thresholds
 *  - BMR using Mifflin–St Jeor equation
 *  - TDEE with PAL multipliers
 *  - NRS-2002 nutritional risk screening score interpretation
 *
 * All methods are pure functions with no side effects.
 */
class NutritionCalculatorService
{
    /**
     * WHO Asia-Pacific BMI thresholds (lower than standard WHO cutoffs).
     * Reference: WHO Expert Consultation (2004), adapted for Asian populations.
     */
    private const BMI_CATEGORIES = [
        ['max' => 18.5, 'label' => 'Underweight'],
        ['max' => 23.0, 'label' => 'Normal'],
        ['max' => 27.5, 'label' => 'Overweight'],
        ['max' => PHP_FLOAT_MAX, 'label' => 'Obese'],
    ];

    /**
     * Physical Activity Level (PAL) multipliers for TDEE calculation.
     */
    private const ACTIVITY_MULTIPLIERS = [
        'sedentary'          => 1.200,
        'lightly_active'     => 1.375,
        'moderately_active'  => 1.550,
        'very_active'        => 1.725,
        'extra_active'       => 1.900,
    ];

    // --------------------------------------------------------
    // BMI
    // --------------------------------------------------------

    /**
     * Compute BMI from weight (kg) and height (cm).
     */
    public function computeBmi(float $weightKg, float $heightCm): float
    {
        if ($heightCm <= 0) {
            throw new \InvalidArgumentException('Height must be greater than zero.');
        }

        $heightM = $heightCm / 100;
        return round($weightKg / ($heightM * $heightM), 2);
    }

    /**
     * Classify BMI using WHO Asia-Pacific thresholds.
     */
    public function classifyBmi(float $bmi): string
    {
        foreach (self::BMI_CATEGORIES as $category) {
            if ($bmi < $category['max']) {
                return $category['label'];
            }
        }

        return 'Obese';
    }

    // --------------------------------------------------------
    // BMR & TDEE (Mifflin–St Jeor)
    // --------------------------------------------------------

    /**
     * Compute Basal Metabolic Rate using Mifflin–St Jeor equation.
     *
     * @param float  $weightKg
     * @param float  $heightCm
     * @param int    $ageYears
     * @param string $sex       'male' or 'female'
     * @return float BMR in kcal/day
     */
    public function computeBmr(float $weightKg, float $heightCm, int $ageYears, string $sex): float
    {
        $bmr = (10 * $weightKg) + (6.25 * $heightCm) - (5 * $ageYears);

        $bmr += match ($sex) {
            'male'   =>  5,
            'female' => -161,
            default  => throw new \InvalidArgumentException("Sex must be 'male' or 'female'."),
        };

        return round($bmr, 2);
    }

    /**
     * Compute Total Daily Energy Expenditure (TDEE) from BMR and activity level.
     *
     * @param float  $bmr
     * @param string $activityLevel  One of the ACTIVITY_MULTIPLIERS keys
     * @return float TDEE in kcal/day
     */
    public function computeTdee(float $bmr, string $activityLevel): float
    {
        $multiplier = self::ACTIVITY_MULTIPLIERS[$activityLevel]
            ?? throw new \InvalidArgumentException("Unknown activity level: {$activityLevel}");

        return round($bmr * $multiplier, 2);
    }

    // --------------------------------------------------------
    // NRS-2002 Nutritional Risk Screening
    // --------------------------------------------------------

    /**
     * Interpret an NRS-2002 total score into a risk category.
     *
     * Score interpretation:
     *  0–2 → no_risk    (reassess in 1 week)
     *  3   → at_risk    (initiate nutritional care plan)
     *  4–7 → high_risk  (refer to dietitian, intensive care)
     *
     * @param int $nrsScore 0–7
     * @return string no_risk|at_risk|high_risk
     */
    public function interpretNrsScore(int $nrsScore): string
    {
        if ($nrsScore < 0 || $nrsScore > 7) {
            throw new \InvalidArgumentException('NRS-2002 score must be between 0 and 7.');
        }

        return match (true) {
            $nrsScore <= 2 => 'no_risk',
            $nrsScore === 3 => 'at_risk',
            default         => 'high_risk',
        };
    }

    /**
     * Compute the full pre-consultation screening result from biometrics.
     *
     * @return array{bmi: float, bmi_category: string, bmr_kcal: float, tdee_kcal: float, nrs_risk: string}
     */
    public function computeScreening(
        float  $weightKg,
        float  $heightCm,
        int    $ageYears,
        string $sex,
        string $activityLevel,
        int    $nrsScore
    ): array {
        $bmi     = $this->computeBmi($weightKg, $heightCm);
        $bmr     = $this->computeBmr($weightKg, $heightCm, $ageYears, $sex);
        $tdee    = $this->computeTdee($bmr, $activityLevel);

        return [
            'bmi'          => $bmi,
            'bmi_category' => $this->classifyBmi($bmi),
            'bmr_kcal'     => $bmr,
            'tdee_kcal'    => $tdee,
            'nrs_risk'     => $this->interpretNrsScore($nrsScore),
        ];
    }
}
