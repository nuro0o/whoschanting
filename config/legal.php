<?php

return [
    'version' => '2026-09-13.3',
    'updated_at' => '2026-09-13',
    'support_email' => 'whoschanting.support@geniousverse.app',
    // Supply the actual operator details; do not substitute a brand for a legal entity.
    'operator_name' => env('LEGAL_OPERATOR_NAME'),
    'business_address' => env('LEGAL_BUSINESS_ADDRESS'),
    'registration_number' => env('LEGAL_REGISTRATION_NUMBER'),
    'minimum_age' => env('LEGAL_MINIMUM_AGE'),
    'request_retention_days' => 365,
];
