<?php

namespace App\Http\Requests\CC2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateOrganizationProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        $allowedRoles = ['SPO_ADMIN', 'SSPO_ADMIN', 'ORG_ADMIN'];

        return in_array(strtoupper((string) $user->organization_role), $allowedRoles, true);
    }

    public function rules(): array
    {
        $capabilityKeys = ['dementia', 'mental_health', 'clinical', 'community', 'technology'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:se_health,partner,external'],
            'regions' => ['nullable', 'array'],
            'regions.*' => ['string', 'max:255'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['in:' . implode(',', $capabilityKeys)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $regions = $this->input('regions');

        if (is_string($regions)) {
            $regions = array_values(array_filter(array_map(
                fn ($value) => trim($value),
                preg_split('/[\r\n,]+/', $regions) ?: []
            )));
        }

        $capabilities = $this->input('capabilities', []);
        if (!is_array($capabilities)) {
            $capabilities = array_filter(array_map('trim', explode(',', (string) $capabilities)));
        }

        $this->merge([
            'regions' => $regions ?? [],
            'capabilities' => array_values(array_unique($capabilities)),
        ]);
    }
}
