<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\User;

class FormPolicy
{
    /**
     * Perform pre-authorization checks on the model.
     */
    public function before(User $user): ?bool
    {
        // Admins have full access to everything
        if ($user->isAdmin()) {
            return true;
        }

        return null; // Fall through to specific policy methods
    }

    /**
     * Determine whether the user can view the list of forms.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can see the forms list
        return true;
    }

    /**
     * Determine whether the user can view the form.
     */
    public function view(User $user, Form $form): bool
    {
        // Form creator can always view their forms
        if ($user->id === $form->user_id) {
            return true;
        }

        // Check if user is appointed to this form
        if ($form->appointedUsers->contains($user->id)) {
            return true;
        }

        // Check if user has previously submitted to this form
        if ($form->submissions()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Check if form is public or authenticated
        if ($form->visibility === 'public' ||
            ($form->visibility === 'authenticated' && auth()->check())) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create forms.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['internal_evaluator','external_evaluator', 'admin']);
    }

    /**
     * Determine whether the user can update the form.
     */
    public function update(User $user, Form $form): bool
    {
        // Form creator can edit their own forms
        if ($user->id === $form->user_id) {
            return true;
        }

        // Appointed users with can_edit permission can edit
        return $form->appointedUsers()
            ->where('user_id', $user->id)
            ->where('can_edit', true)
            ->exists();
    }

    /**
     * Determine whether the user can delete the form.
     */
    public function delete(User $user, Form $form): bool
    {
        // Only form creator can delete their forms
        return $user->id === $form->user_id;
    }

    /**
     * Determine whether the user can assign other users to the form.
     */
    public function assignUsers(User $user, Form $form): bool
    {
        // Form creator can assign users
        if ($user->id === $form->user_id) {
            return true;
        }

        // Internal evaluators with edit rights can assign users
        if ($user->role === 'internal_evaluator') {
            return $form->appointedUsers()
                ->where('user_id', $user->id)
                ->where('can_edit', true)
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create access links.
     */
    public function createAccessLink(User $user, Form $form): bool
    {
        // Only form creator can create access links
        return $user->id === $form->user_id;
    }

    /**
     * Determine whether the user can view form submissions.
     */
    public function viewSubmissions(User $user, Form $form): bool
    {
        // Form creator can view all submissions
        if ($user->id === $form->user_id) {
            return true;
        }

        // Users can view their own submissions
        if ($form->submissions()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Appointed users with can_edit can view all submissions
        return $form->appointedUsers()
            ->where('user_id', $user->id)
            ->where('can_edit', true)
            ->exists();
    }

    /**
     * Determine whether the user can submit to the form.
     */
    public function submit(User $user, Form $form): bool
    {
        switch ($form->visibility) {
            case 'public':
                return true;

            case 'authenticated':
                return auth()->check();

            case 'private':
                // Check for valid access link in session
                if (session()->has('form_access_' . $form->id)) {
                    return true;
                }

                // Check if user is appointed or creator
                return $user->id === $form->user_id ||
                    $form->appointedUsers->contains($user->id);
        }

        return false;
    }

    /**
     * Determine whether the user can restore soft-deleted forms.
     */
    public function restore(User $user, Form $form): bool
    {
        return $user->id === $form->user_id;
    }

    /**
     * Determine whether the user can permanently delete forms.
     */
    public function forceDelete(User $user, Form $form): bool
    {
        return $user->id === $form->user_id;
    }

    /**
     * Determine whether the user can export form submissions.
     */
    public function exportSubmissions(User $user, Form $form): bool
    {
        // Form creator can export submissions
        if ($user->id === $form->user_id) {
            return true;
        }

        // Internal evaluators with edit rights can export submissions
        if ($user->role === 'internal_evaluator' || $user->role === 'external_evaluator') {
            return $form->appointedUsers()
                ->where('user_id', $user->id)
                ->where('can_edit', true)
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can duplicate the form.
     */
    public function duplicate(User $user, Form $form): bool
    {
        // Only creators and internal evaluators can duplicate forms
        if ($user->id === $form->user_id) {
            return true;
        }

        return in_array($user->role, ['internal_evaluator', 'external_evaluator']) &&
            $form->appointedUsers()
                ->where('user_id', $user->id)
                ->where('can_edit', true)
                ->exists();
    }

    /**
     * Determine whether the user can export all form submissions.
     */
    public function exportAllSubmissions(User $user, Form $form): bool
    {
        // Form creator can export all submissions
        if ($user->id === $form->user_id) {
            return true;
        }

        // Internal evaluators with edit rights can export all submissions
        if ($user->role === 'internal_evaluator') {
            return $form->appointedUsers()
                ->where('user_id', $user->id)
                ->exists();
        }

        // External evaluators can export all submissions if appointed
        if ($user->role === 'external_evaluator') {
            return $form->appointedUsers()
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can export individual submissions.
     */
    public function exportSubmission(User $user, Form $form): bool
    {
        // Form creator can export any submission
        if ($user->id === $form->user_id) {
            return true;
        }

        // Users can export their own submissions
        if ($form->submissions()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Internal evaluators with edit rights can export submissions
        if ($user->role === 'internal_evaluator') {
            return $form->appointedUsers()
                ->where('user_id', $user->id)
                ->exists();
        }

        // External evaluators can export submissions if appointed
        if ($user->role === 'external_evaluator') {
            return $form->appointedUsers()
                ->where('user_id', $user->id)
                ->exists();
        }

        // Appointed users with can_edit permission can export submissions
        return $form->appointedUsers()
            ->where('user_id', $user->id)
            ->where('can_edit', true)
            ->exists();
    }
}
