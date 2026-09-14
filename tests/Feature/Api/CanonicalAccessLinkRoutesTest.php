<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class CanonicalAccessLinkRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_and_legacy_routes_share_behavior_and_only_legacy_is_deprecated(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->create(['user_id' => $user->id]);
        $secret = Str::random(48);
        ApiToken::create(['user_id' => $user->id, 'name' => 'Manager', 'token' => hash('sha256', $secret), 'abilities' => ['forms:read', 'forms:update']]);
        $canonical = '/api/v1/forms/'.$form->id.'/access-links';
        $legacy = '/api/v1/form/'.$form->id.'/access-links';
        $created = $this->withToken($secret)->postJson($canonical)->assertCreated()->assertHeaderMissing('Sunset');
        $id = $created->json('data.id');
        $this->withToken($secret)->getJson($legacy)->assertOk()->assertHeader('Deprecation')
            ->assertHeader('Sunset', 'Thu, 01 Apr 2027 00:00:00 GMT')->assertJsonPath('data.0.id', $id);
        $this->withToken($secret)->getJson($legacy.'/'.$id)->assertOk()->assertHeader('Link', '<'.url($canonical.'/'.$id).'>; rel="successor-version"');
        $this->withToken($secret)->getJson($canonical.'/'.$id)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame(url($canonical), route('api.forms.access-links.index', $form));
        $names = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter();
        $this->assertSame($names->count(), $names->unique()->count());
    }

    public function test_both_routes_enforce_abilities_owner_permissions_and_parent_binding(): void
    {
        $owner = User::factory()->create();
        $form = Form::factory()->create(['user_id' => $owner->id]);
        $otherForm = Form::factory()->create();
        $link = $otherForm->accessLinks()->create(['token' => Str::random(32)]);
        $secret = Str::random(48);
        $token = ApiToken::create(['user_id' => $owner->id, 'name' => 'Scoped', 'token' => hash('sha256', $secret), 'abilities' => ['forms:read']]);
        foreach (['forms', 'form'] as $prefix) {
            $this->withToken($secret)->postJson("/api/v1/{$prefix}/{$form->id}/access-links")->assertForbidden();
            $this->withToken($secret)->getJson("/api/v1/{$prefix}/{$form->id}/access-links/{$link->id}")->assertNotFound();
            $this->withToken($secret)->getJson("/api/v1/{$prefix}/{$otherForm->id}/access-links")->assertForbidden();
        }
        $token->update(['abilities' => ['forms:update']]);
        foreach (['forms', 'form'] as $prefix) {
            $this->withToken($secret)->deleteJson("/api/v1/{$prefix}/{$form->id}/access-links/{$link->id}")->assertNotFound();
        }
        $this->assertModelExists($link);
    }
}
