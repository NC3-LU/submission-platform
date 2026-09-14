<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormCollaboratorResource;
use App\Models\ApiToken;
use App\Models\Form;
use App\Services\FormCollaborators;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FormCollaboratorController extends Controller
{
    public function index(Request $request, Form $form): AnonymousResourceCollection
    {
        $this->authorizeForm($request, $form);
        $data = $request->validate(['per_page' => 'sometimes|integer|min:1|max:100']);

        return FormCollaboratorResource::collection($form->appointedUsers()->orderBy('users.id')->paginate($data['per_page'] ?? 25));
    }

    public function store(Request $request, Form $form, FormCollaborators $collaborators): JsonResponse
    {
        $token = $this->authorizeForm($request, $form);
        $data = $request->validate(['user_id' => 'required|integer|min:1', 'permission' => ['required', Rule::in(['viewer', 'editor'])]]);
        [$member, $created] = $collaborators->change($form, $token, $data['user_id'], $data['permission']);

        return (new FormCollaboratorResource($member))->response()->setStatusCode($created ? 201 : 200);
    }

    public function update(Request $request, Form $form, int $user, FormCollaborators $collaborators): FormCollaboratorResource
    {
        $token = $this->authorizeForm($request, $form);
        $data = $request->validate(['permission' => ['required', Rule::in(['viewer', 'editor'])]]);
        [$member] = $collaborators->change($form, $token, $user, $data['permission'], true);

        return new FormCollaboratorResource($member);
    }

    public function destroy(Request $request, Form $form, int $user, FormCollaborators $collaborators): Response
    {
        $collaborators->change($form, $this->authorizeForm($request, $form), $user, null, true);

        return response()->noContent();
    }

    private function authorizeForm(Request $request, Form $form): ApiToken
    {
        $token = ApiToken::fromRequest($request);
        abort_unless(Gate::forUser($token->user)->allows('manageCollaborators', $form), 404);

        return $token;
    }
}
