<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubmissionExportResource;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\IntegrationEvent;
use App\Models\SubmissionExport;
use App\Services\SubmissionExports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionExportController extends Controller
{
    public function store(Request $request, Form $form, SubmissionExports $exports): JsonResponse
    {
        $token = ApiToken::fromRequest($request);
        abort_unless(Gate::forUser($token->user)->allows('exportSubmissions', $form), 404);
        $data = $request->validate(['format' => 'required|string|in:json,xlsx']);

        return (new SubmissionExportResource($exports->create($form, $token, $data['format'])))->response()->setStatusCode(202);
    }

    public function show(Request $request, Form $form, SubmissionExport $export): SubmissionExportResource
    {
        $this->authorizeExport($request, $form, $export);

        return new SubmissionExportResource($export);
    }

    public function download(Request $request, Form $form, SubmissionExport $export): StreamedResponse
    {
        $this->authorizeExport($request, $form, $export);
        abort_if($export->expires_at->isPast(), 410, 'This export has expired.');
        abort_unless($export->status === 'completed', 409, 'The export is not ready.');
        abort_unless($export->path === $export->artifactPath() && Storage::disk('private')->exists($export->path), 404, 'Export file not found.');
        $token = ApiToken::fromRequest($request);
        IntegrationEvent::record('export.downloaded', $form->id, $export->id, $token->user_id, $token->id);

        return Storage::disk('private')->download($export->path, 'submissions-'.$form->id.'.'.$export->format, [
            'Content-Type' => $export->format === 'json' ? 'application/json' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeExport(Request $request, Form $form, SubmissionExport $export): void
    {
        $token = ApiToken::fromRequest($request);
        abort_unless($export->form_id === $form->id && $export->user_id === $token->user_id
            && Gate::forUser($token->user)->allows('exportSubmissions', $form), 404);
    }
}
