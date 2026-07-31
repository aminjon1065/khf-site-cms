<?php

return [
    'api' => [
        'slow_request_ms' => (float) env('OBSERVABILITY_SLOW_REQUEST_MS', 750),
        'sample_rate' => (float) env('OBSERVABILITY_API_SAMPLE_RATE', 0.01),
    ],

    'database' => [
        'slow_query_ms' => (float) env('OBSERVABILITY_SLOW_QUERY_MS', 250),
    ],
];
