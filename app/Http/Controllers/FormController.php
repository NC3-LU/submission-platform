<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFormRequest;
use App\Http\Requests\UpdateFormRequest;
use App\Models\Form;
use App\Models\User;
use App\Services\ImageColorExtractor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class FormController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|Factory|Application
    {
        $query = Form::where('status', 'published')
            ->whereIn('visibility', ['public', 'authenticated'])
            ->latest();

        $totalForms = $query->count();
        $forms = $query->take(6)->get();

        return view('index', compact('forms', 'totalForms'));
    }
    public function publicIndex(Request $request): View|Factory|Application
    {
        $forms = Form::where('status', 'published')
            ->whereIn('visibility', ['public', 'authenticated'])
            ->latest()
            ->paginate(10);

        return view('forms.public-index', compact('forms'));
    }

    /**
     * @throws AuthorizationException
     */
    public function userIndex(): View|Factory|Application
    {
        $this->authorize('create', Form::class);

        // Get forms created by the user
        $createdForms = Auth::user()->forms()->latest();

        // Get forms the user is assigned to
        $assignedForms = Form::whereHas('appointedUsers', function($query) {
            $query->where('user_id', Auth::id());
        })->latest();

        // Combine both queries and get the results
        $forms = $createdForms->union($assignedForms)->get();

        return view('forms.user-index', ['forms' => $forms]);
    }

    /**
     * @throws AuthorizationException
     */
    public function create(): View|Factory|Application
    {
        $this->authorize('create', Form::class);
        return view('forms.create');
    }

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

    public function store(StoreFormRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();

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

        foreach ($validatedData['categories'] as $index => $categoryData) {
            $form->categories()->create([
                'name' => $categoryData['name'],
                'description' => $categoryData['description'],
                'order' => $index + 1,
            ]);
        }

        return redirect()->route('forms.edit', $form)->with('success', 'Form created successfully.');
    }

    /**
     * @throws AuthorizationException
     */
    public function edit(Form $form): View|Factory|Application
    {
        $this->authorize('update', $form);
        return view('forms.edit', compact('form'));
    }

    public function update(UpdateFormRequest $request, Form $form): RedirectResponse
    {
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

        return redirect()->route('forms.user_index')->with('success', 'Form updated successfully.');
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Form $form): RedirectResponse
    {
        $this->authorize('delete', $form);

        if ($form->header_image) {
            Storage::disk('public')->delete($form->header_image);
        }

        $form->delete();

        return redirect()->route('forms.user_index')->with('success', 'Form deleted successfully.');
    }

    /**
     * @throws AuthorizationException
     */
    public function preview(Form $form): View|Factory|Application
    {
        // Only allow form creators and appointed users (evaluators) to access preview
        if ($form->user_id !== Auth::id() && 
            !$form->appointedUsers()->where('user_id', Auth::id())->exists()) {
            abort(403, 'Only form creators and evaluators can access form previews.');
        }

        return view('forms.preview', compact('form'));
    }

    /**
     * Remove a user from the form.
     *
     * @param Form $form
     * @param User $user
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function removeUser(Form $form, User $user): RedirectResponse
    {
        $this->authorize('assignUsers', $form);

        $form->appointedUsers()->detach($user->id);

        return redirect()->route('forms.edit', $form)
            ->with('success', 'User removed successfully.');
    }

    /**
     * Duplicate a form with its categories and fields.
     *
     * @throws AuthorizationException
     */
    public function duplicate(Form $form): RedirectResponse
    {
        $this->authorize('duplicate', $form);

        $newForm = DB::transaction(function () use ($form) {
            $clone = $form->replicate(['id', 'created_at', 'updated_at']);
            $clone->title = $form->title . ' (Copy)';
            $clone->status = 'draft';
            $clone->user_id = auth()->id();

            if ($form->header_image && Storage::disk('public')->exists($form->header_image)) {
                $ext = pathinfo($form->header_image, PATHINFO_EXTENSION);
                $newPath = 'form-headers/'.Str::uuid().($ext ? '.'.$ext : '');
                Storage::disk('public')->copy($form->header_image, $newPath);
                $clone->header_image = $newPath;
            }

            $clone->save();

            foreach ($form->categories()->orderBy('order')->get() as $category) {
                $newCategory = $clone->categories()->create([
                    'name' => $category->name,
                    'description' => $category->description,
                    'order' => $category->order,
                ]);

                foreach ($category->fields()->orderBy('order')->get() as $field) {
                    $newCategory->fields()->create([
                        'form_id' => $clone->id,
                        'label' => $field->label,
                        'type' => $field->type,
                        'options' => $field->options,
                        'required' => $field->required,
                        'content' => $field->content,
                        'char_limit' => $field->char_limit,
                        'order' => $field->order,
                    ]);
                }
            }

            return $clone;
        });

        return redirect()->route('forms.edit', $newForm)->with('success', 'Form cloned successfully.');
    }
}
