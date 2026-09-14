<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Form;
use App\Models\ScanResult;
use App\Models\Submission;
use App\Models\SubmissionValues;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubmissionFileTest extends TestCase
{
    use RefreshDatabase;

    private function attachment(): SubmissionValues
    {
        Storage::fake('private');
        $form = Form::factory()->create();
        $category = $form->categories()->create(['name' => 'Files', 'order' => 1]);
        $field = $category->fields()->create(['form_id' => $form->id, 'label' => 'File', 'type' => 'file', 'order' => 1]);
        $submission = Submission::factory()->submitted()->create(['form_id' => $form->id]);
        $path = "submissions/{$submission->id}/report.txt";
        Storage::disk('private')->put($path, 'Synthetic attachment');

        return $submission->values()->create(['form_field_id' => $field->id, 'value' => $path]);
    }

    private function authenticate(User $user, array $abilities = ['submissions:read']): void
    {
        $secret = Str::random(48);
        ApiToken::create(['user_id' => $user->id, 'name' => 'Files', 'token' => hash('sha256', $secret), 'abilities' => $abilities]);
        $this->withToken($secret);
    }

    private function url(SubmissionValues $value): string
    {
        return "/api/v1/forms/{$value->submission->form_id}/submissions/{$value->submission_id}/files/{$value->id}";
    }

    public function test_evaluator_download_is_streamed_with_safe_headers_and_ignores_client_paths(): void
    {
        config(['services.pandora.enabled' => false]);
        $value = $this->attachment();
        $evaluator = User::factory()->create(['role' => 'external_evaluator']);
        $value->submission->form->appointedUsers()->attach($evaluator, ['can_edit' => false]);
        $this->authenticate($evaluator);
        $this->getJson($this->url($value).'?filename=../secret&path=/etc/passwd')->assertOk()
            ->assertDownload('report.txt')->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private')
            ->assertStreamedContent('Synthetic attachment');
    }

    public function test_foreign_and_non_file_ids_are_non_leaking_and_ability_is_required(): void
    {
        $value = $this->attachment();
        $form = $value->submission->form;
        $this->authenticate($form->user, ['forms:read']);
        $this->getJson($this->url($value))->assertForbidden();
        $this->authenticate(User::factory()->create());
        $this->getJson($this->url($value))->assertNotFound();
        $this->authenticate($form->user);
        $other = Submission::factory()->submitted()->create(['form_id' => $form->id]);
        $this->getJson(str_replace($value->submission_id, $other->id, $this->url($value)))->assertNotFound();
        $this->getJson(str_replace('/forms/'.$form->id.'/', '/forms/'.Form::factory()->create()->id.'/', $this->url($value)))->assertNotFound();
        // Simulate invalid legacy data without changing a submitted form's structure.
        DB::table('form_fields')->where('id', $value->form_field_id)->update(['type' => 'text']);
        $this->getJson($this->url($value))->assertNotFound();
    }

    public function test_api_and_web_use_the_same_scan_gate_for_every_state(): void
    {
        $value = $this->attachment();
        $owner = $value->submission->form->user;
        $this->authenticate($owner);
        $this->actingAs($owner);
        config(['services.pandora.enabled' => true, 'services.pandora.block_malicious' => true]);
        foreach ([null, 'pending', 'failed', 'malicious', 'clean'] as $status) {
            ScanResult::where('submission_value_id', $value->id)->delete();
            if ($status) {
                ScanResult::create(['submission_id' => $value->submission_id, 'submission_value_id' => $value->id, 'filename' => 'report.txt', 'scanner_used' => 'pandora', 'status' => $status, 'is_malicious' => $status === 'malicious']);
            }
            foreach ([$this->url($value), route('submissions.download', ['submission' => $value->submission_id, 'filename' => 'report.txt'])] as $url) {
                $this->getJson($url)->assertStatus($status === 'clean' ? 200 : 423);
            }
        }
        config(['services.pandora.block_malicious' => false]);
        ScanResult::where('submission_value_id', $value->id)->update(['status' => 'malicious']);
        $this->getJson($this->url($value))->assertOk();
    }

    public function test_missing_and_corrupt_server_paths_return_json_404(): void
    {
        config(['services.pandora.enabled' => false]);
        $value = $this->attachment();
        $this->authenticate($value->submission->form->user);
        Storage::disk('private')->delete($value->value);
        $this->getJson($this->url($value))->assertNotFound()->assertJsonStructure(['message']);
        foreach (['../secret', 'submissions/other/report.txt', "submissions/{$value->submission_id}/../secret"] as $path) {
            $value->update(['value' => $path]);
            $this->getJson($this->url($value))->assertNotFound()->assertDontSee($path);
        }
    }

    public function test_resources_expose_file_metadata_and_url_without_storage_paths(): void
    {
        config(['services.pandora.enabled' => false]);
        $value = $this->attachment();
        $this->authenticate($value->submission->form->user);
        $base = "/api/v1/forms/{$value->submission->form_id}/submissions";
        $this->getJson($base.'/'.$value->submission_id)->assertOk()->assertDontSee($value->value)
            ->assertJsonPath('data.values.0.value.filename', 'report.txt')
            ->assertJsonPath('data.values.0.value.download_url', url($this->url($value)));
        $this->getJson($base)->assertOk()->assertDontSee($value->value);
    }
}
