<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormAccessLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormAccessMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_access_link_for_another_form_never_grants_session_access(): void
    {
        $owner = User::factory()->create();
        $target = Form::factory()->published()->create([
            'user_id' => $owner->id,
            'visibility' => 'private',
        ]);
        $other = Form::factory()->published()->create([
            'user_id' => $owner->id,
            'visibility' => 'private',
        ]);
        $link = FormAccessLink::create([
            'form_id' => $other->id,
            'token' => Str::random(32),
        ]);

        $this->withSession([
            'form_access_'.$target->id => [
                'token' => $link->token,
                'expires_at' => null,
            ],
        ])->get(route('submissions.create', $target))
            ->assertRedirect(route('homepage'));
    }
}
