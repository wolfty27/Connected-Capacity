<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        // FE-005: Client-side error logging
        'client_errors' => [
            'driver' => 'daily',
            'path' => storage_path('logs/client-errors.log'),
            'level' => 'error',
            'days' => 30,
        ],

        // SLA compliance alerts
        'sla_alerts' => [
            'driver' => 'daily',
            'path' => storage_path('logs/sla-alerts.log'),
            'level' => 'warning',
            'days' => 90,
        ],

        // IAR integration logging
        'iar_integration' => [
            'driver' => 'daily',
            'path' => storage_path('logs/iar-integration.log'),
            'level' => 'info',
            'days' => 90,
        ],

    ],

];