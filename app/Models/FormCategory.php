<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormCategory extends Model
{
    protected static function booted(): void
    {
        static::saving(fn (FormCategory $record) => $record->form->ensureStructureEditable());
        static::deleting(fn (FormCategory $record) => $record->form->ensureStructureEditable());
    }

    protected $fillable = ['form_id', 'name', 'description', 'order'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('order');
    }
}
