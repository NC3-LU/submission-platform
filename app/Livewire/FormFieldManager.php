<?php

namespace App\Livewire;

use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormField;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Livewire\Component;
use Illuminate\Validation\Rule;

class FormFieldManager extends Component
{
    public $form;
    public $categories = [];
    public $newCategory = [
        'name' => '',
        'description' => '',
        'percentage_start' => 0,
        'percentage_end' => 100,
    ];
    public $newField = [
        'category_id' => '',
        'label' => '',
        'type' => '',
        'options' => '',
        'required' => false,
        'content' => '',
        'char_limit' => null
    ];

    public $confirmingCategoryDeletion = false;
    public $confirmingFieldDeletion = false;
    public $categoryToDelete;
    public $fieldToDelete;
    public $editingCategory = false;
    public $editingField = false;
    public $categoryBeingEdited;
    public $fieldBeingEdited;
    
    // Enhanced UX properties
    public $showAddCategoryPanel = false;
    public $showAddFieldPanel = false;
    public $addingFieldToCategoryId = null;
    public $collapsedCategories = [];
    public $showPreview = false;
    public $searchQuery = '';
    public $activeFieldTypeFilter = null;
    
    // Field type definitions with icons and descriptions
    public static $fieldTypes = [
        'header' => [
            'label' => 'Header',
            'icon' => 'heading',
            'description' => 'Section heading to organize your form',
            'category' => 'layout'
        ],
        'description' => [
            'label' => 'Description',
            'icon' => 'align-left',
            'description' => 'Explanatory text or instructions',
            'category' => 'layout'
        ],
        'text' => [
            'label' => 'Short Text',
            'icon' => 'type',
            'description' => 'Single line text input',
            'category' => 'input'
        ],
        'textarea' => [
            'label' => 'Long Text',
            'icon' => 'align-justify',
            'description' => 'Multi-line text area',
            'category' => 'input'
        ],
        'select' => [
            'label' => 'Dropdown',
            'icon' => 'chevron-down-square',
            'description' => 'Select one option from a list',
            'category' => 'choice'
        ],
        'checkbox' => [
            'label' => 'Checkboxes',
            'icon' => 'check-square',
            'description' => 'Select multiple options',
            'category' => 'choice'
        ],
        'radio' => [
            'label' => 'Radio Buttons',
            'icon' => 'circle-dot',
            'description' => 'Select one option with radio buttons',
            'category' => 'choice'
        ],
        'file' => [
            'label' => 'File Upload',
            'icon' => 'upload',
            'description' => 'Allow file attachments',
            'category' => 'input'
        ],
    ];

    protected $listeners = [
        'updateCategoryOrder',
        'updateFieldOrder',
        'moveFieldToCategory'
    ];

    protected $rules = [
        'newCategory.name' => 'required|string|max:255',
        'newCategory.description' => 'nullable|string',
        'newCategory.percentage_start' => 'required|numeric|min:0|max:100',
        'newCategory.percentage_end' => 'required|numeric|min:0|max:100|gt:newCategory.percentage_start',
        // Keep a generic rule as a fallback; scoped rule is applied at runtime in validation methods
        'newField.category_id' => 'required|exists:form_categories,id',
        'newField.label' => 'required|string|max:255',
        'newField.type' => 'required|in:text,textarea,select,checkbox,radio,file,header,description',
        'newField.options' => 'nullable|string|required_if:newField.type,select,checkbox,radio',
        'newField.required' => 'boolean',
        'newField.content' => 'required_if:newField.type,header,description',
        'categoryBeingEdited.name' => 'required|string|max:255',
        'categoryBeingEdited.description' => 'nullable|string',
        'categoryBeingEdited.percentage_start' => 'required|numeric|min:0|max:100',
        'categoryBeingEdited.percentage_end' => 'required|numeric|min:0|max:100|gt:categoryBeingEdited.percentage_start',
        'fieldBeingEdited.label' => 'required|string|max:255',
        'fieldBeingEdited.type' => 'required|in:text,textarea,select,checkbox,radio,file,header,description',
        'fieldBeingEdited.options' => 'nullable|string|required_if:fieldBeingEdited.type,select,checkbox,radio',
        'fieldBeingEdited.required' => 'boolean',
        'fieldBeingEdited.content' => 'required_if:fieldBeingEdited.type,header,description',

    ];

    public function mount(Form $form): void
    {
        $this->form = $form;
        $this->loadCategories();
    }

    public function loadCategories(): void
    {
        $this->categories = $this->form->categories()->with(['fields' => function ($query) {
            $query->orderBy('order');
        }])->orderBy('order')->get()->toArray();
    }

    public function updated($propertyName): void
    {
        $this->validateOnly($propertyName);
    }

    public function addCategory(): void
    {
        $this->validate([
            'newCategory.name' => 'required|string|max:255',
            'newCategory.description' => 'nullable|string',
            'newCategory.percentage_start' => 'required|numeric|min:0|max:100',
            'newCategory.percentage_end' => 'required|numeric|min:0|max:100|gt:newCategory.percentage_start',
        ]);

        $this->form->categories()->create([
            'name' => $this->newCategory['name'],
            'description' => $this->newCategory['description'],
            'percentage_start' => $this->newCategory['percentage_start'],
            'percentage_end' => $this->newCategory['percentage_end'],
            'order' => $this->form->categories()->max('order') + 1,
        ]);

        $this->reset('newCategory');
        $this->loadCategories();
    }


    public function confirmDeleteCategory($categoryId): void
    {
        $this->confirmingCategoryDeletion = true;
        $this->categoryToDelete = $categoryId;
    }

    public function deleteCategory(): void
    {
        $category = FormCategory::findOrFail($this->categoryToDelete);

        // Delete all fields associated with this category
        $category->fields()->delete();

        // Delete the category
        $category->delete();

        $this->confirmingCategoryDeletion = false;
        $this->categoryToDelete = null;
        $this->loadCategories();
    }

    public function confirmDeleteField($fieldId): void
    {
        $this->confirmingFieldDeletion = true;
        $this->fieldToDelete = $fieldId;
    }

    public function deleteField(): void
    {
        $field = FormField::findOrFail($this->fieldToDelete);
        $field->delete();
        $this->confirmingFieldDeletion = false;
        $this->fieldToDelete = null;
        $this->loadCategories();
    }

    public function addField(): void
    {
        $messages = [
            'newField.category_id.required' => 'Category is required.',
            'newField.category_id.exists' => 'Selected category is invalid.',
        ];

        $this->validate($this->fieldValidationRules(), $messages);

        $category = FormCategory::findOrFail($this->newField['category_id']);

        $fieldData = [
            'form_id' => $this->form->id,
            'form_category_id' => $category->id,
            'type' => $this->newField['type'],
            'order' => $category->fields()->max('order') + 1,
        ];

        if (in_array($this->newField['type'], ['header', 'description'])) {
            $fieldData['content'] = $this->newField['content'];
            $fieldData['label'] = null;
            $fieldData['options'] = null;
            $fieldData['required'] = false;
            $fieldData['char_limit'] = null;
        } else {
            $fieldData['label'] = $this->newField['label'];
            $fieldData['options'] = $this->newField['options'];
            $fieldData['required'] = $this->newField['required'];
            $fieldData['content'] = null;
            // Persist char_limit only for text/textarea
            if (in_array($this->newField['type'], ['text', 'textarea'])) {
                $fieldData['char_limit'] = $this->newField['char_limit'] ?? null;
            } else {
                $fieldData['char_limit'] = null;
            }
        }

        FormField::create($fieldData);

        $this->reset('newField');
        $this->loadCategories();
    }

    public function updatedNewFieldType(): void
    {
        $this->newField['label'] = '';
        $this->newField['options'] = '';
        $this->newField['content'] = '';
        $this->newField['required'] = false;
        $this->newField['char_limit'] = null;
    }

    private function fieldValidationRules($prefix = 'newField'): array
    {
        $rules = [
            $prefix.'.type' => 'required|in:header,description,text,textarea,select,checkbox,radio,file',
        ];

        // Only require category_id when creating a new field via the add form.
        if ($prefix === 'newField') {
            $rules[$prefix.'.category_id'] = [
                'required',
                Rule::exists('form_categories', 'id')->where('form_id', $this->form->id),
            ];
        }

        if (in_array($this->{$prefix}['type'], ['header', 'description'])) {
            $rules[$prefix.'.content'] = 'required|string|max:500';
        } else {
            $rules[$prefix.'.label'] = 'required|string|max:255';
            if (in_array($this->{$prefix}['type'], ['select', 'checkbox', 'radio'])) {
                $rules[$prefix.'.options'] = 'required|string';
            }
            if (in_array($this->{$prefix}['type'], ['text', 'textarea'])) {
                $rules[$prefix.'.char_limit'] = 'nullable|integer|min:1';  // Add this
            }
            $rules[$prefix.'.required'] = 'boolean';
        }

        return $rules;
    }

    public function moveCategoryUp($categoryId): void
    {
        $category = FormCategory::find($categoryId);
        $switchWith = FormCategory::where('form_id', $this->form->id)
            ->where('order', '<', $category->order)
            ->orderBy('order', 'desc')
            ->first();

        if ($switchWith) {
            DB::transaction(function () use ($category, $switchWith) {
                $tempOrder = $category->order;
                $category->order = $switchWith->order;
                $switchWith->order = $tempOrder;

                $category->save();
                $switchWith->save();
            });

            $this->loadCategories();
        }
    }

    public function moveCategoryDown($categoryId): void
    {
        $category = FormCategory::find($categoryId);
        $switchWith = FormCategory::where('form_id', $this->form->id)
            ->where('order', '>', $category->order)
            ->orderBy('order', 'asc')
            ->first();

        if ($switchWith) {
            DB::transaction(function () use ($category, $switchWith) {
                $tempOrder = $category->order;
                $category->order = $switchWith->order;
                $switchWith->order = $tempOrder;

                $category->save();
                $switchWith->save();
            });

            $this->loadCategories();
        }
    }

    public function moveFieldUp($fieldId): void
    {
        $field = FormField::find($fieldId);
        $switchWith = FormField::where('form_category_id', $field->form_category_id)
            ->where('order', '<', $field->order)
            ->orderBy('order', 'desc')
            ->first();

        if ($switchWith) {
            DB::transaction(function () use ($field, $switchWith) {
                $tempOrder = $field->order;
                $field->order = $switchWith->order;
                $switchWith->order = $tempOrder;

                $field->save();
                $switchWith->save();
            });

            $this->loadCategories();
        }
    }

    public function moveFieldDown($fieldId): void
    {
        $field = FormField::find($fieldId);
        $switchWith = FormField::where('form_category_id', $field->form_category_id)
            ->where('order', '>', $field->order)
            ->orderBy('order', 'asc')
            ->first();

        if ($switchWith) {
            DB::transaction(function () use ($field, $switchWith) {
                $tempOrder = $field->order;
                $field->order = $switchWith->order;
                $switchWith->order = $tempOrder;

                $field->save();
                $switchWith->save();
            });

            $this->loadCategories();
        }
    }
    public function editCategory($categoryId): void
    {
        $this->categoryBeingEdited = FormCategory::find($categoryId)->toArray();
        $this->editingCategory = true;
    }

    public function updateCategory(): void
    {
        $this->validate([
            'categoryBeingEdited.name' => 'required|string|max:255',
            'categoryBeingEdited.description' => 'nullable|string',
            'categoryBeingEdited.percentage_start' => 'required|numeric|min:0|max:100',
            'categoryBeingEdited.percentage_end' => 'required|numeric|min:0|max:100|gt:categoryBeingEdited.percentage_start',
        ]);

        $category = FormCategory::find($this->categoryBeingEdited['id']);
        $category->update($this->categoryBeingEdited);

        $this->editingCategory = false;
        $this->categoryBeingEdited = null;
        $this->loadCategories();
    }

    public function editField($fieldId): void
    {
        $field = FormField::find($fieldId);
        $this->fieldBeingEdited = array_merge($field->toArray(), [
            'required' => (bool) $field->required,
            'options' => $field->options ?? '',
            'content' => $field->content ?? '',
            'char_limit' => $field->char_limit ?? null,
        ]);
        $this->editingField = true;
    }

    public function updateField(): void
    {
        $this->validate($this->fieldValidationRules('fieldBeingEdited'));

        $field = FormField::find($this->fieldBeingEdited['id']);

        // Ensure boolean value is properly set
        $this->fieldBeingEdited['required'] = (bool) $this->fieldBeingEdited['required'];

        // Handle null values appropriately
        if (in_array($this->fieldBeingEdited['type'], ['header', 'description'])) {
            $this->fieldBeingEdited['label'] = null;
            $this->fieldBeingEdited['options'] = null;
            $this->fieldBeingEdited['required'] = false;
            $this->fieldBeingEdited['char_limit'] = null;
        } else {
            $this->fieldBeingEdited['content'] = null;
            if (!in_array($this->fieldBeingEdited['type'], ['select', 'checkbox', 'radio'])) {
                $this->fieldBeingEdited['options'] = null;
            }
            // Ensure char_limit is only kept for text/textarea
            if (!in_array($this->fieldBeingEdited['type'], ['text', 'textarea'])) {
                $this->fieldBeingEdited['char_limit'] = null;
            }
        }

        $field->update($this->fieldBeingEdited);

        $this->editingField = false;
        $this->fieldBeingEdited = null;
        $this->loadCategories();
    }



    // Toggle category collapse state
    public function toggleCategoryCollapse($categoryId): void
    {
        if (in_array($categoryId, $this->collapsedCategories)) {
            $this->collapsedCategories = array_diff($this->collapsedCategories, [$categoryId]);
        } else {
            $this->collapsedCategories[] = $categoryId;
        }
    }

    // Expand all categories
    public function expandAllCategories(): void
    {
        $this->collapsedCategories = [];
    }

    // Collapse all categories
    public function collapseAllCategories(): void
    {
        $this->collapsedCategories = array_column($this->categories, 'id');
    }

    // Open add field panel for specific category
    public function openAddFieldPanel($categoryId = null): void
    {
        $this->showAddFieldPanel = true;
        $this->addingFieldToCategoryId = $categoryId;
        if ($categoryId) {
            $this->newField['category_id'] = $categoryId;
        }
        $this->showAddCategoryPanel = false;
    }

    // Close add field panel
    public function closeAddFieldPanel(): void
    {
        $this->showAddFieldPanel = false;
        $this->addingFieldToCategoryId = null;
        $this->reset('newField');
    }

    // Open add category panel
    public function openAddCategoryPanel(): void
    {
        $this->showAddCategoryPanel = true;
        $this->showAddFieldPanel = false;
    }

    // Close add category panel
    public function closeAddCategoryPanel(): void
    {
        $this->showAddCategoryPanel = false;
        $this->reset('newCategory');
    }

    // Duplicate a field
    public function duplicateField($fieldId): void
    {
        $originalField = FormField::findOrFail($fieldId);
        
        $newField = $originalField->replicate();
        $newField->label = $originalField->label ? $originalField->label . ' (Copy)' : null;
        $newField->content = $originalField->content ? $originalField->content . ' (Copy)' : null;
        $newField->order = $originalField->order + 1;
        $newField->save();

        // Reorder other fields
        FormField::where('form_category_id', $originalField->form_category_id)
            ->where('id', '!=', $newField->id)
            ->where('order', '>=', $newField->order)
            ->increment('order');

        $this->loadCategories();
        $this->dispatch('notify', ['message' => 'Field duplicated successfully', 'type' => 'success']);
    }

    // Duplicate a category with all its fields
    public function duplicateCategory($categoryId): void
    {
        $originalCategory = FormCategory::with('fields')->findOrFail($categoryId);
        
        $newCategory = $originalCategory->replicate();
        $newCategory->name = $originalCategory->name . ' (Copy)';
        $newCategory->order = $originalCategory->order + 1;
        $newCategory->save();

        // Duplicate all fields in the category
        foreach ($originalCategory->fields as $field) {
            $newField = $field->replicate();
            $newField->form_category_id = $newCategory->id;
            $newField->save();
        }

        // Reorder other categories
        FormCategory::where('form_id', $this->form->id)
            ->where('id', '!=', $newCategory->id)
            ->where('order', '>=', $newCategory->order)
            ->increment('order');

        $this->loadCategories();
        $this->dispatch('notify', ['message' => 'Category duplicated successfully', 'type' => 'success']);
    }

    // Handle drag-drop reordering of categories
    public function updateCategoryOrder($orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            FormCategory::where('id', $id)->update(['order' => $index + 1]);
        }
        $this->loadCategories();
    }

    // Handle drag-drop reordering of fields within a category
    public function updateFieldOrder($items): void
    {
        foreach ($items as $index => $item) {
            // Handle both array format and simple ID format
            $fieldId = is_array($item) ? ($item['value'] ?? $item['id'] ?? $item) : $item;
            $order = is_array($item) ? ($item['order'] ?? $index + 1) : $index + 1;
            
            $updateData = ['order' => $order];
            
            // Only update category if 'group' key exists (cross-category drag)
            if (is_array($item) && isset($item['group'])) {
                $updateData['form_category_id'] = $item['group'];
            }
            
            FormField::where('id', $fieldId)->update($updateData);
        }
        $this->loadCategories();
    }

    // Move field to a different category
    public function moveFieldToCategory($fieldId, $newCategoryId): void
    {
        $field = FormField::findOrFail($fieldId);
        $newCategory = FormCategory::findOrFail($newCategoryId);
        
        $field->form_category_id = $newCategoryId;
        $field->order = $newCategory->fields()->max('order') + 1;
        $field->save();
        
        $this->loadCategories();
        $this->dispatch('notify', ['message' => 'Field moved successfully', 'type' => 'success']);
    }

    // Quick add field with preset type
    public function quickAddField($type, $categoryId): void
    {
        $category = FormCategory::findOrFail($categoryId);
        
        $fieldData = [
            'form_id' => $this->form->id,
            'form_category_id' => $categoryId,
            'type' => $type,
            'order' => $category->fields()->max('order') + 1,
        ];

        if (in_array($type, ['header', 'description'])) {
            $fieldData['content'] = $type === 'header' ? 'New Section' : 'Enter description here...';
            $fieldData['label'] = null;
            $fieldData['required'] = false;
        } else {
            $fieldData['label'] = 'New ' . ucfirst(self::$fieldTypes[$type]['label'] ?? $type) . ' Field';
            $fieldData['required'] = false;
            if (in_array($type, ['select', 'checkbox', 'radio'])) {
                $fieldData['options'] = 'Option 1,Option 2,Option 3';
            }
        }

        $newField = FormField::create($fieldData);
        $this->loadCategories();
        
        // Open edit modal for the new field
        $this->editField($newField->id);
    }

    // Get field statistics
    public function getFieldStats(): array
    {
        $totalFields = 0;
        $requiredFields = 0;
        $fieldsByType = [];

        foreach ($this->categories as $category) {
            foreach ($category['fields'] as $field) {
                $totalFields++;
                if (!empty($field['required'])) {
                    $requiredFields++;
                }
                $type = $field['type'];
                $fieldsByType[$type] = ($fieldsByType[$type] ?? 0) + 1;
            }
        }

        return [
            'total' => $totalFields,
            'required' => $requiredFields,
            'byType' => $fieldsByType,
            'categories' => count($this->categories)
        ];
    }

    // Get filtered categories based on search
    public function getFilteredCategoriesProperty(): array
    {
        if (empty($this->searchQuery) && empty($this->activeFieldTypeFilter)) {
            return $this->categories;
        }

        $filtered = [];
        foreach ($this->categories as $category) {
            $matchingFields = array_filter($category['fields'], function ($field) {
                $matchesSearch = empty($this->searchQuery) || 
                    stripos($field['label'] ?? '', $this->searchQuery) !== false ||
                    stripos($field['content'] ?? '', $this->searchQuery) !== false;
                
                $matchesType = empty($this->activeFieldTypeFilter) || 
                    $field['type'] === $this->activeFieldTypeFilter;
                
                return $matchesSearch && $matchesType;
            });

            if (!empty($matchingFields) || 
                stripos($category['name'], $this->searchQuery) !== false) {
                $filteredCategory = $category;
                $filteredCategory['fields'] = array_values($matchingFields);
                $filtered[] = $filteredCategory;
            }
        }

        return $filtered;
    }

    // Clear all filters
    public function clearFilters(): void
    {
        $this->searchQuery = '';
        $this->activeFieldTypeFilter = null;
    }

    // Get field types for the view
    public static function getFieldTypes(): array
    {
        return self::$fieldTypes;
    }

    public function render(): View|Factory|Application
    {
        return view('livewire.form-field-manager', [
            'fieldTypes' => self::$fieldTypes,
            'fieldStats' => $this->getFieldStats(),
            'filteredCategories' => $this->filteredCategories,
        ]);
    }
}
