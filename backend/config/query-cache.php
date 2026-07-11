<?php

return [
    'prefix' => env('QUERY_CACHE_PREFIX', 'micro_service_backend'),

    'default_ttl' => (int) env('QUERY_CACHE_TTL', 300),

    'models' => [
        'user' => (int) env('QUERY_CACHE_USER_TTL', env('QUERY_CACHE_TTL', 300)),
        'tenant' => (int) env('QUERY_CACHE_TENANT_TTL', env('QUERY_CACHE_TTL', 300)),
        'branch' => (int) env('QUERY_CACHE_BRANCH_TTL', env('QUERY_CACHE_TTL', 300)),
        'category' => (int) env('QUERY_CACHE_CATEGORY_TTL', env('QUERY_CACHE_TTL', 300)),
        'currency' => (int) env('QUERY_CACHE_CURRENCY_TTL', env('QUERY_CACHE_TTL', 300)),
        'item' => (int) env('QUERY_CACHE_ITEM_TTL', env('QUERY_CACHE_TTL', 300)),
        'price_list' => (int) env('QUERY_CACHE_PRICE_LIST_TTL', env('QUERY_CACHE_TTL', 300)),
        'slider' => (int) env('QUERY_CACHE_SLIDER_TTL', env('QUERY_CACHE_TTL', 300)),
    ],
];
