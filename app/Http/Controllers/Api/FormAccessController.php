<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormAccessLinkResource;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\FormAccessLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FormAccessController extends Controller
{
    /**
     * Display a listing of form access links.
     */
    public function index(Request $request, Form $form): AnonymousResourceCollection|JsonResponse
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Check if user owns or has edit access to the form
        if ($form->user_id !== $userId &&
            ! $form->appointedUsers()->where('user_id', $userId)->where('can_edit', true)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $accessLinks = $form->accessLinks()->latest()->get();

        return FormAccessLinkResource::collection($accessLinks);
    }

    /**
     * Store a newly created access link.
     *
     * @return FormAccessLinkResource|JsonResponse
     */
    public function store(Request $request, Form $form)
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Check if user owns or has edit access to the form
        if ($form->user_id !== $userId &&
            ! $form->appointedUsers()->where('user_id', $userId)->where('can_edit', true)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'expires_at' => 'nullable|date|after:now',
            'max_submissions' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Generate a unique token
        $token = Str::random(32);

        $accessLink = FormAccessLink::create([
            'form_id' => $form->id,
            'token' => $token,
            'name' => $request->name,
            'expires_at' => $request->expires_at,
            'max_submissions' => $request->max_submissions,
            'submission_count' => 0,
        ]);

        return new FormAccessLinkResource($accessLink);
    }

    /**
     * Display the specified access link.
     *
     * @returns FormAccessLinkResource|JsonResponse
     */
    public function show(Request $request, Form $form, FormAccessLink $accessLink)
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Check if access link belongs to the specified form
        if ($accessLink->form_id !== $form->id) {
            return response()->json(['message' => 'Access link not found for this form'], 404);
        }

        // Check if user owns or has edit access to the form
        if ($form->user_id !== $userId &&
            ! $form->appointedUsers()->where('user_id', $userId)->where('can_edit', true)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new FormAccessLinkResource($accessLink);
    }

    /**
     * Update the specified access link.
     *
     * @return FormAccessLinkResource|JsonResponse
     */
    public function update(Request $request, Form $form, FormAccessLink $accessLink)
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Check if access link belongs to the specified form
        if ($accessLink->form_id !== $form->id) {
            return response()->json(['message' => 'Access link not found for this form'], 404);
        }

        // Check if user owns or has edit access to the form
        if ($form->user_id !== $userId &&
            ! $form->appointedUsers()->where('user_id', $userId)->where('can_edit', true)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'expires_at' => 'nullable|date|after:now',
            'max_submissions' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $accessLink->update($request->only(['name', 'expires_at', 'max_submissions']));

        return new FormAccessLinkResource($accessLink);
    }

    /**
     * Remove the specified access link.
     */
    public function destroy(Request $request, Form $form, FormAccessLink $accessLink): JsonResponse
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Check if access link belongs to the specified form
        if ($accessLink->form_id !== $form->id) {
            return response()->json(['message' => 'Access link not found for this form'], 404);
        }

        // Check if user owns the form
        if ($form->user_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $accessLink->delete();

        return response()->json(['message' => 'Access link deleted successfully'], 200);
    }
}
