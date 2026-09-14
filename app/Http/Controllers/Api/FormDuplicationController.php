<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormResource;
use App\Models\ApiToken;
use App\Models\Form;
use App\Services\FormDuplicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FormDuplicationController extends Controller
{
    public function store(Request $request, Form $form, FormDuplicator $duplicator): JsonResponse
    {
        $user = ApiToken::fromRequest($request)->user;
        abort_unless(Gate::forUser($user)->allows('duplicate', $form), 404);

        return (new FormResource($duplicator->duplicate($form, $user)))->response()->setStatusCode(201);
    }
}
