<?php

return [
    'port' => (int) env('PORT', 5000),
    'rate_limit_max' => (int) env('RATE_LIMIT_MAX', 100),
    'rate_limit_each_ms' => (int) env('RATE_LIMIT_EACH', 24 * 60 * 60 * 1000),
    'cache_each_seconds' => (int) env('CACHE_EACH', 5),
    'fetch_timeout_ms' => (int) env('FETCH_TIMEOUT', 15000),
    'hadith_api_page_size' => (int) env('HADITH_API_PAGE_SIZE', 15),
    'hadith_site_page_size' => (int) env('HADITH_SITE_PAGE_SIZE', 30),
];
