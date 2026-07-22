<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
