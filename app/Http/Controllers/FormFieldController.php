<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormFieldController extends Controller
{
    use AuthorizesRequests;

    /**
     * Store a newly created form field in storage.
     */
    public function store(Request $request, Form $form): RedirectResponse
    {
        // $this->authorize('update', $form);

        // Base validation rules
        $rules = [
            'type' => 'required|in:text,textarea,select,checkbox,radio,header,description,file',
            'required' => 'boolean',
            'order' => 'integer',
        ];

        // Conditional rules based on field type
        if (in_array($request->input('type'), ['header', 'description'])) {
            $rules['content'] = 'required|string|max:500';
        } else {
            $rules['label'] = 'required|string|max:255';
            if (in_array($request->input('type'), ['select', 'checkbox', 'radio'])) {
                $rules['options'] = 'required|string';
            } else {
                $rules['options'] = 'nullable|string';
            }
            if (in_array($request->input('type'), ['text', 'textarea'])) {
                $rules['char_limit'] = 'nullable|integer|min:1';
            }

            // For non-structural fields, a category must be selected and belong to this form
            $rules['form_category_id'] = [
                'required',
                Rule::exists('form_categories', 'id')->where('form_id', $form->id),
            ];
        }

        $validated = $request->validate($rules, [
            'form_category_id.required' => 'Category is required.',
            'form_category_id.exists' => 'Selected category is invalid.',
        ]);

        // For header and description types, copy the label to content field
        $data = $validated;
        if (in_array($request->type, ['header', 'description'])) {
            // Normalize for structural fields
            $data['label'] = null;
            $data['options'] = null;
            $data['required'] = false;  // Headers and descriptions are never required
        } else {
            // Non-structural fields should not store content
            $data['content'] = null;
            // Ensure options is null when not applicable
            if (! in_array($request->type, ['select', 'checkbox', 'radio'])) {
                $data['options'] = null;
            }
        }

        $form->fields()->create($data);

        return redirect()->route('forms.edit', $form)->with('success', 'Field added successfully.');
    }

    /**
     * Update the specified form field in storage.
     */
    public function update(Request $request, Form $form, FormField $field): RedirectResponse
    {
        // $this->authorize('update', $form);

        // Base validation rules
        $rules = [
            'type' => 'required|in:text,textarea,select,checkbox,radio,header,description,file',
            'required' => 'boolean',
            'order' => 'integer',
        ];

        // Conditional rules based on field type
        if (in_array($request->input('type'), ['header', 'description'])) {
            $rules['content'] = 'required|string|max:500';
        } else {
            $rules['label'] = 'required|string|max:255';
            if (in_array($request->input('type'), ['select', 'checkbox', 'radio'])) {
                $rules['options'] = 'required|string';
            } else {
                $rules['options'] = 'nullable|string';
            }
            if (in_array($request->input('type'), ['text', 'textarea'])) {
                $rules['char_limit'] = 'nullable|integer|min:1';
            }

            // For non-structural fields, a category must be selected and belong to this form
            $rules['form_category_id'] = [
                'required',
                Rule::exists('form_categories', 'id')->where('form_id', $form->id),
            ];
        }

        $validated = $request->validate($rules, [
            'form_category_id.required' => 'Category is required.',
            'form_category_id.exists' => 'Selected category is invalid.',
        ]);

        // Normalize payload based on type before updating
        $data = $validated;
        if (in_array($request->type, ['header', 'description'])) {
            $data['label'] = null;
            $data['options'] = null;
            $data['required'] = false;  // Headers and descriptions are never required
        } else {
            $data['content'] = null;
            if (! in_array($request->type, ['select', 'checkbox', 'radio'])) {
                $data['options'] = null;
            }
        }

        $field->update($data);

        return redirect()->route('forms.edit', $form)->with('success', 'Field updated successfully.');
    }

    /**
     * Remove the specified form field from storage.
     */
    public function destroy(Form $form, FormField $field): RedirectResponse
    {
        //  $this->authorize('update', $form);

        $field->delete();

        return redirect()->route('forms.edit', $form)->with('success', 'Field deleted successfully.');
    }
}
