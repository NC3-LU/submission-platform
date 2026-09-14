<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\Submission;
use App\Models\SubmissionValues;
use App\Services\SubmissionFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionFileController extends Controller
{
    public function show(Request $request, Form $form, Submission $submission, SubmissionValues $value, SubmissionFiles $files): StreamedResponse
    {
        abort_unless($submission->form_id === $form->id && $value->submission_id === $submission->id
            && Gate::forUser(ApiToken::fromRequest($request)->user)->allows('view', $submission), 404);

        return $files->download($value->loadMissing(['field', 'submission', 'scanResult']));
    }
}
