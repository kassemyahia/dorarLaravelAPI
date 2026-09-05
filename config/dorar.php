<?php

return [
    'port' => (int) env('PORT', 5000),
    'rate_limit_max' => (int) env('RATE_LIMIT_MAX', 100),
    'rate_limit_each_ms' => (int) env('RATE_LIMIT_EACH', 24 * 60 * 60 * 1000),
    'cache_each_seconds' => (int) env('CACHE_EACH', 5),
    'fetch_timeout_ms' => (int) env('FETCH_TIMEOUT', 15000),
    'fetch_retries' => (int) env('FETCH_RETRIES', 4),
    'fetch_retry_base_ms' => (int) env('FETCH_RETRY_BASE_MS', 1000),
    'enrichment_search_attempts' => (int) env('ENRICHMENT_SEARCH_ATTEMPTS', 2),
    'enrichment_chunk_size' => (int) env('ENRICHMENT_CHUNK_SIZE', 25),
    'enrichment_retention_days' => (int) env('ENRICHMENT_RETENTION_DAYS', 30),
    'site_url' => env('DORAR_SITE_URL', 'https://www.dorar.net'),
    'api_url' => env('DORAR_API_URL', 'https://dorar.net'),
    'hadith_api_page_size' => (int) env('HADITH_API_PAGE_SIZE', 15),
    'hadith_site_page_size' => (int) env('HADITH_SITE_PAGE_SIZE', 30),
];
