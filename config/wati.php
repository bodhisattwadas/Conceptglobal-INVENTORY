<?php

return [
    'endpoint' => rtrim((string) env('WATI_API_ENDPOINT', ''), '/'),
    'token' => env('WATI_ACCESS_TOKEN'),
    'template_name' => env('WATI_TEMPLATE_NAME', 'billing_template'),
    'default_country_code' => env('WATI_DEFAULT_COUNTRY_CODE', '91'),
];
