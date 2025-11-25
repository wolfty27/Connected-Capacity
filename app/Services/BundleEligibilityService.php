<?php

namespace App\Services;

use App\Models\CareBundle;
use App\Models\Patient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SC-006: Bundle Eligibility Evaluation Service
 *
 * Evaluates eligibility rules to recommend appropriate care bundles
 * for patients based on their clinical data and assessments.
 */
class BundleEligibilityService
{
    /**
     * Get recommended bundles for a patient.
     *
     * @param Patient $patient
     * @param array|null $assessmentData Optional override assessment data
     * @return Collection Collection of bundle recommendations with scores
     */
    public function getRecommendations(Patient $patient, ?array $assessmentData = null): Collection
    {
        // Get patient's clinical data
        $patientData = $this->getPatientEvaluationData($patient, $assessmentData);

        // Get all active bundles with eligibility rules
        $bundles = CareBundle::query()
            ->where('is_current_version', true)
            ->whereNotNull('eligibility_rules')
            ->where('auto_recommend', true)
            ->orderBy('priority_weight', 'desc')
            ->get();

        $recommendations = collect();

        foreach ($bundles as $bundle) {
            $result = $this->evaluateBundle($bundle, $patientData);

            if ($result['match_score'] > 0) {
                $recommendations->push([
                    'bundle' => $bundle,
                    'match_score' => $result['match_score'],
                    'passed_rules' => $result['passed_rules'],
                    'failed_rules' => $result['failed_rules'],
                    'required_met' => $result['required_met'],
                ]);

                // Log the recommendation
                $this->logRecommendation($patient, $bundle, $result);
            }
        }

        // Sort by match score then priority weight
        return $recommendations->sortByDesc(function ($rec) {
            return ($rec['match_score'] * 100) + $rec['bundle']->priority_weight;
        })->values();
    }

    /**
     * Evaluate a specific bundle against patient data.
     */
    public function evaluateBundle(CareBundle $bundle, array $patientData): array
    {
        $rules = $bundle->eligibility_rules;
        $passedRules = [];
        $failedRules = [];
        $requiredMet = true;

        if (!$rules) {
            return [
                'match_score' => 100,
                'passed_rules' => [],
                'failed_rules' => [],
                'required_met' => true,
            ];
        }

        // Evaluate combined rules
        $combinedResult = $this->evaluateRuleSet($rules, $patientData);

        // Get and evaluate individual rules
        $individualRules = DB::table('bundle_eligibility_rules')
            ->where('care_bundle_id', $bundle->id)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($individualRules as $rule) {
            $ruleDefinition = json_decode($rule->rule_definition, true);
            $passed = $this->evaluateRuleSet($ruleDefinition, $patientData);

            if ($passed) {
                $passedRules[] = [
                    'name' => $rule->name,
                    'description' => $rule->description,
                ];
            } else {
                $failedRules[] = [
                    'name' => $rule->name,
                    'description' => $rule->description,
                ];

                if ($rule->is_required) {
                    $requiredMet = false;
                }
            }
        }

        // Calculate match score
        $totalRules = count($passedRules) + count($failedRules);
        $matchScore = $totalRules > 0
            ? (count($passedRules) / $totalRules) * 100
            : ($combinedResult ? 100 : 0);

        // If required rules not met, reduce score significantly
        if (!$requiredMet) {
            $matchScore *= 0.5;
        }

        return [
            'match_score' => round($matchScore, 2),
            'passed_rules' => $passedRules,
            'failed_rules' => $failedRules,
            'required_met' => $requiredMet,
        ];
    }

    /**
     * Evaluate a rule set (can be nested with AND/OR operators).
     */
    protected function evaluateRuleSet(array $rules, array $data): bool
    {
        if (isset($rules['operator'])) {
            $operator = strtoupper($rules['operator']);
            $conditions = $rules['conditions'] ?? [];

            if ($operator === 'AND') {
                foreach ($conditions as $condition) {
                    if (!$this->evaluateRuleSet($condition, $data)) {
                        return false;
                    }
                }
                return true;
            }

            if ($operator === 'OR') {
                foreach ($conditions as $condition) {
                    if ($this->evaluateRuleSet($condition, $data)) {
                        return true;
                    }
                }
                return false;
            }
        }

        // Single condition
        return $this->evaluateCondition($rules, $data);
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateCondition(array $condition, array $data): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return false;
        }

        $actualValue = $data[$field] ?? null;

        return match ($operator) {
            '=', '==' => $actualValue == $value,
            '!=' => $actualValue != $value,
            '>' => is_numeric($actualValue) && $actualValue > $value,
            '>=' => is_numeric($actualValue) && $actualValue >= $value,
            '<' => is_numeric($actualValue) && $actualValue < $value,
            '<=' => is_numeric($actualValue) && $actualValue <= $value,
            'contains' => is_array($actualValue)
                ? in_array($value, $actualValue)
                : (is_string($actualValue) && str_contains(strtolower($actualValue), strtolower($value))),
            'not_contains' => is_array($actualValue)
                ? !in_array($value, $actualValue)
                : (is_string($actualValue) && !str_contains(strtolower($actualValue), strtolower($value))),
            'in' => is_array($value) && in_array($actualValue, $value),
            'not_in' => is_array($value) && !in_array($actualValue, $value),
            'is_null' => is_null($actualValue),
            'is_not_null' => !is_null($actualValue),
            'between' => is_array($value) && count($value) === 2 && $actualValue >= $value[0] && $actualValue <= $value[1],
            default => false,
        };
    }

    /**
     * Get patient data formatted for rule evaluation.
     */
    protected function getPatientEvaluationData(Patient $patient, ?array $overrideAssessment = null): array
    {
        // Get latest InterRAI assessment
        $assessment = $overrideAssessment ?? $patient->latestInterraiAssessment?->toArray() ?? [];

        // Build evaluation data from patient and assessment
        return [
            // Patient demographics
            'age' => $patient->date_of_birth ? now()->diffInYears($patient->date_of_birth) : null,
            'gender' => $patient->gender,
            'status' => $patient->status,

            // InterRAI scores
            'maple_score' => $assessment['maple_score'] ?? null,
            'cps' => $assessment['cps'] ?? null,
            'adl_hierarchy' => $assessment['adl_hierarchy'] ?? null,
            'iadl_performance' => $assessment['iadl_performance'] ?? null,
            'chess_score' => $assessment['chess_score'] ?? null,
            'pain_scale' => $assessment['pain_scale'] ?? null,
            'depression_rating' => $assessment['depression_rating'] ?? null,

            // Diagnosis flags (array)
            'diagnosis_flags' => $this->extractDiagnosisFlags($patient, $assessment),

            // CAP triggers (array)
            'cap_triggers' => $assessment['cap_triggers'] ?? [],

            // High risk flags (array)
            'high_risk_flags' => $assessment['high_risk_flags'] ?? [],

            // Clinical history
            'falls_last_90_days' => $assessment['clinical_data']['falls_last_90_days'] ?? null,
            'hospitalizations_last_90_days' => $assessment['clinical_data']['hospitalizations_last_90_days'] ?? null,
            'er_visits_last_90_days' => $assessment['clinical_data']['er_visits_last_90_days'] ?? null,
            'medications_count' => $assessment['clinical_data']['medications_count'] ?? null,
        ];
    }

    /**
     * Extract diagnosis flags from patient and assessment.
     */
    protected function extractDiagnosisFlags(Patient $patient, array $assessment): array
    {
        $flags = [];

        // From patient primary diagnosis
        if ($patient->primary_diagnosis) {
            $diagnosis = strtolower($patient->primary_diagnosis);

            if (str_contains($diagnosis, 'dementia')) $flags[] = 'dementia';
            if (str_contains($diagnosis, 'alzheimer')) $flags[] = 'alzheimers';
            if (str_contains($diagnosis, 'parkinson')) $flags[] = 'parkinsons';
            if (str_contains($diagnosis, 'stroke') || str_contains($diagnosis, 'cva')) $flags[] = 'stroke';
            if (str_contains($diagnosis, 'diabetes')) $flags[] = 'diabetes';
            if (str_contains($diagnosis, 'copd')) $flags[] = 'copd';
            if (str_contains($diagnosis, 'heart') || str_contains($diagnosis, 'cardiac')) $flags[] = 'cardiac';
        }

        // From assessment diagnoses
        $diagnosisList = $assessment['clinical_data']['diagnoses'] ?? [];
        foreach ($diagnosisList as $diagnosis) {
            $diagnosis = strtolower($diagnosis);
            if (str_contains($diagnosis, 'dementia') && !in_array('dementia', $flags)) $flags[] = 'dementia';
            if (str_contains($diagnosis, 'alzheimer') && !in_array('alzheimers', $flags)) $flags[] = 'alzheimers';
        }

        return $flags;
    }

    /**
     * Log a bundle recommendation.
     */
    protected function logRecommendation(Patient $patient, CareBundle $bundle, array $result): void
    {
        DB::table('bundle_recommendation_logs')->insert([
            'patient_id' => $patient->id,
            'care_bundle_id' => $bundle->id,
            'evaluation_results' => json_encode([
                'passed_rules' => $result['passed_rules'],
                'failed_rules' => $result['failed_rules'],
                'required_met' => $result['required_met'],
            ]),
            'match_score' => $result['match_score'],
            'was_selected' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
