<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class FormController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|Factory|Application
    {
        $forms = Form::where('status', 'published')
            ->whereIn('visibility', ['public', 'authenticated'])
            ->latest()
            ->take(6)
            ->get();

        return view('index', compact('forms'));
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
     * @throws AuthorizationException
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Form::class);

        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'visibility' => 'required|in:public,authenticated,private',
            'categories' => 'required|array|min:1',
            'categories.*.name' => 'required|string|max:255',
            'categories.*.description' => 'nullable|string',
        ]);

        $form = Form::create([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'visibility' => $validatedData['visibility'],
            'status' => 'draft',
            'user_id' => auth()->id(),
        ]);

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

    /**
     * @throws AuthorizationException
     */
    public function update(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('update', $form);

        $request->validate([
            'title' => 'required|max:255',
            'description' => 'nullable',
            'status' => 'required|in:draft,published,archived',
            'visibility' => 'required|in:public,authenticated,private',
        ]);

        $form->update($request->only('title', 'description', 'status', 'visibility'));

        return redirect()->route('forms.user_index')->with('success', 'Form updated successfully.');
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Form $form): RedirectResponse
    {
        $this->authorize('delete', $form);

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
}
