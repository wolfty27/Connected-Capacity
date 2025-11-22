<?php

use App\Models\User;

return [
    'roles' => [
        User::ROLE_ADMIN => 'Admin',
        User::ROLE_HOSPITAL => 'Hospital',
        User::ROLE_RETIREMENT_HOME => 'Retirement Home',
        User::ROLE_SPO_ADMIN => 'SPO Admin',
        User::ROLE_FIELD_STAFF => 'Field Staff',
        User::ROLE_PATIENT => 'Patient',
        User::ROLE_SPO_COORDINATOR => 'SPO Coordinator',
        User::ROLE_SSPO_ADMIN => 'SSPO Admin',
        User::ROLE_SSPO_COORDINATOR => 'SSPO Coordinator',
        User::ROLE_ORG_ADMIN => 'Organization Admin',
        User::ROLE_MASTER => 'Master Admin',
    ],
    'ai' => [
        'gemini_api_key' => env('GEMINI_API_KEY'),
    ],
];
