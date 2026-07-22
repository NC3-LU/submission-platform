<?php

namespace App\Policies;

use App\Models\FormAccessLink;
use App\Models\User;

class FormAccessLinkPolicy
{
    /**
     * Determine whether the user can delete the access link.
     */
    public function delete(User $user, FormAccessLink $accessLink): bool
    {
        return $user->isAdmin() ||
            $user->id === $accessLink->form->user_id ||
            ($user->role === 'internal_evaluator' &&
                $accessLink->form->appointedUsers()
                    ->where('user_id', $user->id)
                    ->where('can_edit', true)
                    ->exists()
            );
    }
}
