<?php

namespace App\Http\Requests\CC2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DQ-005: Form request for creating service assignments
 *
 * Validates service assignment creation including scheduling,
 * staff assignment, and SSPO delegation.
 */
class StoreServiceAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\ServiceAssignment::class);
    }

    public function rules(): array
    {
        return [
            'care_plan_id' => [
                'required',
                'integer',
                Rule::exists('care_plans', 'id'),
            ],
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id'),
            ],
            'service_type_id' => [
                'required',
                'integer',
                Rule::exists('service_types', 'id'),
            ],
            'assigned_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'sspo_id' => [
                'nullable',
                'integer',
                Rule::exists('sspo_organizations', 'id'),
            ],
            'scheduled_start' => [
                'required',
                'date',
            ],
            'scheduled_end' => [
                'required',
                'date',
                'after:scheduled_start',
            ],
            'status' => [
                'sometimes',
                Rule::in([
                    'scheduled',
                    'pending_acceptance',
                    'accepted',
                    'in_progress',
                    'completed',
                    'cancelled',
                    'missed',
                    'rescheduled',
                ]),
            ],
            'priority' => [
                'sometimes',
                Rule::in(['normal', 'high', 'urgent']),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'recurrence_pattern' => [
                'nullable',
                Rule::in(['none', 'daily', 'weekly', 'biweekly', 'monthly']),
            ],
            'recurrence_end_date' => [
                'nullable',
                'date',
                'after:scheduled_start',
                'required_unless:recurrence_pattern,none,null',
            ],
            'location_type' => [
                'nullable',
                Rule::in(['patient_home', 'clinic', 'virtual', 'other']),
            ],
            'location_notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'care_plan_id.required' => 'Service assignment must be linked to a care plan.',
            'patient_id.required' => 'Patient is required.',
            'service_type_id.required' => 'Service type must be specified.',
            'scheduled_start.required' => 'Scheduled start time is required.',
            'scheduled_end.required' => 'Scheduled end time is required.',
            'scheduled_end.after' => 'End time must be after start time.',
            'recurrence_end_date.required_unless' => 'Recurrence end date is required when using recurrence.',
        ];
    }
}
