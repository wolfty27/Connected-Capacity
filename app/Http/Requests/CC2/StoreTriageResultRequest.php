<?php

namespace App\Http\Requests\CC2;

use App\Models\TriageResult;
use Illuminate\Foundation\Http\FormRequest;

class StoreTriageResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TriageResult::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'acuity_level' => ['required', 'in:low,medium,high,critical'],
            'dementia_flag' => ['sometimes', 'boolean'],
            'mh_flag' => ['sometimes', 'boolean'],
            'rpm_required' => ['sometimes', 'boolean'],
            'fall_risk' => ['sometimes', 'boolean'],
            'behavioural_risk' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['dementia_flag', 'mh_flag', 'rpm_required', 'fall_risk', 'behavioural_risk'] as $booleanField) {
            if (!$this->has($booleanField)) {
                continue;
            }

            $value = filter_var(
                $this->input($booleanField),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($value !== null) {
                $this->merge([
                    $booleanField => $value,
                ]);
            }
        }
    }
}
