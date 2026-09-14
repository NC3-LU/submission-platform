<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationalSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_rolling_back_deletion_keeps_attachment_and_response(): void
    {
        Storage::fake('private');
        $form = Form::factory()->create();
        $category = $form->categories()->create(['name' => 'Files', 'order' => 1]);
        $field = $category->fields()->create(['form_id' => $form->id, 'label' => 'File', 'type' => 'file', 'order' => 1]);
        $submission = Submission::factory()->submitted()->create(['form_id' => $form->id]);
        $path = 'submissions/'.$submission->id.'/test.pdf';
        Storage::disk('private')->put($path, 'test');
        $submission->values()->create(['form_field_id' => $field->id, 'value' => $path]);
        DB::beginTransaction();
        $form->delete();
        DB::rollBack();
        $this->assertDatabaseHas('submissions', ['id' => $submission->id]);
        Storage::disk('private')->assertExists($path);
    }

    public function test_pruning_temporary_uploads_preserves_recent_and_referenced_files(): void
    {
        Storage::fake('private');
        $disk = Storage::disk('private');
        $disk->put('temp-submissions/old.pdf', 'old');
        touch($disk->path('temp-submissions/old.pdf'), now()->subDays(3)->timestamp);
        $disk->put('temp-submissions/recent.pdf', 'recent');
        $this->artisan('app:prune-temporary-uploads')->assertSuccessful();
        $disk->assertMissing('temp-submissions/old.pdf');
        $disk->assertExists('temp-submissions/recent.pdf');
    }

    public function test_health_detects_unprocessed_queue(): void
    {
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subHour()->timestamp, 'created_at' => now()->subHour()->timestamp]);
        $this->artisan('app:health', ['--full' => true])->assertFailed();
    }

    public function test_worker_reports_startup_while_maintenance_keeps_jobs_paused(): void
    {
        $this->mock(MaintenanceMode::class, function ($mock) {
            $mock->shouldReceive('active')->andReturn(true);
        });
        Cache::forget('health:queue');
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 1])->assertSuccessful();

        $this->assertGreaterThanOrEqual(now()->subSeconds(5)->timestamp, Cache::get('health:queue', 0));
        $this->assertDatabaseHas('jobs', ['attempts' => 0, 'reserved_at' => null]);
    }

    public function test_legacy_backslash_paths_cannot_delete_another_responses_attachment(): void
    {
        Storage::fake('private');
        $form = Form::factory()->create();
        $category = $form->categories()->create(['name' => 'Files', 'order' => 1]);
        $field = $category->fields()->create(['form_id' => $form->id, 'label' => 'File', 'type' => 'file', 'order' => 1]);
        $victim = Submission::factory()->submitted()->create(['form_id' => $form->id]);
        $attacker = Submission::factory()->create(['form_id' => $form->id, 'status' => 'draft']);
        $path = 'submissions/'.$victim->id.'/private.pdf';
        Storage::disk('private')->put($path, 'private');
        $attacker->values()->create(['form_field_id' => $field->id, 'value' => 'submissions/'.$attacker->id.'/..'.chr(92).$victim->id.chr(92).'private.pdf']);
        $attacker->delete();
        Storage::disk('private')->assertExists($path);
    }
}
