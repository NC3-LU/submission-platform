<?php

namespace App\Services;

use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class FormDuplicator
{
    public function duplicate(Form $source, User $owner): Form
    {
        $asset = null;
        try {
            return DB::transaction(function () use ($source, $owner, &$asset) {
                $source = Form::whereKey($source->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($owner)->authorize('duplicate', $source);
                abort_if($source->fields()->count() > 1000 || $source->categories()->count() > 100, 422, 'This form exceeds the duplication size limit.');
                $categories = $source->categories()->lockForUpdate()->get();
                $fields = $source->fields()->lockForUpdate()->get();
                $path = $source->header_image;
                $disk = Storage::disk('public');
                if (is_string($path) && preg_match('~^form-headers/[a-zA-Z0-9_-]+\.(png|jpg|jpeg|gif|webp)$~i', $path) && $disk->exists($path)) {
                    $asset = 'form-headers/'.Str::uuid().'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    if (! $disk->copy($path, $asset)) {
                        throw new \RuntimeException('Unable to copy form header.');
                    }
                }
                $copy = Form::create([
                    'user_id' => $owner->id, 'title' => Str::limit($source->title, 248, '').' (Copy)',
                    'description' => $source->description, 'status' => 'draft', 'visibility' => $source->visibility,
                    'header_image' => $asset, 'header_image_position' => $source->header_image_position,
                    'header_theme_color' => $source->header_theme_color,
                ]);
                $categoryIds = [];
                foreach ($categories as $category) {
                    $categoryIds[$category->id] = $copy->categories()->create($category->only(['name', 'description', 'order']))->id;
                }
                $fieldCopies = [];
                foreach ($fields as $field) {
                    $fieldCopies[$field->id] = $copy->fields()->create([
                        ...$field->only(['type', 'order', 'label', 'options', 'required', 'content', 'char_limit', 'depends_on_value']),
                        'form_category_id' => $categoryIds[$field->form_category_id] ?? null,
                    ]);
                }
                foreach ($fields as $field) {
                    if ($field->depends_on_field_id) {
                        $fieldCopies[$field->id]->update(['depends_on_field_id' => $fieldCopies[$field->depends_on_field_id]->id ?? null]);
                    }
                }

                return $copy->load(['categories.fields', 'fields']);
            });
        } catch (\Throwable $error) {
            if ($asset) {
                FileCleanup::schedule('public', [$asset]);
                // Try immediately as well, including when a surrounding transaction rolls back.
                Storage::disk('public')->delete($asset);
            }
            throw $error;
        }
    }
}
