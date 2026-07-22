<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormResource;
use App\Models\ApiToken;
use App\Models\Form;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FormController extends Controller
{
    /**
     * Display a listing of forms for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        $query = Form::query()->where('user_id', $userId);

        // Apply filters if provided
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('visibility')) {
            $query->where('visibility', $request->visibility);
        }

        $forms = $query->latest()->paginate($request->per_page ?? 15);

        return FormResource::collection($forms);
    }

    /**
     * Store a newly created form.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,published,archived',
            'visibility' => 'required|in:public,authenticated,private',
            'categories' => 'array',
            'categories.*.name' => 'required|string|max:255',
            'categories.*.order' => 'required|integer',
            'categories.*.fields' => 'array',
            'categories.*.fields.*.type' => 'required|string',
            'categories.*.fields.*.label' => 'required|string',
            'categories.*.fields.*.required' => 'required|boolean',
            'categories.*.fields.*.options' => 'nullable|string',
            'categories.*.fields.*.order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $apiToken = ApiToken::fromRequest($request);
            $userId = $apiToken->user_id;

            // Use a transaction to ensure data integrity
            return DB::transaction(function () use ($request, $userId) {
                // Create the form
                $form = Form::create([
                    'user_id' => $userId,
                    'title' => $request->title,
                    'description' => $request->description ?? null,
                    'status' => $request->status,
                    'visibility' => $request->visibility,
                ]);

                // Create categories and fields
                if ($request->has('categories')) {
                    foreach ($request->categories as $categoryData) {
                        $category = $form->categories()->create([
                            'name' => $categoryData['name'],
                            'order' => $categoryData['order'],
                        ]);

                        if (isset($categoryData['fields'])) {
                            foreach ($categoryData['fields'] as $fieldData) {
                                $form->fields()->create([
                                    'form_category_id' => $category->id,
                                    'type' => $fieldData['type'],
                                    'label' => $fieldData['label'],
                                    'required' => $fieldData['required'],
                                    'options' => $fieldData['options'] ?? null,
                                    'order' => $fieldData['order'],
                                ]);
                            }
                        }
                    }
                }

                // Load relationships for the response
                $form->load('categories.fields');

                return response()->json([
                    'message' => 'Form created successfully',
                    'data' => new FormResource($form),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('API Form creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $response = ['message' => 'Form creation failed'];

            // Only include error details in debug mode
            if (config('app.debug')) {
                $response['error'] = $e->getMessage();
            }

            return response()->json($response, 500);
        }
    }

    /**
     * Display the specified form.
     *
     * @return FormResource|JsonResponse
     */
    public function show(Request $request, Form $form)
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Check if user owns or has access to the form
        if ($form->user_id !== $userId &&
            ! $form->appointedUsers()->where('user_id', $userId)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $form->load('categories.fields');

        return new FormResource($form);
    }

    /**
     * Update the specified form.
     *
     * @return FormResource|JsonResponse
     */
    public function update(Request $request, Form $form)
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Check if user owns the form or has edit permission
        if ($form->user_id !== $userId &&
            ! $form->appointedUsers()->where('user_id', $userId)->where('can_edit', true)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:draft,published,archived',
            'visibility' => 'sometimes|required|in:public,authenticated,private',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $form->update($request->only(['title', 'description', 'status', 'visibility']));

        return new FormResource($form);
    }

    /**
     * Remove the specified form.
     */
    public function destroy(Request $request, Form $form): JsonResponse
    {
        $apiToken = ApiToken::fromRequest($request);
        $userId = $apiToken->user_id;

        // Check if user owns the form
        if ($form->user_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete the form using a transaction to ensure all related data is removed
        DB::transaction(function () use ($form) {
            // Delete fields and categories first to maintain database integrity
            $form->fields()->delete();
            $form->categories()->delete();
            $form->delete();
        });

        return response()->json(['message' => 'Form deleted successfully'], 200);
    }
}
