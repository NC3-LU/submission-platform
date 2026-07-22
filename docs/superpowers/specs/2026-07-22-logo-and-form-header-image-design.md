# Logo swap + per-form header image — Design

Date: 2026-07-22
Status: Approved pending spec review

## Goals

1. Replace the current NC3 logo with the new `SM-Cyber-Luxembourg-color.svg` in the top nav bar and on the auth/login pages.
2. Add a Google-Forms-style header image ("banner") to each form, shown above the form description, with:
   - Image upload (owner-provided file).
   - Vertical repositioning (focal point), like Google Forms' drag-to-reposition.
   - Auto-extracted theme color from the image, applied as an accent, with an owner override.

## Part 1 — Logo swap

The new asset `public/img/SM-Cyber-Luxembourg-color.svg` is a **horizontal color wordmark** (viewBox `0 0 218.75 50.61`).

### Changes

- **`resources/views/components/application-logo.blade.php`** (top nav):
  - Point the `color` and `auto` (default) variants at `img/SM-Cyber-Luxembourg-color.svg`.
  - The SVG is color-only, so render it in **both** light and dark mode (remove the separate white-PNG dark variant). The app nav uses a light background in both themes, so contrast is fine.
  - Keep nav sizing (`h-8 w-auto`, supplied by callers).
- **`resources/views/components/authentication-card-logo.blade.php`** (login/register/reset/2FA/verify/terms/policy):
  - Swap the square `nc3-logo-no-text-no-bg.png` for `img/SM-Cyber-Luxembourg-color.svg`.
  - Change sizing `w-24 h-24` → `h-12 w-auto` so the horizontal wordmark is not distorted.
- **Cleanup:** delete the stray Windows artifact `public/img/SM-Cyber-Luxembourg-color.svg:Zone.Identifier`.
- The old PNGs (`logo_nc3_white.png`, `Logo_NC3_horizontal_coul_versB.png`, `nc3-logo-no-text-no-bg.png`) remain on disk — still referenced by the welcome watermark and co-funded-EU logo; left untouched.

## Part 2 — Per-form header image

### Data model

Migration adds three nullable/defaulted columns to `forms`:

| Column | Type | Default | Meaning |
|---|---|---|---|
| `header_image` | `string` nullable | `null` | Relative path on the `public` disk (`form-headers/<file>`). |
| `header_image_position` | `unsignedTinyInteger` | `50` | Vertical focal point, 0–100 (% for CSS `object-position`). |
| `header_theme_color` | `string(7)` nullable | `null` | Hex accent color, e.g. `#1a2b3c`. |

`Form` model:
- Add the three columns to `$fillable`.
- Cast `header_image_position` to `integer`.
- Accessor `header_image_url` using the modern `Attribute` form (`protected function headerImageUrl(): Attribute`): returns `Storage::disk('public')->url($this->header_image)` when set, else `null`.
- **Hard-delete assumption:** `Form` does not use `SoftDeletes`, so `destroy()` physically deleting the header file is safe. If `SoftDeletes` is ever added, file deletion must move to a `forceDeleting`/`forceDelete` hook — noted here so the coupling is explicit.

### Storage

- Files stored on the `public` disk under `form-headers/`, always via `->store('form-headers', 'public')` so Laravel generates a **hashed filename** (never `storeAs()` with the client-supplied name — avoids path traversal / collisions).
- Requires the `public/storage` symlink (`php artisan storage:link`) — **not currently present**. Run in dev; add to the manual prod deploy steps (prod deploy is manual per project notes).

### Color extraction service

New `app/Services/ImageColorExtractor.php`, `extract(string $absolutePath): ?string`:
- **Guard first:** call `getimagesize()`; if it fails, or `width * height` exceeds **25 MP**, return `null` without decoding (defense-in-depth against decompression bombs — see validation below, which already rejects these before extraction runs).
- Decode via GD, downscale to **32×32**, then run **one deterministic algorithm**: quantize each pixel by rounding each RGB channel to the nearest 32; skip near-white (all channels > 240), near-black (all < 15), and low-saturation greys (max−min channel < 20); tally the quantized buckets; pick the most frequent bucket and return the **average** of its member pixels as `#rrggbb`. If every pixel was skipped, return `null`.
- Returns `null` and never throws on unreadable/unsupported input (caller treats color as absent). GD confirmed available (WebP/PNG/JPEG supported).

### Validation — Form Request classes

The existing inline `$request->validate()` arrays in `store()`/`update()` are near-duplicates; adding the header rules to both tips past the inline threshold. Extract **`app/Http/Requests/StoreFormRequest.php`** and **`UpdateFormRequest.php`**, moving the existing rules plus:

- `header_image` → `nullable|image|mimes:jpeg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000`
  - `image` excludes SVG; **GIF dropped** (unneeded for a banner, trims decode surface); `dimensions` runs a header-only check that rejects oversized images **before** any full decode.
- `header_image_position` → `nullable|integer|min:0|max:100`
- `header_theme_color` → `nullable|string|regex:/^#[0-9a-fA-F]{6}$/D` (the `/D` anchor rejects a trailing newline)
- `remove_header_image` → `nullable|boolean` (UpdateFormRequest only)

`authorize()` in each request delegates to the existing policy (`create` / `update`), preserving the current `$this->authorize(...)` gate.

### Controller — `FormController`

- **Add `enctype="multipart/form-data"`** to the `<form>` in both `forms/create.blade.php` and `forms/edit.blade.php` (neither has it today).
- **`store()`** builds an explicit `Form::create([...])` array (it does **not** use `$request->only()`). Handle the upload **before** create: store the file, run `ImageColorExtractor`, and include `header_image` / `header_image_position` / `header_theme_color` directly in the create array. Theme color = owner override if supplied, else the extracted value.
- **`update()`** uses a shared private helper `applyHeaderImage(Form $form, FormRequest $request)`:
  - New file uploaded: store it, extract color (unless override supplied), assign columns; then delete the **previous** file after the new one is committed.
  - `remove_header_image` checked: delete the file, null `header_image` and `header_theme_color`, reset position to `50`.
  - Otherwise: persist `header_image_position` and any explicit `header_theme_color` override without touching the file.
- **`destroy()`**: delete the header image file (hard delete — see model note).
- **`duplicate()`**: `replicate()` auto-carries the `header_image` path, so the clone initially points at the original's file. After `replicate()`, **copy the file to a new hashed path and overwrite `$clone->header_image`** with it (position and theme color carry over as-is). Perform the file copy inside the existing `DB::transaction()` guard so a rollback doesn't leave an orphan.

### Views — display

Banner rendered full-width, rounded, `object-cover` in a fixed-height frame, with `style="object-position: 50% {{ $form->header_image_position }}%"` and `alt="{{ $form->title }}"`. The theme color is applied as a **4px top accent border** on the banner block (`border-top: 4px solid {{ $form->header_theme_color }}` when set) — **not** a background band (the banner is `object-cover`, so there is no letterbox to fill; a solid border reads correctly in both light and dark mode, avoiding auto-color contrast problems). The banner is its **own block**, rendered `@if($form->header_image)` independently of the description (whose card is `@if($form->description)` and may be absent).

- **`resources/views/submissions/create.blade.php`** — public fill-out page: banner block above the (conditional) description card and the Livewire form card.
- **`resources/views/forms/preview.blade.php`** — owner preview: this view is a **single** card; the banner renders at the top of that card, above the title/description.

Listing thumbnails:
- **`resources/views/forms/public-index.blade.php`** — card layout: small `object-cover` thumbnail at the top of each card.
- **`resources/views/submissions/user-index.blade.php`** — this is a **paginated `<table>`**, not cards. Add a small thumbnail `<img>` (keyed off `$submission->form->header_image_url`) in the Form Title cell, or `null`-guarded so rows without a header are unaffected.

The banner is **not** placed in the Livewire component (`livewire/submission-form.blade.php`), which only renders per-category step descriptions — so single-step and multi-step forms stay consistent.

**PDF export (`submissions/pdf.blade.php`) — out of scope:** the banner is intentionally omitted. The PDF is a data record (it uses its own fixed `#2563eb` accent), and DomPDF rendering of public-disk image paths is fragile.

### API

Add `header_image_url` and `header_theme_color` to the Form API resource in `app/Http/Resources/` so REST consumers see the new fields (raw `header_image` path stays internal).

### Views — edit/create UI

All header controls live **inside the existing Settings-tab `<form>`** (`x-show="activeTab === 'settings'"`) on the edit page — the same POST form that holds title/description.

- File input labeled "Header image" above the Description field in both create and edit.
- **Edit page** (image already exists): show the current banner in a fixed-height frame with:
  - A draggable overlay (Alpine): dragging vertically updates a hidden `header_image_position` input and the live `object-position`.
  - A color picker (`<input type="color" name="header_theme_color">`) pre-filled with `header_theme_color`, plus a "reset to auto" affordance (clearing lets the next upload re-extract).
  - A "Remove header image" checkbox.
- **Create page**: file input only; position defaults to 50 and theme color is auto-extracted on save. Fine-tuning (reposition/color) is done afterward on the edit page (keeps create free of pre-upload preview JS).

## Testing

Feature tests under `tests/Feature/` (using `Storage::fake('public')` and `UploadedFile::fake()->image()`):
- Upload on create stores the file, sets `header_image`, and auto-populates `header_theme_color`.
- Non-image upload is rejected by validation; an over-dimension image is rejected by the `dimensions` rule.
- Owner-supplied `header_theme_color` overrides auto-extraction.
- `header_image_position` persists on create and update.
- `remove_header_image` deletes the file and clears the columns.
- Update replacing the image deletes the old file.
- `destroy()` deletes the header file.
- `duplicate()` copies the file to a **new** path — the clone's `header_image` differs from the original, and deleting the original leaves the clone's file intact.
- Banner + `object-position` render on the submission page when set; absent when null.

Unit test under `tests/Unit/` for `ImageColorExtractor`:
- Returns `#rrggbb` for a valid solid-color image; returns `null` for garbage bytes / non-image input; never throws.

## Out of scope (YAGNI)

- Horizontal repositioning (vertical focal point only, matching Google Forms' common case).
- A full palette picker derived from the image (single auto color + manual override instead).
- Client-side pre-upload preview/drag on the create page (deferred to edit).
- Banner in PDF export (see rationale above).

## Deploy notes

- Run `php artisan storage:link` on any environment lacking `public/storage` (dev now; prod manually).
- New migration must run on deploy (three columns on `forms`; MySQL 8 INSTANT algorithm — effectively zero downtime).
