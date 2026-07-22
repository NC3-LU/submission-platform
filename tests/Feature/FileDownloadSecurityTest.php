<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileDownloadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createSubmissionForUser(User $user): Submission
    {
        $form = Form::factory()->for($user)->create(['status' => 'published']);

        return $form->submissions()->create([
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);
    }

    public function test_dot_dot_filename_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $submission = $this->createSubmissionForUser($user);

        $this->actingAs($user);

        $response = $this->getJson(route('submissions.download', [
            'submission' => $submission->id,
            'filename' => '..',
        ]));

        $response->assertStatus(403);
    }

    public function test_null_bytes_in_filename_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $submission = $this->createSubmissionForUser($user);

        $this->actingAs($user);

        $response = $this->getJson(route('submissions.download', [
            'submission' => $submission->id,
            'filename' => "file.pdf\0.php",
        ]));

        $response->assertStatus(403);
    }
}
