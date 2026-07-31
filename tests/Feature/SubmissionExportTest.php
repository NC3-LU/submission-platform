<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class SubmissionExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_owner_can_export_submissions_as_xlsx(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $submitter = User::factory()->create([
            'role' => 'user',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
        ]);
        $form = Form::factory()->for($owner)->create([
            'title' => 'Grant applications',
            'status' => 'published',
            'visibility' => 'public',
        ]);
        $category = $form->categories()->create(['name' => 'Project', 'order' => 1]);
        $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Introduction',
            'type' => 'header',
            'content' => 'Tell us about the project.',
            'required' => false,
            'order' => 0,
        ]);
        $nameField = $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Project name',
            'type' => 'text',
            'required' => true,
            'order' => 1,
        ]);
        $countryField = $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Country',
            'type' => 'select',
            'required' => false,
            'order' => 2,
        ]);

        $submission = $form->submissions()->create([
            'user_id' => $submitter->id,
            'status' => 'submitted',
        ]);
        $submission->values()->createMany([
            ['form_field_id' => $nameField->id, 'value' => '=2+2'],
            ['form_field_id' => $countryField->id, 'value' => 'Luxembourg'],
        ]);

        $response = $this->actingAs($owner)
            ->get(route('submissions.export.form.xlsx', $form));

        $response->assertOk();
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $response->assertDownload('grant-applications-submissions.xlsx');

        $path = $response->baseResponse->getFile()->getPathname();
        $reader = new Reader;
        $rows = [];

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(
                        static fn ($cell) => $cell->getValue(),
                        $row->getCells()
                    );
                }

                break;
            }
        } finally {
            $reader->close();
            @unlink($path);
        }

        $this->assertSame([
            'Submission ID',
            'Status',
            'Submitted By',
            'Email',
            'Submitted At',
            'Last Updated',
            'Project - Project name',
            'Project - Country',
        ], $rows[0]);
        $this->assertSame($submission->id, $rows[1][0]);
        $this->assertSame('submitted', $rows[1][1]);
        $this->assertSame('Ada Lovelace', $rows[1][2]);
        $this->assertSame('ada@example.test', $rows[1][3]);
        $this->assertSame('=2+2', $rows[1][6]);
        $this->assertSame('Luxembourg', $rows[1][7]);
    }

    public function test_non_owner_cannot_export_form_submissions_as_xlsx(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->get(route('submissions.export.form.xlsx', $form))
            ->assertForbidden();
    }

    public function test_user_can_export_own_submission_as_json(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);
        $category = $form->categories()->create(['name' => 'General', 'order' => 1]);
        $field = $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Name',
            'type' => 'text',
            'required' => false,
            'order' => 1,
        ]);

        $submission = $form->submissions()->create([
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);
        $submission->values()->create([
            'form_field_id' => $field->id,
            'value' => 'Test Value',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('submissions.export.single.json', [
            'form' => $form->id,
            'submission' => $submission->id,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_user_can_export_submission_with_status_metadata_as_json(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $form = Form::factory()->for($user)->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);
        $category = $form->categories()->create(['name' => 'General', 'order' => 1]);
        $field = $category->fields()->create([
            'form_id' => $form->id,
            'label' => 'Name',
            'type' => 'text',
            'required' => false,
            'order' => 1,
        ]);

        $submission = $form->submissions()->create([
            'user_id' => $user->id,
            'status' => 'submitted',
            'status_metadata' => ['reviewer' => 'admin', 'notes' => 'Approved'],
        ]);
        $submission->values()->create([
            'form_field_id' => $field->id,
            'value' => 'Test Value',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('submissions.export.single.json', [
            'form' => $form->id,
            'submission' => $submission->id,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        $data = $response->json();
        $this->assertIsArray($data['status_metadata']);
        $this->assertEquals('admin', $data['status_metadata']['reviewer']);
    }
}
