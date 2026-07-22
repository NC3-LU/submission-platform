<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('label');
            $table->text('value')->nullable();
            $table->string('type'); // text, number, select, toggle, textarea
            $table->json('attributes')->nullable(); // for select options, input hints, etc.
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default API security settings with best practices
        $settings = [
            // Rate Limiting - Authenticated (balanced for production use)
            [
                'key' => 'rate_limit_api_authenticated',
                'label' => 'API Rate Limit (Authenticated)',
                'value' => '100',
                'type' => 'number',
                'description' => 'Requests per minute for authenticated API tokens',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Rate Limiting - Unauthenticated (restrictive to prevent abuse)
            [
                'key' => 'rate_limit_api_unauthenticated',
                'label' => 'API Rate Limit (Unauthenticated)',
                'value' => '10',
                'type' => 'number',
                'description' => 'Requests per minute for unauthenticated requests (by IP) - restrictive to prevent abuse',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Authentication Attempts (security best practice - strict)
            [
                'key' => 'rate_limit_auth_attempts',
                'label' => 'Authentication Attempts Limit',
                'value' => '3',
                'type' => 'number',
                'description' => 'Maximum authentication attempts per minute per IP (prevents brute force)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Submissions - Read Operations (generous for read-heavy workloads)
            [
                'key' => 'rate_limit_submissions_read',
                'label' => 'Submissions Read Rate Limit',
                'value' => '100',
                'type' => 'number',
                'description' => 'GET requests per minute for submissions endpoint',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Submissions - Write Operations (controlled to prevent spam)
            [
                'key' => 'rate_limit_submissions_write',
                'label' => 'Submissions Write Rate Limit',
                'value' => '30',
                'type' => 'number',
                'description' => 'POST/PUT/PATCH requests per minute for submissions (controlled to prevent spam)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Submissions - Daily Limit (reasonable daily quota)
            [
                'key' => 'rate_limit_submissions_daily',
                'label' => 'Submissions Daily Limit',
                'value' => '500',
                'type' => 'number',
                'description' => 'Maximum submissions per day per token (reasonable daily quota)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // API Docs Access (organization-specific domains)
            [
                'key' => 'api_docs_allowed_domains',
                'label' => 'API Docs Allowed Domains',
                'value' => 'lhc.lu,circl.lu,nc3.lu',
                'type' => 'textarea',
                'description' => 'Comma-separated list of email domains allowed to access API documentation',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // CORS - Development + Production Origins
            [
                'key' => 'cors_allowed_origins',
                'label' => 'CORS Allowed Origins',
                'value' => 'http://localhost:3000,http://localhost:5173,http://localhost:8080',
                'type' => 'textarea',
                'description' => 'Comma-separated list of origins allowed for CORS requests (update for production domains)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Sanctum Token Prefix (GitHub security scanning)
            [
                'key' => 'sanctum_token_prefix',
                'label' => 'Sanctum Token Prefix',
                'value' => 'nc3_',
                'type' => 'text',
                'description' => 'Prefix for new Sanctum tokens (helps with GitHub secret scanning)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // API Logging (enabled by default for security auditing)
            [
                'key' => 'api_logging_enabled',
                'label' => 'API Request Logging',
                'value' => '1',
                'type' => 'toggle',
                'description' => 'Enable logging of all API requests for audit purposes',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Use updateOrInsert for idempotency (safe to re-run)
        foreach ($settings as $setting) {
            DB::table('api_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_settings');
    }
};
