<?php

return [
    // Per form and authenticated account, or per client IP for guests.
    'per_minute' => (int) env('SUBMISSION_LIMIT_PER_MINUTE', 10),
    'per_day' => (int) env('SUBMISSION_LIMIT_PER_DAY', 100),
    'uploads_per_minute' => (int) env('SUBMISSION_UPLOADS_PER_MINUTE', 20),
    'temporary_file_hours' => (int) env('SUBMISSION_TEMP_FILE_HOURS', 48),
];
