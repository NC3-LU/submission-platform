<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Configuration defaults that are security-relevant when an operator forgets
 * to set the corresponding env var.
 */
class SecurityDefaultsTest extends TestCase
{
    public function test_cors_does_not_default_to_allowing_every_origin(): void
    {
        // Re-evaluate the config file with the env var absent, so this pins the
        // built-in default rather than whatever the local .env happens to set.
        $original = $_ENV['CORS_ALLOWED_ORIGINS'] ?? null;
        unset($_ENV['CORS_ALLOWED_ORIGINS'], $_SERVER['CORS_ALLOWED_ORIGINS']);
        putenv('CORS_ALLOWED_ORIGINS');

        try {
            $cors = require config_path('cors.php');

            $this->assertNotContains('*', $cors['allowed_origins']);
        } finally {
            if ($original !== null) {
                $_ENV['CORS_ALLOWED_ORIGINS'] = $original;
                $_SERVER['CORS_ALLOWED_ORIGINS'] = $original;
                putenv('CORS_ALLOWED_ORIGINS='.$original);
            }
        }
    }

    public function test_svg_is_not_previewable_as_a_temporary_upload(): void
    {
        // SVGs are script-bearing documents; a same-origin preview URL for one
        // is a stored-XSS primitive.
        $this->assertNotContains('svg', config('livewire.temporary_file_upload.preview_mimes'));
    }
}
