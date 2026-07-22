<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormAccessLink;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormAccessController extends Controller
{
    use AuthorizesRequests;

    /**
     * @throws AuthorizationException
     */
    public function assignUsers(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('assignUsers', $form);

        // Build the allowlist of eligible user IDs on the server side
        $allowedUserIds = User::whereIn('role', ['internal_evaluator', 'external_evaluator', 'admin'])
            ->where('id', '!=', auth()->id())
            ->pluck('id')
            ->toArray();

        // Validate only against the server-side allowlist. Any tampered or injected IDs will be rejected.
        $validatedData = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'distinct', Rule::in($allowedUserIds)],
            'can_edit' => ['boolean'],
        ]);

        $existingUserIds = $form->appointedUsers->pluck('id')->toArray();

        $newUserIds = array_diff($validatedData['user_ids'], $existingUserIds);

        foreach ($newUserIds as $userId) {
            $form->appointedUsers()->attach($userId, [
                'can_edit' => $validatedData['can_edit'] ?? false,
            ]);
        }

        return redirect()->route('forms.edit', $form)->with('success', 'Users assigned successfully.');
    }

    /**
     * @throws AuthorizationException
     */
    public function createAccessLink(Request $request, Form $form): RedirectResponse
    {

        $this->authorize('createAccessLink', $form);

        $validatedData = $request->validate([
            'expires_at' => 'nullable|date|after:now',
        ]);

        $accessLink = $form->accessLinks()->create([
            'token' => Str::random(32),
            'expires_at' => $validatedData['expires_at'] ?? null,
        ]);

        return redirect()->route('forms.edit', $form)
            ->with('success', 'Access link created successfully.');
    }

    /**
     * @throws AuthorizationException
     */
    public function deleteAccessLink(FormAccessLink $accessLink): RedirectResponse
    {
        $this->authorize('delete', $accessLink);

        // Get the form ID before deleting the link
        $formId = $accessLink->form_id;

        $accessLink->delete();

        // Clear any existing sessions using this access link
        \Session::getHandler()->destroy('form_access_'.$formId);

        return redirect()->route('forms.edit', $accessLink->form)
            ->with('success', 'Access link deleted successfully.');
    }

    public function accessForm(Request $request, $token): RedirectResponse
    {
        $accessLink = FormAccessLink::findValidByToken($token);

        if (! $accessLink) {
            return redirect()->route('homepage')->with('error', 'This access link is invalid or has expired.');
        }

        // Store both the token and expiry time in the session
        $request->session()->put('form_access_'.$accessLink->form_id, [
            'token' => $token,
            'expires_at' => $accessLink->expires_at?->timestamp,
        ]);

        return redirect()->route('submissions.create', $accessLink->form);
    }
}
