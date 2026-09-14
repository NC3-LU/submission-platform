<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\Form;
use App\Models\IntegrationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class FormCollaborators
{
    public function change(Form $form, ApiToken $token, int $userId, ?string $permission, bool $existingOnly = false): array
    {
        return DB::transaction(function () use ($form, $token, $userId, $permission, $existingOnly) {
            $form = Form::whereKey($form->id)->lockForUpdate()->firstOrFail();
            abort_unless(Gate::forUser($token->user)->allows('manageCollaborators', $form), 404);
            if ($userId === $form->user_id || $userId === $token->user_id) {
                throw ValidationException::withMessages(['user_id' => 'Owner and self changes are not supported by collaborator routes.']);
            }
            $member = $form->appointedUsers()->where('users.id', $userId)->first();
            abort_if($existingOnly && ! $member, 404);
            if ($permission === null) {
                $form->appointedUsers()->detach($userId);
                IntegrationEvent::record('collaborator.removed', $form->id, $userId, $token->user_id, $token->id);

                return [null, false];
            }
            $target = User::whereKey($userId)->whereIn('role', ['internal_evaluator', 'external_evaluator'])->first();
            if (! $target) {
                throw ValidationException::withMessages(['user_id' => 'This collaborator is unavailable.']);
            }
            abort_if(! $member && $form->appointedUsers()->count() >= 100, 422, 'The collaborator limit has been reached.');
            $canEdit = $permission === 'editor';
            if (! $member || (bool) $member->pivot->can_edit !== $canEdit) {
                $form->appointedUsers()->syncWithoutDetaching([$userId => ['can_edit' => $canEdit]]);
                IntegrationEvent::record($member ? 'collaborator.updated' : 'collaborator.granted', $form->id, $userId, $token->user_id, $token->id, ['permission' => $permission]);
            }

            return [$form->appointedUsers()->where('users.id', $userId)->firstOrFail(), ! $member];
        });
    }
}
