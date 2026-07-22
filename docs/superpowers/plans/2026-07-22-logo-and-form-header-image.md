# Logo Swap + Per-Form Header Image — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the NC3 logo with the new `SM-Cyber-Luxembourg-color.svg` in the nav and auth pages, and add a Google-Forms-style per-form header image (upload + vertical reposition + auto theme color).

**Architecture:** Three new columns on `forms` (`header_image`, `header_image_position`, `header_theme_color`). Upload handled in `FormController` via extracted `StoreFormRequest`/`UpdateFormRequest`; a small GD-based `ImageColorExtractor` service derives an accent color on upload. The banner renders above the description on the submission and preview pages and as a thumbnail in listings. Logo is a static asset swap in two Blade components.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade + Alpine.js, Tailwind 3.4, GD (image color extraction), MySQL 8, PHPUnit + `Storage::fake`.

## Global Constraints

- No AI/authorship attribution anywhere (commits, comments, docs).
- PHP 8.2+, Laravel 12 conventions.
- Header images stored on the `public` disk under `form-headers/` using hashed filenames (`->store('form-headers', 'public')` — never `storeAs()` with a client name).
- Allowed image types: **jpeg, png, webp** only (no SVG, no GIF).
- `header_theme_color` is always `#rrggbb`, validated by `regex:/^#[0-9a-fA-F]{6}$/D`.
- Banner never goes inside the Livewire component (`livewire/submission-form.blade.php`) — only in the wrapper views.
- Run `php artisan storage:link` on any environment lacking `public/storage` (dev now; prod manually).

---

### Task 1: Logo swap + artifact cleanup

**Files:**
- Modify: `resources/views/components/application-logo.blade.php`
- Modify: `resources/views/components/authentication-card-logo.blade.php`
- Delete: `public/img/SM-Cyber-Luxembourg-color.svg:Zone.Identifier`

**Interfaces:**
- Consumes: existing SVG asset at `public/img/SM-Cyber-Luxembourg-color.svg`.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Delete the stray Windows artifact**

```bash
rm 'public/img/SM-Cyber-Luxembourg-color.svg:Zone.Identifier'
```

- [ ] **Step 2: Replace `application-logo.blade.php` with the new SVG (color in both modes)**

Full new file contents:

```blade
@props(['variant' => 'auto'])

<img {{ $attributes->merge(['class' => '']) }} src="{{ asset('img/SM-Cyber-Luxembourg-color.svg') }}" alt="Cyber Luxembourg">
```

(The `variant` prop is kept for callers that still pass `color`/`white`/`auto`, but the SVG is color-only so all variants resolve to the same asset. Nav callers keep `class="h-8 w-auto"`.)

- [ ] **Step 3: Replace `authentication-card-logo.blade.php` and fix sizing**

Full new file contents:

```blade
<a href="/">
    <img class="h-12 w-auto" src="{{ asset('img/SM-Cyber-Luxembourg-color.svg') }}" alt="Cyber Luxembourg">
</a>
```

> Note: if the current file has no `<a>` wrapper, keep whatever wrapper it currently has — only the `<img>` `src`, `class` (`w-24 h-24` → `h-12 w-auto`), and `alt` change. Read the file first and preserve its existing structure.

- [ ] **Step 4: Verify no other view references the old logo in nav/auth context**

Run: `grep -rn "nc3-logo-no-text-no-bg\|logo_nc3_white\|Logo_NC3_horizontal" resources/views/components/`
Expected: no matches in `application-logo.blade.php` / `authentication-card-logo.blade.php` (matches elsewhere — welcome watermark, co-funded logo — are intentional and left alone).

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/application-logo.blade.php resources/views/components/authentication-card-logo.blade.php
git commit -m "feat(ui): swap nav and auth logo for SM Cyber Luxembourg SVG"
```

---

### Task 2: Migration + Form model columns

**Files:**
- Create: `database/migrations/2026_07_22_000001_add_header_image_to_forms.php`
- Modify: `app/Models/Form.php`
- Modify: `database/factories/FormFactory.php`
- Test: `tests/Feature/FormHeaderImageTest.php` (new; first test method)

**Interfaces:**
- Produces: `Form` has fillable `header_image` (string|null), `header_image_position` (int, default 50), `header_theme_color` (string|null); accessor `$form->header_image_url` (string|null); factory state `->withHeaderImage()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/FormHeaderImageTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormHeaderImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_image_url_accessor_returns_public_url_or_null(): void
    {
        $with = Form::factory()->create(['header_image' => 'form-headers/x.jpg']);
        $without = Form::factory()->create(['header_image' => null]);

        $this->assertSame(Storage::disk('public')->url('form-headers/x.jpg'), $with->header_image_url);
        $this->assertNull($without->header_image_url);
        $this->assertSame(50, $without->header_image_position);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php --filter test_header_image_url_accessor -v`
Expected: FAIL — column `header_image` does not exist / accessor undefined.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_22_000001_add_header_image_to_forms.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('header_image')->nullable()->after('description');
            $table->unsignedTinyInteger('header_image_position')->default(50)->after('header_image');
            $table->string('header_theme_color', 7)->nullable()->after('header_image_position');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['header_image', 'header_image_position', 'header_theme_color']);
        });
    }
};
```

- [ ] **Step 4: Update the Form model**

In `app/Models/Form.php`, add the imports near the top (after the namespace's existing `use` lines):

```php
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
```

Add the three columns to `$fillable` (after `'description',`):

```php
        'header_image',
        'header_image_position',
        'header_theme_color',
```

Add to `$casts`:

```php
        'header_image_position' => 'integer',
```

Add the accessor method (anywhere among the model's methods):

```php
    /**
     * Public URL of the header image, or null when unset.
     * Form is not soft-deleted, so destroy() may hard-delete the file safely.
     */
    protected function headerImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->header_image
                ? Storage::disk('public')->url($this->header_image)
                : null,
        );
    }
```

- [ ] **Step 5: Add the factory state**

In `database/factories/FormFactory.php`, add:

```php
    /**
     * Attach a header image to the form.
     */
    public function withHeaderImage(string $path = 'form-headers/test.jpg'): static
    {
        return $this->state(fn (array $attributes) => [
            'header_image' => $path,
            'header_theme_color' => '#3366cc',
            'header_image_position' => 60,
        ]);
    }
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php --filter test_header_image_url_accessor -v`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_22_000001_add_header_image_to_forms.php app/Models/Form.php database/factories/FormFactory.php tests/Feature/FormHeaderImageTest.php
git commit -m "feat(forms): add header image columns and accessor to Form"
```

---

### Task 3: ImageColorExtractor service

**Files:**
- Create: `app/Services/ImageColorExtractor.php`
- Test: `tests/Unit/ImageColorExtractorTest.php`

**Interfaces:**
- Produces: `ImageColorExtractor::extract(string $absolutePath): ?string` — returns `#rrggbb` for a valid raster image, `null` for garbage / non-image / oversized (> 25 MP), never throws.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ImageColorExtractorTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\ImageColorExtractor;
use PHPUnit\Framework\TestCase;

class ImageColorExtractorTest extends TestCase
{
    private function solidImagePath(int $r, int $g, int $b): string
    {
        $img = imagecreatetruecolor(64, 64);
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        $path = tempnam(sys_get_temp_dir(), 'ice') . '.png';
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    public function test_extracts_dominant_color_from_solid_image(): void
    {
        $path = $this->solidImagePath(210, 40, 40); // strong red
        $hex = (new ImageColorExtractor())->extract($path);
        @unlink($path);

        $this->assertNotNull($hex);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex);
        // Red channel should dominate.
        $this->assertGreaterThan(hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 1, 2)));
    }

    public function test_returns_null_for_non_image_input(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ice');
        file_put_contents($path, 'not an image at all');
        $hex = (new ImageColorExtractor())->extract($path);
        @unlink($path);

        $this->assertNull($hex);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/ImageColorExtractorTest.php -v`
Expected: FAIL — class `App\Services\ImageColorExtractor` not found.

- [ ] **Step 3: Implement the service**

Create `app/Services/ImageColorExtractor.php`:

```php
<?php

namespace App\Services;

class ImageColorExtractor
{
    /** Reject anything above this pixel count (decompression-bomb guard). */
    private const MAX_PIXELS = 25_000_000;

    /** Downscaled sample edge length. */
    private const SAMPLE = 32;

    /**
     * Return the dominant accent color of an image as #rrggbb, or null.
     * Never throws: unreadable, non-image, or oversized input yields null.
     */
    public function extract(string $absolutePath): ?string
    {
        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;
        if ($width <= 0 || $height <= 0 || $width * $height > self::MAX_PIXELS) {
            return null;
        }

        $data = @file_get_contents($absolutePath);
        if ($data === false) {
            return null;
        }

        $src = @imagecreatefromstring($data);
        if ($src === false) {
            return null;
        }

        $sample = imagecreatetruecolor(self::SAMPLE, self::SAMPLE);
        imagecopyresampled($sample, $src, 0, 0, 0, 0, self::SAMPLE, self::SAMPLE, $width, $height);
        imagedestroy($src);

        $buckets = [];
        for ($y = 0; $y < self::SAMPLE; $y++) {
            for ($x = 0; $x < self::SAMPLE; $x++) {
                $rgb = imagecolorat($sample, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $max = max($r, $g, $b);
                $min = min($r, $g, $b);

                if ($max > 240 && $min > 240) {
                    continue; // near-white
                }
                if ($max < 15 && $min < 15) {
                    continue; // near-black
                }
                if ($max - $min < 20) {
                    continue; // low-saturation grey
                }

                $key = intdiv($r, 32) . '-' . intdiv($g, 32) . '-' . intdiv($b, 32);
                if (! isset($buckets[$key])) {
                    $buckets[$key] = ['count' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
                }
                $buckets[$key]['count']++;
                $buckets[$key]['r'] += $r;
                $buckets[$key]['g'] += $g;
                $buckets[$key]['b'] += $b;
            }
        }
        imagedestroy($sample);

        if ($buckets === []) {
            return null;
        }

        // PHP 8 sort is stable, so ties resolve by scan order — deterministic.
        uasort($buckets, fn ($a, $b) => $b['count'] <=> $a['count']);
        $top = reset($buckets);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($top['r'] / $top['count']),
            (int) round($top['g'] / $top['count']),
            (int) round($top['b'] / $top['count']),
        );
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/ImageColorExtractorTest.php -v`
Expected: PASS (both methods).

- [ ] **Step 5: Commit**

```bash
git add app/Services/ImageColorExtractor.php tests/Unit/ImageColorExtractorTest.php
git commit -m "feat(services): add GD-based ImageColorExtractor"
```

---

### Task 4: Form Request classes + wire into controller

**Files:**
- Create: `app/Http/Requests/StoreFormRequest.php`
- Create: `app/Http/Requests/UpdateFormRequest.php`
- Modify: `app/Http/Controllers/FormController.php` (store/update signatures + authorize removal)
- Test: `tests/Feature/FormHeaderImageTest.php` (add validation methods)

**Interfaces:**
- Consumes: existing `FormPolicy` `create`/`update` abilities.
- Produces: `StoreFormRequest` and `UpdateFormRequest` carrying all form rules incl. header rules; `store(StoreFormRequest $request)` and `update(UpdateFormRequest $request, Form $form)` signatures.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/FormHeaderImageTest.php` (add `use Illuminate\Http\UploadedFile;` to the imports):

```php
    public function test_non_image_header_upload_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('forms.store'), [
            'title' => 'T',
            'visibility' => 'public',
            'categories' => [['name' => 'C', 'description' => null]],
            'header_image' => UploadedFile::fake()->create('evil.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('header_image');
    }

    public function test_oversized_dimension_header_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('forms.store'), [
            'title' => 'T',
            'visibility' => 'public',
            'categories' => [['name' => 'C', 'description' => null]],
            'header_image' => UploadedFile::fake()->image('huge.jpg', 7000, 7000),
        ]);

        $response->assertSessionHasErrors('header_image');
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php --filter "rejected" -v`
Expected: FAIL — no validation error yet (header_image currently unvalidated).

- [ ] **Step 3: Create `StoreFormRequest`**

Create `app/Http/Requests/StoreFormRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Form::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'visibility' => 'required|in:public,authenticated,private',
            'categories' => 'required|array|min:1',
            'categories.*.name' => 'required|string|max:255',
            'categories.*.description' => 'nullable|string',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after_or_equal:available_from',
            'header_image' => 'nullable|image|mimes:jpeg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000',
            'header_image_position' => 'nullable|integer|min:0|max:100',
            'header_theme_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/D',
        ];
    }
}
```

- [ ] **Step 4: Create `UpdateFormRequest`**

Create `app/Http/Requests/UpdateFormRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('form')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|max:255',
            'description' => 'nullable',
            'status' => 'required|in:draft,published,archived',
            'visibility' => 'required|in:public,authenticated,private',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after_or_equal:available_from',
            'header_image' => 'nullable|image|mimes:jpeg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000',
            'header_image_position' => 'nullable|integer|min:0|max:100',
            'header_theme_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/D',
            'remove_header_image' => 'nullable|boolean',
        ];
    }
}
```

- [ ] **Step 5: Wire the requests into the controller**

In `app/Http/Controllers/FormController.php`:

Add imports:

```php
use App\Http\Requests\StoreFormRequest;
use App\Http\Requests\UpdateFormRequest;
```

Change the `store()` signature and remove the now-duplicated inline authorize + validate. Replace lines 75-88 (the signature, `$this->authorize('create', ...)`, and the `$validatedData = $request->validate([...])` block) so the method begins:

```php
    public function store(StoreFormRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();
```

(Leave the rest of `store()` — the `Form::create` and category loop — unchanged for now; Task 5 modifies it.)

Change `update()` signature and drop the inline authorize + validate. Replace lines 123-134 so the method begins:

```php
    public function update(UpdateFormRequest $request, Form $form): RedirectResponse
    {
```

(Delete the `$this->authorize('update', $form);` line and the whole `$request->validate([...]);` block — the request class now handles both. Leave the `$form->update(...)` line for Task 5.)

- [ ] **Step 6: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php --filter "rejected" -v`
Expected: PASS.

- [ ] **Step 7: Run the existing form tests to confirm no regression**

Run: `./vendor/bin/phpunit tests/Feature/FormCloneTest.php tests/Feature/FormAvailabilityTest.php tests/Feature/FormDeletionCleanupTest.php -v`
Expected: PASS (authorization + validation behavior unchanged).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/StoreFormRequest.php app/Http/Requests/UpdateFormRequest.php app/Http/Controllers/FormController.php tests/Feature/FormHeaderImageTest.php
git commit -m "refactor(forms): extract form requests and validate header image fields"
```

---

### Task 5: Controller header upload/replace/remove/duplicate/destroy handling

**Files:**
- Modify: `app/Http/Controllers/FormController.php`
- Modify: `resources/views/forms/create.blade.php:30` (add `enctype`)
- Modify: `resources/views/forms/edit.blade.php:98` (add `enctype`)
- Test: `tests/Feature/FormHeaderImageTest.php` (add behavior methods)

**Interfaces:**
- Consumes: `ImageColorExtractor::extract()`, `StoreFormRequest`/`UpdateFormRequest`.
- Produces: `store()` persists header on create; `update()` replaces/removes/reposition/recolors; `destroy()` deletes the file; `duplicate()` copies the file to a new path. Private helper `handleHeaderUpload(Request $request): ?array` returning `['header_image' => string, 'header_theme_color' => string|null]`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/FormHeaderImageTest.php` (`use App\Models\FormCategory;` is not needed — categories go through the store payload):

```php
    public function test_create_stores_header_and_auto_extracts_color(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('forms.store'), [
            'title' => 'Themed',
            'visibility' => 'public',
            'categories' => [['name' => 'C', 'description' => null]],
            'header_image' => UploadedFile::fake()->image('banner.jpg', 800, 400),
            'header_image_position' => 30,
        ])->assertRedirect();

        $form = Form::where('title', 'Themed')->firstOrFail();
        $this->assertNotNull($form->header_image);
        Storage::disk('public')->assertExists($form->header_image);
        $this->assertSame(30, $form->header_image_position);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $form->header_theme_color);
    }

    public function test_explicit_theme_color_overrides_extraction(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('forms.store'), [
            'title' => 'Override',
            'visibility' => 'public',
            'categories' => [['name' => 'C', 'description' => null]],
            'header_image' => UploadedFile::fake()->image('banner.jpg', 800, 400),
            'header_theme_color' => '#abcdef',
        ])->assertRedirect();

        $this->assertSame('#abcdef', Form::where('title', 'Override')->firstOrFail()->header_theme_color);
    }

    public function test_update_replaces_image_and_deletes_old_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create([
            'header_image' => 'form-headers/old.jpg',
        ]);
        Storage::disk('public')->put('form-headers/old.jpg', 'x');

        $this->actingAs($user)->put(route('forms.update', $form), [
            'title' => $form->title,
            'status' => 'draft',
            'visibility' => 'public',
            'header_image' => UploadedFile::fake()->image('new.jpg', 400, 200),
        ])->assertRedirect();

        Storage::disk('public')->assertMissing('form-headers/old.jpg');
        $this->assertNotSame('form-headers/old.jpg', $form->fresh()->header_image);
        Storage::disk('public')->assertExists($form->fresh()->header_image);
    }

    public function test_update_can_remove_header_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create(['header_image' => 'form-headers/z.jpg']);
        Storage::disk('public')->put('form-headers/z.jpg', 'x');

        $this->actingAs($user)->put(route('forms.update', $form), [
            'title' => $form->title,
            'status' => 'draft',
            'visibility' => 'public',
            'remove_header_image' => '1',
        ])->assertRedirect();

        Storage::disk('public')->assertMissing('form-headers/z.jpg');
        $this->assertNull($form->fresh()->header_image);
        $this->assertNull($form->fresh()->header_theme_color);
    }

    public function test_update_persists_position_without_new_upload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->withHeaderImage()->create();

        $this->actingAs($user)->put(route('forms.update', $form), [
            'title' => $form->title,
            'status' => 'draft',
            'visibility' => 'public',
            'header_image_position' => 80,
        ])->assertRedirect();

        $this->assertSame(80, $form->fresh()->header_image_position);
    }

    public function test_destroy_deletes_header_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create(['header_image' => 'form-headers/d.jpg']);
        Storage::disk('public')->put('form-headers/d.jpg', 'x');

        $this->actingAs($user)->delete(route('forms.destroy', $form))->assertRedirect();

        Storage::disk('public')->assertMissing('form-headers/d.jpg');
    }

    public function test_duplicate_copies_header_to_new_path(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create(['header_image' => 'form-headers/orig.jpg']);
        Storage::disk('public')->put('form-headers/orig.jpg', 'x');
        $form->categories()->create(['name' => 'C', 'order' => 1]);

        $this->actingAs($user)->post(route('forms.duplicate', $form))->assertRedirect();

        $clone = Form::where('title', $form->title.' (Copy)')->firstOrFail();
        $this->assertNotSame('form-headers/orig.jpg', $clone->header_image);
        Storage::disk('public')->assertExists($clone->header_image);

        // Deleting the original must not break the clone.
        Storage::disk('public')->delete('form-headers/orig.jpg');
        Storage::disk('public')->assertExists($clone->header_image);
    }
```

> Verify route names before running: `php artisan route:list --name=forms` — confirm `forms.store`, `forms.update`, `forms.destroy`, `forms.duplicate` exist. Adjust the `route()` calls if a name differs.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php --filter "header\|duplicate\|destroy\|position\|override" -v`
Expected: FAIL — header not persisted / files not deleted / clone shares path.

- [ ] **Step 3: Add controller imports and helper**

In `app/Http/Controllers/FormController.php` add imports:

```php
use App\Services\ImageColorExtractor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
```

Add the private helper to the class:

```php
    /**
     * Store an uploaded header image (if any) and resolve its accent color.
     * Owner-supplied theme color wins; otherwise it is auto-extracted.
     *
     * @return array{header_image: string, header_theme_color: string|null}|null
     */
    private function handleHeaderUpload(Request $request): ?array
    {
        if (! $request->hasFile('header_image')) {
            return null;
        }

        $path = $request->file('header_image')->store('form-headers', 'public');

        $color = $request->filled('header_theme_color')
            ? $request->input('header_theme_color')
            : app(ImageColorExtractor::class)->extract(Storage::disk('public')->path($path));

        return ['header_image' => $path, 'header_theme_color' => $color];
    }
```

- [ ] **Step 4: Update `store()` to persist the header**

Replace the `Form::create([...])` array in `store()` with:

```php
        $attributes = [
            'title' => $validatedData['title'],
            'description' => $validatedData['description'] ?? null,
            'visibility' => $validatedData['visibility'],
            'status' => 'draft',
            'user_id' => auth()->id(),
            'available_from' => $validatedData['available_from'] ?? null,
            'available_until' => $validatedData['available_until'] ?? null,
            'header_image_position' => $validatedData['header_image_position'] ?? 50,
        ];

        if ($header = $this->handleHeaderUpload($request)) {
            $attributes['header_image'] = $header['header_image'];
            $attributes['header_theme_color'] = $header['header_theme_color'];
        }

        $form = Form::create($attributes);
```

- [ ] **Step 5: Update `update()` to replace/remove/reposition/recolor**

Replace the single `$form->update($request->only(...))` line with:

```php
        $data = $request->only('title', 'description', 'status', 'visibility', 'available_from', 'available_until');
        $data['header_image_position'] = (int) $request->input('header_image_position', 50);

        if ($request->boolean('remove_header_image') && $form->header_image) {
            Storage::disk('public')->delete($form->header_image);
            $data['header_image'] = null;
            $data['header_theme_color'] = null;
            $data['header_image_position'] = 50;
        } elseif ($header = $this->handleHeaderUpload($request)) {
            $old = $form->header_image;
            $data['header_image'] = $header['header_image'];
            $data['header_theme_color'] = $header['header_theme_color'];
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        } elseif ($request->filled('header_theme_color')) {
            $data['header_theme_color'] = $request->input('header_theme_color');
        }

        $form->update($data);
```

- [ ] **Step 6: Update `destroy()` to delete the file**

Insert before `$form->delete();`:

```php
        if ($form->header_image) {
            Storage::disk('public')->delete($form->header_image);
        }
```

- [ ] **Step 7: Update `duplicate()` to copy the file to a new path**

Inside the `DB::transaction` closure, after `$clone->user_id = auth()->id();` and **before** `$clone->save();`, insert:

```php
            if ($form->header_image && Storage::disk('public')->exists($form->header_image)) {
                $ext = pathinfo($form->header_image, PATHINFO_EXTENSION);
                $newPath = 'form-headers/'.Str::uuid().($ext ? '.'.$ext : '');
                Storage::disk('public')->copy($form->header_image, $newPath);
                $clone->header_image = $newPath;
            }
```

(`replicate()` already carried the original path onto `$clone`; this overwrites it with the new copy.)

- [ ] **Step 8: Add `enctype` to both forms**

In `resources/views/forms/create.blade.php:30` change the opening `<form>` tag to include `enctype="multipart/form-data"`:

```blade
                <form action="{{ route('forms.store') }}" method="POST" enctype="multipart/form-data" x-data="{ categories: [{ name: '', description: '', percentage_start: 0, percentage_end: 100 }] }">
```

In `resources/views/forms/edit.blade.php:98` change to:

```blade
                <form action="{{ route('forms.update', $form) }}" method="POST" enctype="multipart/form-data">
```

- [ ] **Step 9: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php -v`
Expected: PASS (all methods).

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/FormController.php resources/views/forms/create.blade.php resources/views/forms/edit.blade.php tests/Feature/FormHeaderImageTest.php
git commit -m "feat(forms): handle header image upload, replace, remove, duplicate and delete"
```

---

### Task 6: Display the banner and listing thumbnails

**Files:**
- Modify: `resources/views/submissions/create.blade.php` (banner block above description)
- Modify: `resources/views/forms/preview.blade.php` (banner at top of card)
- Modify: `resources/views/forms/public-index.blade.php` (card thumbnail)
- Modify: `resources/views/submissions/user-index.blade.php` (table-cell thumbnail)
- Test: `tests/Feature/FormHeaderImageTest.php` (render assertion)

**Interfaces:**
- Consumes: `$form->header_image`, `$form->header_image_url`, `$form->header_image_position`, `$form->header_theme_color`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/FormHeaderImageTest.php`:

```php
    public function test_banner_renders_on_submission_page_when_set(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->published()->public()->withHeaderImage()->create();
        $form->categories()->create(['name' => 'C', 'order' => 1]);

        $response = $this->actingAs($user)->get(route('submissions.create', $form));

        $response->assertOk();
        $response->assertSee('object-position: 50% 60%', false);
        $response->assertSee($form->header_image_url, false);
    }

    public function test_banner_absent_when_no_header_image(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->published()->public()->create(['header_image' => null]);
        $form->categories()->create(['name' => 'C', 'order' => 1]);

        $response = $this->actingAs($user)->get(route('submissions.create', $form));

        $response->assertOk();
        $response->assertDontSee('object-position: 50%', false);
    }
```

> Confirm `submissions.create` is the correct route name via `php artisan route:list --name=submissions`; adjust if needed.

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php --filter banner -v`
Expected: FAIL — banner markup not present.

- [ ] **Step 3: Add the banner to `submissions/create.blade.php`**

Immediately after the opening `<div class="max-w-3xl mx-auto sm:px-6 lg:px-8">` (line 19) and **before** the `@if($form->description)` block, insert:

```blade
            @if($form->header_image)
                <div class="mb-6 overflow-hidden rounded-xl shadow"
                     @if($form->header_theme_color) style="border-top: 4px solid {{ $form->header_theme_color }}" @endif>
                    <img src="{{ $form->header_image_url }}" alt="{{ $form->title }}"
                         class="w-full h-48 sm:h-64 object-cover"
                         style="object-position: 50% {{ $form->header_image_position }}%">
                </div>
            @endif
```

- [ ] **Step 4: Add the banner to `forms/preview.blade.php`**

Immediately after the opening `<div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">` (line 20), as the first child (before the `@if($form->description)` block), insert:

```blade
                @if($form->header_image)
                    <div class="mb-6 -mx-6 -mt-6 overflow-hidden rounded-t-xl"
                         @if($form->header_theme_color) style="border-top: 4px solid {{ $form->header_theme_color }}" @endif>
                        <img src="{{ $form->header_image_url }}" alt="{{ $form->title }}"
                             class="w-full h-48 sm:h-64 object-cover"
                             style="object-position: 50% {{ $form->header_image_position }}%">
                    </div>
                @endif
```

- [ ] **Step 5: Add the thumbnail to `forms/public-index.blade.php`**

Immediately after the opening card `<div class="bg-white ... p-6 hover:shadow-md transition-shadow">` (line 21), before the `<h3>` title (line 22), insert:

```blade
                            @if($form->header_image)
                                <img src="{{ $form->header_image_url }}" alt=""
                                     class="w-full h-24 object-cover rounded-md mb-3 -mt-2"
                                     style="object-position: 50% {{ $form->header_image_position }}%">
                            @endif
```

- [ ] **Step 6: Add the thumbnail to `submissions/user-index.blade.php`**

Replace the Form Title cell body (line 38-40) so the title cell becomes:

```blade
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        <div class="flex items-center gap-3">
                                            @if($submission->form->header_image)
                                                <img src="{{ $submission->form->header_image_url }}" alt=""
                                                     class="w-10 h-10 object-cover rounded shrink-0">
                                            @endif
                                            <span>{{ $submission->form->title }}</span>
                                        </div>
                                    </td>
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php --filter banner -v`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/views/submissions/create.blade.php resources/views/forms/preview.blade.php resources/views/forms/public-index.blade.php resources/views/submissions/user-index.blade.php tests/Feature/FormHeaderImageTest.php
git commit -m "feat(forms): render header banner and listing thumbnails"
```

---

### Task 7: Edit/create upload UI (file input, reposition, color, remove)

**Files:**
- Modify: `resources/views/forms/create.blade.php` (file input)
- Modify: `resources/views/forms/edit.blade.php` (file input + reposition + color + remove, inside Settings-tab form)
- Test: `tests/Feature/FormHeaderImageTest.php` (HTML presence assertions)

**Interfaces:**
- Consumes: same `$form` header attributes; posts `header_image`, `header_image_position`, `header_theme_color`, `remove_header_image` handled in Task 5.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/FormHeaderImageTest.php`:

```php
    public function test_edit_page_exposes_header_controls(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->withHeaderImage()->create();

        $response = $this->actingAs($user)->get(route('forms.edit', $form));

        $response->assertOk();
        $response->assertSee('name="header_image"', false);
        $response->assertSee('name="header_image_position"', false);
        $response->assertSee('name="header_theme_color"', false);
        $response->assertSee('name="remove_header_image"', false);
        $response->assertSee('enctype="multipart/form-data"', false);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php --filter header_controls -v`
Expected: FAIL — controls not present.

- [ ] **Step 3: Add the file input to `create.blade.php`**

After the description `<div class="lg:col-span-2">...</div>` block (ends line 51), insert:

```blade
                        <div class="lg:col-span-2">
                            <label for="header_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Header image</label>
                            <input type="file" name="header_image" id="header_image" accept="image/jpeg,image/png,image/webp"
                                   class="block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 dark:file:bg-sky-900/30 dark:file:text-sky-300">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Optional banner shown above the form. JPG, PNG or WebP, max 4&nbsp;MB. Reposition and accent color can be adjusted after saving.</p>
                            @error('header_image')
                            <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>
```

- [ ] **Step 4: Add the full header control block to `edit.blade.php`**

After the description `<!-- Description Field -->` block (ends line 126) and before the `<!-- Status Field -->` block, insert:

```blade
                        <!-- Header Image Field -->
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Header image</label>

                            @if($form->header_image)
                                <div x-data="{ pos: {{ $form->header_image_position }}, dragging: false }" class="mb-3">
                                    <div class="relative w-full h-48 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 cursor-ns-resize select-none"
                                         @mousedown="dragging = true"
                                         @mousemove="if (dragging) { const r = $el.getBoundingClientRect(); pos = Math.min(100, Math.max(0, Math.round((($event.clientY - r.top) / r.height) * 100))) }"
                                         @mouseup.window="dragging = false"
                                         @mouseleave="dragging = false">
                                        <img src="{{ $form->header_image_url }}" alt="{{ $form->title }}"
                                             class="w-full h-full object-cover pointer-events-none"
                                             :style="`object-position: 50% ${pos}%`">
                                        <div class="absolute inset-x-0 bottom-0 bg-black/40 text-white text-xs text-center py-1 pointer-events-none">Drag to reposition</div>
                                    </div>
                                    <input type="hidden" name="header_image_position" :value="pos">
                                </div>

                                <div class="flex flex-wrap items-center gap-4 mb-3">
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        Accent color
                                        <input type="color" name="header_theme_color" value="{{ $form->header_theme_color ?? '#3366cc' }}"
                                               class="h-8 w-12 rounded border border-gray-300 dark:border-gray-600 bg-transparent p-0">
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" name="remove_header_image" value="1"
                                               class="rounded border-gray-300 dark:border-gray-600 text-sky-600 focus:ring-sky-500">
                                        Remove header image
                                    </label>
                                </div>
                            @else
                                <input type="hidden" name="header_image_position" value="50">
                            @endif

                            <input type="file" name="header_image" accept="image/jpeg,image/png,image/webp"
                                   class="block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 dark:file:bg-sky-900/30 dark:file:text-sky-300">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">JPG, PNG or WebP, max 4&nbsp;MB. Uploading replaces the current image.</p>
                            @error('header_image')
                            <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>
```

> The `withHeaderImage()` factory state sets `header_image`, so the `@if($form->header_image)` branch (with the color picker and remove checkbox) renders — which is what the test asserts. All controls sit inside the existing Settings-tab `<form>`.

- [ ] **Step 5: Run the test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php --filter header_controls -v`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/views/forms/create.blade.php resources/views/forms/edit.blade.php tests/Feature/FormHeaderImageTest.php
git commit -m "feat(forms): add header image upload, reposition and accent controls"
```

---

### Task 8: Expose header fields in the API

**Files:**
- Modify: `app/Http/Resources/FormResource.php`
- Test: `tests/Feature/FormHeaderImageTest.php` (API resource assertion) OR extend an existing API test

**Interfaces:**
- Produces: `header_image_url` and `header_theme_color` keys in the Form API payload.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/FormHeaderImageTest.php`:

```php
    public function test_form_resource_includes_header_fields(): void
    {
        $form = Form::factory()->withHeaderImage()->create();

        $resource = (new \App\Http\Resources\FormResource($form))->toArray(request());

        $this->assertArrayHasKey('header_image_url', $resource);
        $this->assertArrayHasKey('header_theme_color', $resource);
        $this->assertSame('#3366cc', $resource['header_theme_color']);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php --filter resource_includes_header -v`
Expected: FAIL — keys absent.

- [ ] **Step 3: Add the fields to `FormResource`**

In `app/Http/Resources/FormResource.php`, add to the returned array (after `'description' => $this->description,`):

```php
            'header_image_url' => $this->header_image_url,
            'header_theme_color' => $this->header_theme_color,
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php --filter resource_includes_header -v`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Resources/FormResource.php tests/Feature/FormHeaderImageTest.php
git commit -m "feat(api): expose header image url and theme color on form resource"
```

---

### Final verification

- [ ] **Run the full header-image test file + related regressions**

Run: `./vendor/bin/phpunit tests/Feature/FormHeaderImageTest.php tests/Unit/ImageColorExtractorTest.php tests/Feature/FormCloneTest.php tests/Feature/FormDeletionCleanupTest.php`
Expected: all PASS.

- [ ] **Run the whole suite**

Run: `composer test`
Expected: green (or only pre-existing unrelated failures).

- [ ] **Lint**

Run: `composer lint` (fix with `composer lint:fix` if needed)
Expected: clean.

- [ ] **Manual/deploy note:** ensure `php artisan storage:link` has been run so uploaded banners resolve under `/storage`. Add this to the manual prod deploy steps.

## Notes for the implementer

- **Route names:** the tests assume `forms.store`, `forms.update`, `forms.destroy`, `forms.duplicate`, `forms.edit`, `submissions.create`. Verify with `php artisan route:list` and adjust if a name differs (e.g. `forms.user_index` vs `forms.userIndex`).
- **`Storage::fake('public')`** must be called in any test that uploads/deletes so no real files are touched.
- **Do not** add the banner to `resources/views/livewire/submission-form.blade.php` — it renders per-category descriptions only; the banner lives in the wrapper views.
- **PDF export** (`submissions/pdf.blade.php`) deliberately does not show the banner.
