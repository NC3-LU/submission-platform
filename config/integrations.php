<?php

return [
    // These operations must never run inside an HTTP request, even if the default queue is sync.
    'queue_connection' => env('INTEGRATION_QUEUE_CONNECTION', 'database'),
    'webhooks' => ['enabled' => (bool) env('WEBHOOKS_ENABLED', false)],
    'exports' => [
        'max_rows' => 10000,
        'max_fields' => 100,
        'max_bytes' => 50 * 1024 * 1024,
        'retention_hours' => 24,
    ],
];
