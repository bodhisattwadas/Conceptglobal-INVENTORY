<?php

return [
    'provider' => env('WHATSAPP_PROVIDER', 'meta'), // 'meta' or 'wati'

    'meta' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),
        'template_name' => env('WHATSAPP_TEMPLATE_NAME', 'billing_template'),
        'language_code' => env('WHATSAPP_LANGUAGE_CODE', 'en'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '91'),
    ],

    'wati' => [
        'endpoint' => rtrim((string) env('WATI_API_ENDPOINT', ''), '/'),
        'token' => env('WATI_ACCESS_TOKEN'),
        'template_name' => env('WATI_TEMPLATE_NAME', 'billing_template'),
        'default_country_code' => env('WATI_DEFAULT_COUNTRY_CODE', '91'),
    ],
];
