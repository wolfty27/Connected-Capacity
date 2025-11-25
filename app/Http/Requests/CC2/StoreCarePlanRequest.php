<?php

namespace App\Http\Requests\CC2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DQ-005: Form request for creating care plans
 *
 * Validates all required fields for care plan creation including
 * bundle selection, service configuration, and approval workflow.
 */
class StoreCarePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\CarePlan::class);
    }

    public function rules(): array
    {
        return [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id'),
            ],
            'bundle_template_id' => [
                'required',
                'integer',
                Rule::exists('care_bundles', 'id'),
            ],
            'start_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after:start_date',
            ],
            'status' => [
                'sometimes',
                Rule::in(['draft', 'pending_approval', 'approved', 'active', 'completed', 'cancelled']),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'services' => [
                'required',
                'array',
                'min:1',
            ],
            'services.*.service_type_id' => [
                'required',
                'integer',
                Rule::exists('service_types', 'id'),
            ],
            'services.*.frequency' => [
                'required',
                'string',
                Rule::in(['daily', 'weekly', 'biweekly', 'monthly', 'as_needed']),
            ],
            'services.*.visits_per_week' => [
                'required',
                'integer',
                'min:0',
                'max:14',
            ],
            'services.*.duration_minutes' => [
                'required',
                'integer',
                'min:15',
                'max:480',
            ],
            'services.*.sspo_id' => [
                'nullable',
                'integer',
                Rule::exists('sspo_organizations', 'id'),
            ],
            'services.*.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'clinical_justification' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'tnp_id' => [
                'nullable',
                'integer',
                Rule::exists('transition_needs_profiles', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'A patient must be selected for this care plan.',
            'patient_id.exists' => 'The selected patient does not exist.',
            'bundle_template_id.required' => 'A care bundle template must be selected.',
            'bundle_template_id.exists' => 'The selected bundle template does not exist.',
            'start_date.required' => 'A start date is required.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'services.required' => 'At least one service must be configured.',
            'services.min' => 'At least one service must be configured.',
            'services.*.service_type_id.required' => 'Each service must have a service type.',
            'services.*.visits_per_week.max' => 'Visits per week cannot exceed 14 (2 per day).',
            'services.*.duration_minutes.min' => 'Service duration must be at least 15 minutes.',
            'services.*.duration_minutes.max' => 'Service duration cannot exceed 8 hours (480 minutes).',
        ];
    }
}
