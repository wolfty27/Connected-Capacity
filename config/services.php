<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ontario Health Integration Services
    |--------------------------------------------------------------------------
    |
    | Configuration for Ontario Health system integrations required for
    | OHaH compliance including IAR, CHRIS, and HPG.
    |
    */

    'iar' => [
        'endpoint' => env('IAR_API_ENDPOINT'),
        'api_key' => env('IAR_API_KEY'),
        'organization_id' => env('IAR_ORGANIZATION_ID'),
        'submitter_id' => env('IAR_SUBMITTER_ID'),
        'timeout' => env('IAR_TIMEOUT', 30),
        // Mock settings for development (IR-008)
        'use_mock' => env('IAR_USE_MOCK', true),
        'mock_mode' => env('IAR_MOCK_MODE', 'success'), // success, delayed, random_failure, always_fail
        'mock_delay_ms' => env('IAR_MOCK_DELAY_MS', 100),
    ],

    'chris' => [
        'endpoint' => env('CHRIS_API_ENDPOINT'),
        'api_key' => env('CHRIS_API_KEY'),
        'facility_id' => env('CHRIS_FACILITY_ID'),
    ],

    'hpg' => [
        'webhook_secret' => env('HPG_WEBHOOK_SECRET'),
        'response_sla_minutes' => env('HPG_RESPONSE_SLA_MINUTES', 15),
    ],

];