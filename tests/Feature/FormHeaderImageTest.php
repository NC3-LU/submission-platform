<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormHeaderImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_image_url_accessor_returns_public_url_or_null(): void
    {
        $with = Form::factory()->create(['header_image' => 'form-headers/x.jpg']);
        $without = Form::factory()->create(['header_image' => null]);

        $this->assertSame(Storage::disk('public')->url('form-headers/x.jpg'), $with->header_image_url);
        $this->assertNull($without->header_image_url);
        $this->assertSame(50, $without->header_image_position);
    }

    public function test_non_image_header_upload_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'internal_evaluator']);

        $response = $this->actingAs($user)->post(route('forms.store'), [
            'title' => 'T',
            'visibility' => 'public',
            'categories' => [['name' => 'C', 'description' => null]],
            'header_image' => UploadedFile::fake()->create('evil.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('header_image');
    }

    public function test_oversized_dimension_header_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'internal_evaluator']);

        $response = $this->actingAs($user)->post(route('forms.store'), [
            'title' => 'T',
            'visibility' => 'public',
            'categories' => [['name' => 'C', 'description' => null]],
            'header_image' => UploadedFile::fake()->image('huge.jpg', 7000, 7000),
        ]);

        $response->assertSessionHasErrors('header_image');
    }

    /**
     * UploadedFile::fake()->image() renders a solid black canvas, which
     * ImageColorExtractor deliberately filters out as "near-black" (no
     * usable accent color). Build a real colored image instead so this
     * test exercises genuine auto-extraction rather than that edge case.
     */
    private function coloredFakeImage(string $name, int $width, int $height): UploadedFile
    {
        $gd = imagecreatetruecolor($width, $height);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 210, 40, 40));
        $path = tempnam(sys_get_temp_dir(), 'fhi').'.jpg';
        imagejpeg($gd, $path);
        imagedestroy($gd);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    public function test_create_stores_header_and_auto_extracts_color(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'internal_evaluator']);

        $this->actingAs($user)->post(route('forms.store'), [
            'title' => 'Themed',
            'visibility' => 'public',
            'categories' => [['name' => 'C', 'description' => null]],
            'header_image' => $this->coloredFakeImage('banner.jpg', 800, 400),
            'header_image_position' => 30,
        ])->assertRedirect();

        $form = Form::where('title', 'Themed')->firstOrFail();
        $this->assertNotNull($form->header_image);
        Storage::disk('public')->assertExists($form->header_image);
        $this->assertSame(30, $form->header_image_position);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $form->header_theme_color);
    }

    public function test_explicit_theme_color_overrides_extraction(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'internal_evaluator']);

        $this->actingAs($user)->post(route('forms.store'), [
            'title' => 'Override',
            'visibility' => 'public',
            'categories' => [['name' => 'C', 'description' => null]],
            'header_image' => UploadedFile::fake()->image('banner.jpg', 800, 400),
            'header_theme_color' => '#abcdef',
        ])->assertRedirect();

        $this->assertSame('#abcdef', Form::where('title', 'Override')->firstOrFail()->header_theme_color);
    }

    public function test_update_replaces_image_and_deletes_old_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create([
            'header_image' => 'form-headers/old.jpg',
        ]);
        Storage::disk('public')->put('form-headers/old.jpg', 'x');

        $this->actingAs($user)->put(route('forms.update', $form), [
            'title' => $form->title,
            'status' => 'draft',
            'visibility' => 'public',
            'header_image' => UploadedFile::fake()->image('new.jpg', 400, 200),
        ])->assertRedirect();

        Storage::disk('public')->assertMissing('form-headers/old.jpg');
        $this->assertNotSame('form-headers/old.jpg', $form->fresh()->header_image);
        Storage::disk('public')->assertExists($form->fresh()->header_image);
    }

    public function test_update_can_remove_header_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create(['header_image' => 'form-headers/z.jpg']);
        Storage::disk('public')->put('form-headers/z.jpg', 'x');

        $this->actingAs($user)->put(route('forms.update', $form), [
            'title' => $form->title,
            'status' => 'draft',
            'visibility' => 'public',
            'remove_header_image' => '1',
        ])->assertRedirect();

        Storage::disk('public')->assertMissing('form-headers/z.jpg');
        $this->assertNull($form->fresh()->header_image);
        $this->assertNull($form->fresh()->header_theme_color);
    }

    public function test_update_persists_position_without_new_upload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->withHeaderImage()->create();

        $this->actingAs($user)->put(route('forms.update', $form), [
            'title' => $form->title,
            'status' => 'draft',
            'visibility' => 'public',
            'header_image_position' => 80,
        ])->assertRedirect();

        $this->assertSame(80, $form->fresh()->header_image_position);
    }

    public function test_destroy_deletes_header_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create(['header_image' => 'form-headers/d.jpg']);
        Storage::disk('public')->put('form-headers/d.jpg', 'x');

        $this->actingAs($user)->delete(route('forms.destroy', $form))->assertRedirect();

        Storage::disk('public')->assertMissing('form-headers/d.jpg');
    }

    public function test_duplicate_copies_header_to_new_path(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'internal_evaluator']);
        $form = Form::factory()->for($user)->create(['header_image' => 'form-headers/orig.jpg']);
        Storage::disk('public')->put('form-headers/orig.jpg', 'x');
        $form->categories()->create(['name' => 'C', 'order' => 1]);

        $this->actingAs($user)->post(route('forms.duplicate', $form))->assertRedirect();

        $clone = Form::where('title', $form->title.' (Copy)')->firstOrFail();
        $this->assertNotSame('form-headers/orig.jpg', $clone->header_image);
        Storage::disk('public')->assertExists($clone->header_image);

        // Deleting the original must not break the clone.
        Storage::disk('public')->delete('form-headers/orig.jpg');
        Storage::disk('public')->assertExists($clone->header_image);
    }

    public function test_banner_renders_on_submission_page_when_set(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->published()->public()->withHeaderImage()->create();
        $form->categories()->create(['name' => 'C', 'order' => 1]);

        $response = $this->actingAs($user)->get(route('submissions.create', $form));

        $response->assertOk();
        $response->assertSee('object-position: 50% 60%', false);
        $response->assertSee($form->header_image_url, false);
    }

    public function test_banner_absent_when_no_header_image(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->published()->public()->create(['header_image' => null]);
        $form->categories()->create(['name' => 'C', 'order' => 1]);

        $response = $this->actingAs($user)->get(route('submissions.create', $form));

        $response->assertOk();
        $response->assertDontSee('object-position: 50%', false);
    }

    public function test_edit_page_exposes_header_controls(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->withHeaderImage()->create();

        $response = $this->actingAs($user)->get(route('forms.edit', $form));

        $response->assertOk();
        $response->assertSee('name="header_image"', false);
        $response->assertSee('name="header_image_position"', false);
        $response->assertSee('name="header_theme_color"', false);
        $response->assertSee('name="remove_header_image"', false);
        $response->assertSee('enctype="multipart/form-data"', false);
    }
}
