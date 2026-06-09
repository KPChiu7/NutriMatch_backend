<?php

namespace Tests\Unit;

use App\Services\NutritionCalculatorService;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Unit tests for clinical nutrition computation.
 * Tests BMI (WHO Asia-Pacific), BMR (Mifflin-St Jeor), TDEE, NRS-2002.
 */
class NutritionCalculatorTest extends TestCase
{
    private NutritionCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new NutritionCalculatorService();
    }

    // --------------------------------------------------------
    // BMI
    // --------------------------------------------------------

    public function test_computes_bmi_correctly(): void
    {
        // 70kg / (1.70m)^2 = 24.22
        $bmi = $this->calculator->computeBmi(70.0, 170.0);
        $this->assertEquals(24.22, $bmi);
    }

    public function test_bmi_throws_on_zero_height(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->computeBmi(60.0, 0.0);
    }

    // --------------------------------------------------------
    // BMI Classification (WHO Asia-Pacific thresholds)
    // --------------------------------------------------------

    /** @dataProvider bmiClassificationProvider */
    public function test_classifies_bmi_correctly(float $bmi, string $expected): void
    {
        $this->assertEquals($expected, $this->calculator->classifyBmi($bmi));
    }

    public static function bmiClassificationProvider(): array
    {
        return [
            'underweight'   => [17.5, 'Underweight'],
            'normal'        => [20.0, 'Normal'],
            'normal_border' => [22.9, 'Normal'],
            'overweight'    => [25.0, 'Overweight'],
            'obese'         => [30.0, 'Obese'],
        ];
    }

    // --------------------------------------------------------
    // BMR (Mifflin-St Jeor)
    // --------------------------------------------------------

    public function test_computes_bmr_for_male(): void
    {
        // Male: (10×70) + (6.25×170) - (5×25) + 5 = 1668.5
        $bmr = $this->calculator->computeBmr(70.0, 170.0, 25, 'male');
        $this->assertEquals(1668.50, $bmr);
    }

    public function test_computes_bmr_for_female(): void
    {
        // Female: (10×55) + (6.25×160) - (5×30) - 161 = 1339.0
        $bmr = $this->calculator->computeBmr(55.0, 160.0, 30, 'female');
        $this->assertEquals(1339.0, $bmr);
    }

    public function test_bmr_throws_on_invalid_sex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->computeBmr(70.0, 170.0, 25, 'other');
    }

    // --------------------------------------------------------
    // TDEE
    // --------------------------------------------------------

    public function test_computes_tdee_for_sedentary(): void
    {
        $tdee = $this->calculator->computeTdee(1668.50, 'sedentary');
        $this->assertEquals(round(1668.50 * 1.200, 2), $tdee);
    }

    public function test_tdee_throws_on_unknown_activity_level(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->computeTdee(1668.50, 'super_active');
    }

    // --------------------------------------------------------
    // NRS-2002
    // --------------------------------------------------------

    /** @dataProvider nrsProvider */
    public function test_interprets_nrs_score_correctly(int $score, string $expected): void
    {
        $this->assertEquals($expected, $this->calculator->interpretNrsScore($score));
    }

    public static function nrsProvider(): array
    {
        return [
            'score_0'  => [0, 'no_risk'],
            'score_2'  => [2, 'no_risk'],
            'score_3'  => [3, 'at_risk'],
            'score_4'  => [4, 'high_risk'],
            'score_7'  => [7, 'high_risk'],
        ];
    }

    public function test_nrs_throws_on_out_of_range_score(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->interpretNrsScore(8);
    }

    // --------------------------------------------------------
    // computeScreening (integration of all computations)
    // --------------------------------------------------------

    public function test_compute_screening_returns_all_fields(): void
    {
        $result = $this->calculator->computeScreening(70.0, 170.0, 25, 'male', 'sedentary', 2);

        $this->assertArrayHasKey('bmi', $result);
        $this->assertArrayHasKey('bmi_category', $result);
        $this->assertArrayHasKey('bmr_kcal', $result);
        $this->assertArrayHasKey('tdee_kcal', $result);
        $this->assertArrayHasKey('nrs_risk', $result);

        $this->assertEquals(24.22, $result['bmi']);
        $this->assertEquals('Normal', $result['bmi_category']);
        $this->assertEquals('no_risk', $result['nrs_risk']);
    }
}
