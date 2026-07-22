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
- Accessor `header_image_url`: returns `Storage::disk('public')->url($this->header_image)` when set, else `null`.

### Storage

- Files stored on the `public` disk under `form-headers/`.
- Requires the `public/storage` symlink (`php artisan storage:link`) — **not currently present**. Run in dev; add to the manual prod deploy steps (prod deploy is manual per project notes).

### Color extraction service

New `app/Services/ImageColorExtractor.php`:
- `extract(string $absolutePathOrBinary): ?string` — loads the image via GD (`imagecreatefromstring` on file contents), downscales to a small sample (e.g. 32×32), iterates pixels, computes a representative dominant color (average of the most saturated cluster / simple frequency of quantized colors), returns `#rrggbb`.
- Returns `null` and never throws on unreadable/unsupported input (caller treats color as absent).
- GD is confirmed available in this environment.

### Controller — `FormController`

- **Add `enctype="multipart/form-data"`** to the `<form>` in both `forms/create.blade.php` and `forms/edit.blade.php` (neither has it today).
- **`store()` / `update()` validation** additions:
  - `header_image` → `nullable|image|mimes:jpeg,png,webp,gif|max:4096`
  - `header_image_position` → `nullable|integer|min:0|max:100`
  - `header_theme_color` → `nullable|string|regex:/^#[0-9a-fA-F]{6}$/`
  - `remove_header_image` → `nullable|boolean` (edit only)
- **Upload handling** (shared private helper):
  - If a new file is uploaded: store to `form-headers/`, set `header_image`; run `ImageColorExtractor` and set `header_theme_color` **only if the owner did not supply an override** (owner override wins).
  - On update replacing an image: delete the previous file after the new one is stored.
  - If `remove_header_image` is checked: delete the file and null out `header_image` / `header_theme_color` (position reset to default).
  - Always persist `header_image_position` and any explicit `header_theme_color` override.
- **`destroy()`**: delete the header image file before/after removing the form.
- **`duplicate()`**: copy the header image file to a new path for the clone (so deleting the original does not break the copy); carry over position and theme color.

### Views — display

Banner rendered full-width, rounded, `object-cover`, with `object-position: 50% {{ $form->header_image_position }}%`, inside a container whose background is `header_theme_color` (letterbox band); the form/description card gets a top accent using the same color. Rendered above the description in:

- **`resources/views/submissions/create.blade.php`** — public fill-out page (banner above the description card).
- **`resources/views/forms/preview.blade.php`** — owner preview.

Thumbnail (small `object-cover`) added to listing cards in:

- **`resources/views/forms/public-index.blade.php`**
- **`resources/views/submissions/user-index.blade.php`**

When `header_image` is null, nothing renders and layouts are unchanged (accent color also omitted).

### Views — edit/create UI

- File input labeled "Header image" above the Description field in both create and edit.
- **Edit page** (image already exists): show current banner in a fixed-height frame with:
  - A draggable overlay (Alpine): dragging vertically updates a hidden `header_image_position` input and live `object-position`.
  - A color picker (`<input type="color" name="header_theme_color">`) pre-filled with `header_theme_color`, plus a "reset to auto" affordance (clearing lets the next upload re-extract).
  - A "Remove header image" checkbox.
- **Create page**: file input only; position defaults to 50 and theme color is auto-extracted on save. Fine-tuning (reposition/color) is done afterward on the edit page. (Keeps create free of pre-upload preview JS.)

## Testing

Feature test(s) under `tests/Feature/`:
- Upload on create stores the file, sets `header_image`, and auto-populates `header_theme_color`.
- Non-image upload is rejected by validation.
- Owner-supplied `header_theme_color` overrides auto-extraction.
- `remove_header_image` deletes the file and clears the columns.
- Update replacing the image deletes the old file.
- Banner and `object-position` render on the submission page when set; absent when null.

Use `Storage::fake('public')` and `UploadedFile::fake()->image()`.

## Out of scope (YAGNI)

- Horizontal repositioning (vertical focal point only, matching Google Forms' common case).
- A full palette picker derived from the image (single auto color + manual override instead).
- Client-side pre-upload preview/drag on the create page (deferred to edit).

## Deploy notes

- Run `php artisan storage:link` on any environment lacking `public/storage` (dev now; prod manually).
- New migration must run on deploy.
