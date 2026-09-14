<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Validation\Rule;

/** Shared browser/API answer contract. Stored checkbox selections remain comma-separated for compatibility. */
final class SubmissionAnswers
{
    public const FILE_TYPES = ['jpeg', 'jpg', 'webp', 'svg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'md'];

    public const MAX_FILE_KB = 10240;

    public function selections(FormField $field, mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $options = $field->options_array;
        $selected = [];
        foreach ($value as $index => $checked) {
            if (is_bool($checked) || in_array($checked, [0, 1, '0', '1'], true)) {
                if ($checked && isset($options[$index])) {
                    $selected[] = $options[$index];
                }
            } elseif (is_string($checked) && in_array($checked, $options, true)) {
                $selected[] = $checked;
            }
        }

        return array_values(array_unique($selected));
    }

    public function visible(FormField $field, array $values, Form $form, array $visited = []): bool
    {
        if (! $field->depends_on_field_id || $field->depends_on_value === null) {
            return true;
        }
        if (in_array($field->id, $visited, true)) {
            return false;
        }
        $parent = $form->fields->firstWhere('id', $field->depends_on_field_id);
        if (! $parent || ! $this->visible($parent, $values, $form, [...$visited, $field->id])) {
            return false;
        }
        $value = $values[$parent->id] ?? null;

        return $parent->type === 'checkbox'
            ? in_array($field->depends_on_value, $this->selections($parent, $value), true)
            : (string) $value === $field->depends_on_value;
    }

    public function rules(Form $form, array $values, string $prefix = 'values', bool $draft = false): array
    {
        $rules = [$prefix => ['sometimes', 'array']];
        foreach ($form->fields as $field) {
            if (in_array($field->type, ['header', 'description']) || ! $this->visible($field, $values, $form)) {
                continue;
            }
            $required = $field->required && ! $draft;
            $fieldRules = [$required ? 'required' : 'nullable'];
            switch ($field->type) {
                case 'checkbox':
                    $fieldRules[] = 'array';
                    $fieldRules[] = function ($attribute, $value, $fail) use ($field, $required) {
                        if ($required && $this->selections($field, $value) === []) {
                            $fail('Select at least one option for '.$field->label.'.');
                        }
                        foreach ((array) $value as $index => $item) {
                            $boolean = is_bool($item) || in_array($item, [0, 1, '0', '1'], true);
                            if (($boolean && ! array_key_exists($index, $field->options_array)) || (! $boolean && ! in_array($item, $field->options_array, true))) {
                                $fail('Select a valid option for '.$field->label.'.');
                                break;
                            }
                        }
                    };
                    break;
                case 'file':
                    $fieldRules = [...$fieldRules, 'file', 'max:'.self::MAX_FILE_KB, 'mimes:'.implode(',', self::FILE_TYPES)];
                    break;
                case 'select':
                case 'radio':
                    $fieldRules = [...$fieldRules, 'string', Rule::in($field->options_array)];
                    break;
                default:
                    $fieldRules = [...$fieldRules, 'string', 'max:'.($field->char_limit ?: 65535)];
            }
            $rules[$prefix.'.'.$field->id] = $fieldRules;
        }

        return $rules;
    }

    public function storedValue(FormField $field, mixed $value): ?string
    {
        return $field->type === 'checkbox'
            ? (implode(', ', $this->selections($field, $value)) ?: null)
            : $value;
    }
}
