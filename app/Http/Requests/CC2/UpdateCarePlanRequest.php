<?php

namespace App\Http\Requests\CC2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DQ-005: Form request for updating care plans
 */
class UpdateCarePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $carePlan = $this->route('care_plan') ?? $this->route('carePlan');
        return $this->user()->can('update', $carePlan);
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                Rule::in(['draft', 'pending_approval', 'approved', 'active', 'completed', 'cancelled']),
            ],
            'start_date' => [
                'sometimes',
                'date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after:start_date',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'services' => [
                'sometimes',
                'array',
            ],
            'services.*.id' => [
                'nullable',
                'integer',
            ],
            'services.*.service_type_id' => [
                'required_with:services',
                'integer',
                Rule::exists('service_types', 'id'),
            ],
            'services.*.frequency' => [
                'required_with:services',
                'string',
                Rule::in(['daily', 'weekly', 'biweekly', 'monthly', 'as_needed']),
            ],
            'services.*.visits_per_week' => [
                'required_with:services',
                'integer',
                'min:0',
                'max:14',
            ],
            'services.*.duration_minutes' => [
                'required_with:services',
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
            'services.*._delete' => [
                'sometimes',
                'boolean',
            ],
            'approval_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'approved_by' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'approved_at' => [
                'nullable',
                'date',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Invalid status value provided.',
            'end_date.after' => 'End date must be after the start date.',
            'services.*.visits_per_week.max' => 'Visits per week cannot exceed 14.',
            'services.*.duration_minutes.min' => 'Service duration must be at least 15 minutes.',
        ];
    }
}
