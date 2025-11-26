<?php

namespace App\Http\Requests\CC2;

use App\Models\Referral;
use Illuminate\Foundation\Http\FormRequest;

class StoreReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Referral::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'service_type_id' => ['nullable', 'exists:service_types,id'],
            'service_provider_organization_id' => ['nullable', 'exists:service_provider_organizations,id'],
            'intake_notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $metadata = $this->input('metadata');

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $metadata = $decoded;
            } else {
                $metadata = null;
            }
        }

        if ($metadata === null) {
            $metadata = [];
        }

        $this->merge([
            'metadata' => $metadata,
        ]);
    }
}
