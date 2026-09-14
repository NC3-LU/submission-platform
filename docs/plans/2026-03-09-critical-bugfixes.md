# Critical Bugfixes Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix 5 critical/high priority bugs: guest submission crash (#37), missing ip_address column (#38), rate limiter crash (#45), orphaned files (#41), and path traversal hardening (#36). Plus UserFactory missing role (#46).

**Architecture:** Each fix is a small, independent migration + model/controller change. All are backwards-compatible. Tests validate each fix in isolation.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL 8.0, PHPUnit

---

### Task 1: Fix #37 — Make `user_id` nullable on submissions table

The `submissions.user_id` column is `NOT NULL` but guest (unauthenticated) submissions pass `null` via `auth()->id()`, causing a database constraint violation.

**Files:**
- Create: `database/migrations/2026_03_09_000001_make_submissions_user_id_nullable.php`
- Test: `tests/Feature/SubmissionNullableUserTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionNullableUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_submission_on_public_form(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);
        $category = $form->categories()->create(['name' => 'General', 'order' => 1]);
        $field = $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Name',
            'type' => 'text',
            'required' => false,
            'order' => 1,
        ]);

        $response = $this->post(route('submissions.store', $form), [
            'field_' . $field->id => 'Test Value',
        ]);

        $response->assertRedirect(route('submissions.thankyou'));
        $this->assertDatabaseHas('submissions', [
            'form_id' => $form->id,
            'user_id' => null,
        ]);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/SubmissionNullableUserTest.php --filter=test_guest`
Expected: FAIL — database constraint error (user_id cannot be null)

**Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
```

Also update the SQLite branch in `database/migrations/2024_10_31_153758_uuidtoidsubmission.php` line 31: change `$table->unsignedBigInteger('user_id');` to `$table->unsignedBigInteger('user_id')->nullable();`.

And update the original migration `database/migrations/2024_10_08_142022_create_submissions_table.php` line 17: change `$table->unsignedBigInteger('user_id');` to `$table->unsignedBigInteger('user_id')->nullable();`.

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/SubmissionNullableUserTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add database/migrations/2026_03_09_000001_make_submissions_user_id_nullable.php \
        database/migrations/2024_10_31_153758_uuidtoidsubmission.php \
        database/migrations/2024_10_08_142022_create_submissions_table.php \
        tests/Feature/SubmissionNullableUserTest.php
git commit -m "fix: make submissions.user_id nullable for guest submissions (#37)"
```

---

### Task 2: Fix #38 — Add `ip_address` column to submissions table

The API SubmissionController tries to save `ip_address` but the column doesn't exist. The SubmissionResource also exposes it.

**Files:**
- Create: `database/migrations/2026_03_09_000002_add_ip_address_to_submissions.php`
- Modify: `app/Models/Submission.php` — add `ip_address` to `$fillable`

**Step 1: Write the failing test**

Add to `tests/Feature/SubmissionNullableUserTest.php`:

```php
public function test_submission_can_store_ip_address(): void
{
    $user = User::factory()->create(['role' => 'user']);
    $form = Form::factory()->for($user)->create([
        'status' => 'published',
        'visibility' => 'public',
    ]);

    $submission = \App\Models\Submission::create([
        'form_id' => $form->id,
        'user_id' => $user->id,
        'ip_address' => '192.168.1.1',
        'status' => 'submitted',
    ]);

    $this->assertDatabaseHas('submissions', [
        'id' => $submission->id,
        'ip_address' => '192.168.1.1',
    ]);
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/SubmissionNullableUserTest.php --filter=test_submission_can_store_ip`
Expected: FAIL — column ip_address doesn't exist

**Step 3: Create migration and update model**

Migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }
};
```

Update `app/Models/Submission.php` `$fillable` to add `'ip_address'`.

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/SubmissionNullableUserTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add database/migrations/2026_03_09_000002_add_ip_address_to_submissions.php \
        app/Models/Submission.php \
        tests/Feature/SubmissionNullableUserTest.php
git commit -m "fix: add ip_address column to submissions table (#38)"
```

---

### Task 3: Fix #45 — Rate limiters crash on empty `api_settings` table

`ApiSetting::get('rate_limit_api_authenticated')` returns `null` when the table is empty/unseeded. `(int) null` = `0`, and `max(1, 0)` = `1` which is actually fine — BUT the real crash is that `ApiSetting::get()` queries the DB inside `Cache::remember()` and if the table doesn't exist yet (fresh install, pre-migration), it throws a QueryException.

**Files:**
- Modify: `app/Providers/AppServiceProvider.php:82-147` — wrap rate limiter DB calls in try-catch with sensible defaults

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimiterDefaultsTest extends TestCase
{
    public function test_api_rate_limiter_returns_limit_without_api_settings(): void
    {
        // Clear any cached settings
        \Illuminate\Support\Facades\Cache::flush();

        $request = Request::create('/api/test', 'GET');
        $limiter = RateLimiter::limiter('api');

        $result = $limiter($request);

        $this->assertInstanceOf(Limit::class, $result);
    }
}
```

**Step 2: Run test to verify behavior**

Run: `./vendor/bin/phpunit tests/Unit/RateLimiterDefaultsTest.php`

**Step 3: Wrap rate limiter registrations in try-catch**

In `app/Providers/AppServiceProvider.php`, update `registerRateLimiters()`:

```php
private function registerRateLimiters(): void
{
    RateLimiter::for('api', function (Request $request) {
        $apiToken = $request->attributes->get('api_token');

        try {
            if ($apiToken) {
                $limit = max(1, (int) ApiSetting::get('rate_limit_api_authenticated', 60));
                return Limit::perMinute($limit)->by('token:' . $apiToken->id);
            }

            $limit = max(1, (int) ApiSetting::get('rate_limit_api_unauthenticated', 30));
            return Limit::perMinute($limit)->by('ip:' . $request->ip());
        } catch (\Throwable $e) {
            // Fallback when api_settings table doesn't exist
            $key = $apiToken ? 'token:' . $apiToken->id : 'ip:' . $request->ip();
            return Limit::perMinute(60)->by($key);
        }
    });

    RateLimiter::for('api-auth', function (Request $request) {
        try {
            $limit = max(1, (int) ApiSetting::get('rate_limit_auth_attempts', 5));
            return Limit::perMinute($limit)->by('ip:' . $request->ip());
        } catch (\Throwable $e) {
            return Limit::perMinute(5)->by('ip:' . $request->ip());
        }
    });

    RateLimiter::for('api-submissions', function (Request $request) {
        $apiToken = $request->attributes->get('api_token');
        $identifier = $apiToken?->id ?? $request->ip();

        try {
            if ($request->isMethod('GET')) {
                $limit = max(1, (int) ApiSetting::get('rate_limit_submissions_read', 60));
                return Limit::perMinute($limit)->by('token:' . $identifier);
            }

            $writeLimit = max(1, (int) ApiSetting::get('rate_limit_submissions_write', 30));
            $dailyLimit = max(1, (int) ApiSetting::get('rate_limit_submissions_daily', 1000));

            return [
                Limit::perMinute($writeLimit)->by('token:' . $identifier),
                Limit::perDay($dailyLimit)->by('daily:token:' . $identifier),
            ];
        } catch (\Throwable $e) {
            if ($request->isMethod('GET')) {
                return Limit::perMinute(60)->by('token:' . $identifier);
            }
            return [
                Limit::perMinute(30)->by('token:' . $identifier),
                Limit::perDay(1000)->by('daily:token:' . $identifier),
            ];
        }
    });

    // Export rate limiters (no DB dependency — already safe)
    RateLimiter::for('export', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('bulk-export', function (Request $request) {
        return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
    });
}
```

Remove the four separate private methods (`registerGeneralApiRateLimiter`, `registerApiAuthRateLimiter`, `registerApiSubmissionsRateLimiter`, `registerExportRateLimiters`) — they're inlined now.

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/RateLimiterDefaultsTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php tests/Unit/RateLimiterDefaultsTest.php
git commit -m "fix: add fallback defaults for rate limiters when api_settings is empty (#45)"
```

---

### Task 4: Fix #41 — Orphaned files when forms are deleted

`FormController::destroy()` calls `$form->delete()` without cleaning up uploaded files from submission values. The foreign key cascades delete submissions and values rows, but files on disk remain.

**Files:**
- Modify: `app/Models/Form.php` — add `booted()` with `deleting` event
- Test: `tests/Feature/FormDeletionCleanupTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormDeletionCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_form_removes_submission_files_from_storage(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['role' => 'admin']);
        $form = Form::factory()->for($user)->create(['status' => 'draft']);
        $category = $form->categories()->create(['name' => 'General', 'order' => 1]);
        $field = $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Document',
            'type' => 'file',
            'required' => false,
            'order' => 1,
        ]);

        $submission = $form->submissions()->create([
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);

        // Create a fake file in storage
        $filePath = "submissions/{$submission->id}/test-document.pdf";
        Storage::disk('private')->put($filePath, 'fake content');

        $submission->values()->create([
            'form_field_id' => $field->id,
            'value' => $filePath,
        ]);

        Storage::disk('private')->assertExists($filePath);

        // Delete the form
        $form->delete();

        // File should be cleaned up
        Storage::disk('private')->assertMissing($filePath);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/FormDeletionCleanupTest.php`
Expected: FAIL — file still exists after deletion

**Step 3: Add deleting event to Form model**

In `app/Models/Form.php`, add a `booted` method:

```php
protected static function booted(): void
{
    static::deleting(function (Form $form) {
        // Collect all file paths from submission values before cascade delete removes them
        $filePaths = \App\Models\SubmissionValues::whereIn(
            'submission_id',
            $form->submissions()->select('id')
        )
            ->whereHas('field', fn ($q) => $q->where('type', 'file'))
            ->pluck('value')
            ->filter();

        foreach ($filePaths as $path) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete($path);
            // Also try temp location
            $tempPath = str_replace('submissions/', 'temp-submissions/', $path);
            \Illuminate\Support\Facades\Storage::disk('private')->delete($tempPath);
        }
    });
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/FormDeletionCleanupTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/Form.php tests/Feature/FormDeletionCleanupTest.php
git commit -m "fix: clean up uploaded files when deleting forms (#41)"
```

---

### Task 5: Fix #36 — Path traversal hardening in file download

The `SubmissionController::downloadFile()` uses `basename()` on the `$filename` parameter from the URL, but the filename is not validated for path traversal characters. While `basename()` strips directory components, explicit validation adds defense in depth.

**Files:**
- Modify: `app/Http/Controllers/SubmissionController.php:314-340`
- Test: `tests/Feature/FileDownloadSecurityTest.php`

**Step 1: Write the test**

```php
<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileDownloadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_path_traversal_in_filename_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create(['status' => 'published']);
        $submission = $form->submissions()->create([
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('submissions.download', [
            'submission' => $submission->id,
            'filename' => '../../etc/passwd',
        ]));

        $response->assertStatus(403);
    }

    public function test_null_bytes_in_filename_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create(['status' => 'published']);
        $submission = $form->submissions()->create([
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('submissions.download', [
            'submission' => $submission->id,
            'filename' => "file.pdf\0.php",
        ]));

        $response->assertStatus(403);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/FileDownloadSecurityTest.php`
Expected: FAIL — returns 404 (no file) instead of 403 (rejected)

**Step 3: Add path traversal validation**

In `app/Http/Controllers/SubmissionController.php`, update `downloadFile()` method. Add validation at the top of the method, before path construction:

```php
public function downloadFile(Submission $submission, $filename): StreamedResponse
{
    $this->authorize('generalPolicy', $submission);

    // Reject path traversal and null bytes
    if ($filename !== basename($filename) || str_contains($filename, "\0") || str_contains($filename, '..')) {
        abort(403, 'Invalid filename.');
    }

    // ... rest of method unchanged
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/FileDownloadSecurityTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Http/Controllers/SubmissionController.php tests/Feature/FileDownloadSecurityTest.php
git commit -m "fix: reject path traversal attempts in file download (#36)"
```

---

### Task 6: Fix #46 — UserFactory missing role field

The `UserFactory` doesn't set the `role` field, which is required by the `users` table (has a default of `'user'` in the migration, but factories should be explicit).

**Files:**
- Modify: `database/factories/UserFactory.php`

**Step 1: Update the factory**

Add `'role' => 'user'` to the `definition()` array, and add an `admin()` state method:

```php
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => static::$password ??= Hash::make('password'),
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'remember_token' => Str::random(10),
        'profile_photo_path' => null,
        'current_team_id' => null,
        'role' => 'user',
    ];
}

public function admin(): static
{
    return $this->state(fn (array $attributes) => [
        'role' => 'admin',
    ]);
}
```

**Step 2: Run existing tests to verify nothing breaks**

Run: `./vendor/bin/phpunit`
Expected: All existing tests pass

**Step 3: Commit**

```bash
git add database/factories/UserFactory.php
git commit -m "fix: add role field to UserFactory with admin() state (#46)"
```

---

### Task 7: Run full test suite and close issues

**Step 1: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

**Step 2: Close GitHub issues**

```bash
gh issue close 37 --comment "Fixed: user_id is now nullable on submissions table"
gh issue close 38 --comment "Fixed: ip_address column added to submissions table"
gh issue close 45 --comment "Fixed: rate limiters use try-catch with sensible defaults"
gh issue close 41 --comment "Fixed: form deletion now cleans up uploaded files from storage"
gh issue close 36 --comment "Fixed: path traversal validation added to file download endpoint"
gh issue close 46 --comment "Fixed: UserFactory now includes role field with admin() state"
```
