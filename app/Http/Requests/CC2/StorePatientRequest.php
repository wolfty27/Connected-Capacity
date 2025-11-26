<?php

namespace App\Http\Requests\CC2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DQ-005: Form request for creating patients
 *
 * Validates patient registration including demographics,
 * contact info, and health card details per OHaH requirements.
 */
class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Patient::class);
    }

    public function rules(): array
    {
        return [
            // Basic demographics
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],
            'last_name' => [
                'required',
                'string',
                'max:100',
            ],
            'date_of_birth' => [
                'required',
                'date',
                'before:today',
            ],
            'gender' => [
                'nullable',
                Rule::in(['male', 'female', 'other', 'prefer_not_to_say']),
            ],

            // Health card
            'health_card_number' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9]{10}[A-Z]{2}$/', // Ontario format
                'unique:patients,health_card_number',
            ],
            'health_card_version' => [
                'nullable',
                'string',
                'max:2',
            ],

            // Contact info
            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'preferred_language' => [
                'nullable',
                Rule::in(['en', 'fr', 'other']),
            ],

            // Address
            'address_line_1' => [
                'required',
                'string',
                'max:255',
            ],
            'address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],
            'city' => [
                'required',
                'string',
                'max:100',
            ],
            'province' => [
                'required',
                'string',
                'size:2',
                Rule::in(['ON', 'QC', 'BC', 'AB', 'MB', 'SK', 'NS', 'NB', 'NL', 'PE', 'YT', 'NT', 'NU']),
            ],
            'postal_code' => [
                'required',
                'string',
                'regex:/^[A-Z][0-9][A-Z] ?[0-9][A-Z][0-9]$/i',
            ],

            // Emergency contact
            'emergency_contact_name' => [
                'nullable',
                'string',
                'max:200',
            ],
            'emergency_contact_phone' => [
                'nullable',
                'string',
                'max:20',
            ],
            'emergency_contact_relationship' => [
                'nullable',
                'string',
                'max:50',
            ],

            // Clinical info
            'primary_diagnosis' => [
                'nullable',
                'string',
                'max:255',
            ],
            'allergies' => [
                'nullable',
                'array',
            ],
            'allergies.*' => [
                'string',
                'max:100',
            ],

            // Organization
            'spo_id' => [
                'nullable',
                'integer',
                Rule::exists('service_provider_organizations', 'id'),
            ],

            // Status
            'status' => [
                'sometimes',
                Rule::in([
                    'referral_received',
                    'intake_pending',
                    'triage_in_progress',
                    'triage_complete',
                    'assessment_pending',
                    'bundle_building',
                    'bundle_pending_approval',
                    'active',
                    'on_hold',
                    'discharged',
                    'deceased',
                    'transferred',
                ]),
            ],

            // Notes
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Patient first name is required.',
            'last_name.required' => 'Patient last name is required.',
            'date_of_birth.required' => 'Date of birth is required.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'health_card_number.required' => 'Health card number is required.',
            'health_card_number.regex' => 'Health card number must be in Ontario format (10 digits followed by 2 letters).',
            'health_card_number.unique' => 'A patient with this health card number already exists.',
            'address_line_1.required' => 'Address is required.',
            'city.required' => 'City is required.',
            'province.required' => 'Province is required.',
            'province.in' => 'Invalid province code.',
            'postal_code.required' => 'Postal code is required.',
            'postal_code.regex' => 'Invalid postal code format.',
        ];
    }
}
