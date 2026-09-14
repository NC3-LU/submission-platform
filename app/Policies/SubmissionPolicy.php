<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    /**
     * Perform pre-authorization checks on the model.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() && $ability !== 'export') {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can delete a submission.
     */
    public function delete(User $user, Submission $submission): bool
    {

        return $user->id === $submission->user_id && $submission->status === 'draft';
    }

    public function view(User $user, Submission $submission): bool
    {
        // Can't view drafts unless you're the owner
        if (in_array($submission->status, ['draft', 'ongoing']) && $user->id !== $submission->user_id) {
            return false;
        }

        // Form owner can view all submissions
        if ($user->id === $submission->form->user_id) {
            return true;
        }

        // Users can view their own submissions
        if ($user->id === $submission->user_id) {
            return true;
        }

        // Evaluators can view if appointed
        if (in_array($user->role, ['internal_evaluator', 'external_evaluator'])) {
            return $submission->form->appointedUsers()
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }

    public function update(User $user, Submission $submission): bool
    {
        // Users can edit their own submissions if they're still in draft/ongoing status
        if ($user->id === $submission->user_id) {
            return in_array($submission->status, ['draft', 'ongoing']);
        }

        return false;
    }

    public function viewAny(User $user, Form $form): bool
    {
        // Admin or form owner can view all submissions
        if ($user->isAdmin() || $user->id === $form->user_id) {
            return true;
        }

        // Evaluators must be appointed to view
        if (in_array($user->role, ['internal_evaluator', 'external_evaluator'])) {
            return $form->appointedUsers()
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }

    public function manageStatus(User $user, Submission $submission): bool
    {
        return $this->view($user, $submission) && ! in_array($submission->status, Submission::EDITABLE_STATUSES)
            && ($user->id === $submission->form->user_id || $submission->form->appointedUsers()->where('user_id', $user->id)->where('can_edit', true)->exists());
    }

    public function review(User $user, Submission $submission): bool
    {
        // Only reviewable if under review
        if ($submission->status !== 'under_review') {
            return false;
        }

        // Internal evaluators need edit rights
        if ($user->role === 'internal_evaluator') {
            return $submission->form->appointedUsers()
                ->where('user_id', $user->id)
                ->where('can_edit', true)
                ->exists();
        }

        // External evaluators just need to be appointed
        if ($user->role === 'external_evaluator') {
            return $submission->form->appointedUsers()
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }

    public function export(User $user, Submission $submission): bool
    {
        return ! in_array($submission->status, Submission::EDITABLE_STATUSES)
            && ($user->isAdmin() || $this->view($user, $submission));
    }

    public function deleteAsEvaluator(User $user, Submission $submission): bool
    {
        return $user->id === $submission->form->user_id && $this->view($user, $submission);
    }

    /**
     * Determine whether the user can view draft submissions.
     */
    public function viewDrafts(User $user, Form $form): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can edit a submission.
     */
    public function edit(User $user, Submission $submission): bool
    {
        return $this->update($user, $submission);
    }

    /**
     * Determine whether the user can save drafts.
     */
    public function saveDraft(User $user, Form $form): bool
    {
        // Anyone who can submit can save drafts
        return (new FormPolicy)->submit($user, $form);
    }

    /**
     * Determine whether the user can export/download a specific submission.
     */
    public function generalPolicy(User $user, Submission $submission): bool
    {
        return $this->view($user, $submission);
    }
}
