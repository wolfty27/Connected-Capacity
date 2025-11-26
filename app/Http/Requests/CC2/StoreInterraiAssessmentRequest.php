<?php

namespace App\Http\Requests\CC2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DQ-005: Form request for creating InterRAI HC assessments
 *
 * Validates InterRAI assessment data including all clinical scores
 * and CAP triggers per OHaH requirements.
 */
class StoreInterraiAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\InterraiAssessment::class);
    }

    public function rules(): array
    {
        return [
            // Core assessment info
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id'),
            ],
            'assessment_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'source' => [
                'required',
                Rule::in(['spo_completed', 'iar_imported', 'chris_synced', 'manual_entry']),
            ],
            'assessor_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],

            // Clinical scales (0-6 typical range)
            'maple_score' => [
                'required',
                'integer',
                'min:1',
                'max:5',
            ],
            'cps' => [
                'required',
                'integer',
                'min:0',
                'max:6',
            ],
            'adl_hierarchy' => [
                'required',
                'integer',
                'min:0',
                'max:6',
            ],
            'iadl_performance' => [
                'nullable',
                'integer',
                'min:0',
                'max:48',
            ],
            'chess_score' => [
                'nullable',
                'integer',
                'min:0',
                'max:5',
            ],
            'pain_scale' => [
                'nullable',
                'integer',
                'min:0',
                'max:4',
            ],
            'depression_rating' => [
                'nullable',
                'integer',
                'min:0',
                'max:14',
            ],

            // Functional assessment
            'functional_data' => [
                'nullable',
                'array',
            ],
            'functional_data.bathing' => ['nullable', 'integer', 'min:0', 'max:6'],
            'functional_data.dressing_upper' => ['nullable', 'integer', 'min:0', 'max:6'],
            'functional_data.dressing_lower' => ['nullable', 'integer', 'min:0', 'max:6'],
            'functional_data.personal_hygiene' => ['nullable', 'integer', 'min:0', 'max:6'],
            'functional_data.toilet_use' => ['nullable', 'integer', 'min:0', 'max:6'],
            'functional_data.eating' => ['nullable', 'integer', 'min:0', 'max:6'],
            'functional_data.locomotion_inside' => ['nullable', 'integer', 'min:0', 'max:6'],
            'functional_data.locomotion_outside' => ['nullable', 'integer', 'min:0', 'max:6'],
            'functional_data.transfer_toilet' => ['nullable', 'integer', 'min:0', 'max:6'],

            // Clinical assessment
            'clinical_data' => [
                'nullable',
                'array',
            ],
            'clinical_data.diagnoses' => ['nullable', 'array'],
            'clinical_data.diagnoses.*' => ['string', 'max:255'],
            'clinical_data.medications_count' => ['nullable', 'integer', 'min:0'],
            'clinical_data.falls_last_90_days' => ['nullable', 'integer', 'min:0'],
            'clinical_data.hospitalizations_last_90_days' => ['nullable', 'integer', 'min:0'],
            'clinical_data.er_visits_last_90_days' => ['nullable', 'integer', 'min:0'],

            // CAP triggers
            'cap_triggers' => [
                'nullable',
                'array',
            ],
            'cap_triggers.*' => [
                'string',
                Rule::in([
                    'falls',
                    'pain',
                    'pressure_ulcer',
                    'dehydration',
                    'nutrition',
                    'cognitive_loss',
                    'communication',
                    'adl_decline',
                    'social_isolation',
                    'caregiver_distress',
                    'medication_management',
                    'urinary_incontinence',
                    'bowel_conditions',
                    'physical_restraints',
                    'institutional_risk',
                ]),
            ],

            // High risk flags
            'high_risk_flags' => [
                'nullable',
                'array',
            ],
            'high_risk_flags.*' => ['string', 'max:100'],

            // IAR integration
            'iar_submission_id' => [
                'nullable',
                'string',
                'max:100',
            ],
            'iar_status' => [
                'nullable',
                Rule::in(['pending', 'uploaded', 'failed', 'not_required']),
            ],

            // Notes
            'assessment_notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient is required for assessment.',
            'assessment_date.required' => 'Assessment date is required.',
            'assessment_date.before_or_equal' => 'Assessment date cannot be in the future.',
            'source.required' => 'Assessment source must be specified.',
            'maple_score.required' => 'MAPLe score is required.',
            'maple_score.min' => 'MAPLe score must be at least 1.',
            'maple_score.max' => 'MAPLe score cannot exceed 5.',
            'cps.required' => 'Cognitive Performance Scale (CPS) is required.',
            'cps.max' => 'CPS score cannot exceed 6.',
            'adl_hierarchy.required' => 'ADL Hierarchy score is required.',
            'adl_hierarchy.max' => 'ADL Hierarchy score cannot exceed 6.',
            'chess_score.max' => 'CHESS score cannot exceed 5.',
        ];
    }
}
