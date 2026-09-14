<?php

namespace Tests\Feature\Api;

use App\Jobs\DeliverWebhook;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDns;
use App\Services\WebhookTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function prepare(): Form
    {
        config(['integrations.webhooks.enabled' => true]);
        Queue::fake();
        $this->mock(WebhookDns::class)->shouldReceive('addresses')->andReturn(['8.8.8.8']);
        $form = Form::factory()->create();
        $this->token($form->user);

        return $form;
    }

    private function token(User $user, array $abilities = ['webhooks:manage']): void
    {
        $secret = Str::random(48);
        ApiToken::create(['user_id' => $user->id, 'name' => 'Webhooks', 'token' => hash('sha256', $secret), 'abilities' => $abilities]);
        $this->withToken($secret);
    }

    private function endpoint(Form $form): array
    {
        $response = $this->postJson('/api/v1/webhook-endpoints', ['form_id' => $form->id, 'url' => 'https://receiver.example.com/events', 'events' => ['submission.created', 'submission.status_changed', 'form.status_changed']])->assertCreated();

        return [$response->json('data.id'), $response->json('secret')];
    }

    public function test_endpoint_management_is_owned_scoped_and_secret_is_shown_once_encrypted_at_rest(): void
    {
        $form = $this->prepare();
        [$id, $secret] = $this->endpoint($form);
        $this->assertGreaterThanOrEqual(32, strlen($secret));
        $this->assertNotSame($secret, DB::table('webhook_endpoints')->where('id', $id)->value('secret'));
        $this->getJson('/api/v1/webhook-endpoints')->assertOk()->assertDontSee($secret)->assertJsonMissingPath('data.0.secret');
        $url = '/api/v1/webhook-endpoints/'.$id;
        $this->patchJson($url, ['enabled' => false])->assertOk()->assertJsonPath('data.enabled', false);
        $newSecret = $this->postJson($url.'/rotate-secret')->assertOk()->json('secret');
        $this->assertNotSame($secret, $newSecret);
        $this->assertSame($newSecret, WebhookEndpoint::find($id)->secret);
        $this->token(User::factory()->create(['role' => 'admin']));
        $this->getJson($url)->assertNotFound();
        $this->postJson($url.'/rotate-secret')->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        $this->token($form->user);
        $this->deleteJson($url)->assertNoContent();
        $this->assertDatabaseMissing('webhook_endpoints', ['id' => $id]);
    }

    public function test_delivery_is_signed_minimal_and_has_stable_ids_on_retries(): void
    {
        $form = $this->prepare();
        [$id, $secret] = $this->endpoint($form);
        $submission = Submission::factory()->submitted()->create(['form_id' => $form->id]);
        $delivery = WebhookDelivery::where('webhook_endpoint_id', $id)->firstOrFail();
        Queue::assertPushed(DeliverWebhook::class);
        $payload = $delivery->payload;
        $this->assertSame($submission->id, $payload['data']['submission_id']);
        $this->assertArrayNotHasKey('values', $payload['data']);
        $this->assertArrayNotHasKey('email', $payload['data']);
        $this->mock(WebhookTransport::class)->shouldReceive('send')->twice()->withArgs(function ($target, $body, $headers) use ($secret, $delivery) {
            $this->assertSame('8.8.8.8', $target['ip']);
            $this->assertSame($delivery->id, $headers['X-Webhook-Delivery']);
            $this->assertSame('v1='.hash_hmac('sha256', $headers['X-Webhook-Timestamp'].'.'.$body, $secret), $headers['X-Webhook-Signature']);

            return true;
        })->andReturn(['status_code' => 503, 'error_code' => null], ['status_code' => 204, 'error_code' => null]);
        (new DeliverWebhook($delivery->id))->handle();
        $this->assertSame('retrying', $delivery->fresh()->status);
        $this->travel(61)->seconds();
        (new DeliverWebhook($delivery->id))->handle();
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertSame($payload, $delivery->fresh()->payload);
        $this->getJson("/api/v1/webhook-endpoints/$id/deliveries")->assertOk()->assertJsonPath('data.0.attempts', 2)->assertJsonMissingPath('data.0.payload')->assertJsonMissingPath('data.0.response_body');
    }

    public function test_test_delivery_and_real_delivery_recheck_dns_and_block_rebinding(): void
    {
        $form = $this->prepare();
        [$id] = $this->endpoint($form);
        $this->postJson("/api/v1/webhook-endpoints/$id/test")->assertAccepted();
        $delivery = WebhookDelivery::firstOrFail();
        $this->mock(WebhookDns::class)->shouldReceive('addresses')->once()->andReturn(['127.0.0.1']);
        $this->mock(WebhookTransport::class)->shouldNotReceive('send');
        (new DeliverWebhook($delivery->id))->handle();
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertSame('unsafe_destination', $delivery->fresh()->error_code);
    }

    public function test_terminal_failures_and_redelivery_are_bounded_and_keep_ids(): void
    {
        $form = $this->prepare();
        [$id] = $this->endpoint($form);
        $this->postJson("/api/v1/webhook-endpoints/$id/test")->assertAccepted();
        $delivery = WebhookDelivery::firstOrFail();
        $this->mock(WebhookTransport::class)->shouldReceive('send')->times(5)->andReturn(['status_code' => 503, 'error_code' => null]);
        for ($i = 0; $i < 5; $i++) {
            (new DeliverWebhook($delivery->id))->handle();
            $this->travel(3)->hours();
        }
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertSame(5, $delivery->fresh()->attempts);
        $this->postJson("/api/v1/webhook-endpoints/$id/deliveries/{$delivery->id}/redeliver")->assertAccepted()->assertJsonPath('data.id', $delivery->id);
        $this->postJson("/api/v1/webhook-endpoints/$id/deliveries/{$delivery->id}/redeliver")->assertStatus(409);
    }

    public function test_events_cover_reviewable_creation_and_status_changes_without_draft_data(): void
    {
        $form = $this->prepare();
        [$id] = $this->endpoint($form);
        $submission = Submission::factory()->create(['form_id' => $form->id, 'status' => 'draft']);
        $this->assertDatabaseCount('webhook_deliveries', 0);
        $submission->update(['status' => 'submitted']);
        $submission->update(['status' => 'approved']);
        $form->update(['status' => $form->status === 'archived' ? 'published' : 'archived']);
        $this->assertSame(['submission.created', 'submission.status_changed', 'form.status_changed'], WebhookDelivery::orderBy('id')->pluck('event_type')->all());
        $this->patchJson("/api/v1/webhook-endpoints/$id", ['enabled' => false])->assertOk();
        $submission->update(['status' => 'rejected']);
        $this->assertDatabaseCount('webhook_deliveries', 3);
    }

    public function test_global_opt_in_ability_policy_and_endpoint_quotas_are_required(): void
    {
        $form = $this->prepare();
        $data = ['form_id' => $form->id, 'url' => 'https://receiver.example.com/events', 'events' => ['submission.created']];
        config(['integrations.webhooks.enabled' => false]);
        $this->postJson('/api/v1/webhook-endpoints', $data)->assertStatus(503);
        config(['integrations.webhooks.enabled' => true]);
        $this->token($form->user, ['forms:read']);
        $this->postJson('/api/v1/webhook-endpoints', $data)->assertForbidden();
        $this->token(User::factory()->create());
        $this->postJson('/api/v1/webhook-endpoints', $data)->assertNotFound();
        $this->token($form->user);
        for ($i = 0; $i < 5; $i++) {
            $this->endpoint($form);
        }
        $this->postJson('/api/v1/webhook-endpoints', $data)->assertStatus(429);
    }

    public function test_permission_revocation_cancels_queued_delivery_and_duplicate_job_is_noop(): void
    {
        $form = $this->prepare();
        [$id] = $this->endpoint($form);
        $this->postJson("/api/v1/webhook-endpoints/$id/test")->assertAccepted();
        $delivery = WebhookDelivery::firstOrFail();
        $form->update(['user_id' => User::factory()->create()->id]);
        $this->mock(WebhookTransport::class)->shouldNotReceive('send');
        (new DeliverWebhook($delivery->id))->handle();
        (new DeliverWebhook($delivery->id))->handle();
        $this->assertSame('canceled', $delivery->fresh()->status);
    }

    public function test_redirect_is_terminal_and_queued_delivery_uses_rotated_secret(): void
    {
        $form = $this->prepare();
        [$id] = $this->endpoint($form);
        $this->postJson("/api/v1/webhook-endpoints/$id/test")->assertAccepted();
        $delivery = WebhookDelivery::firstOrFail();
        $secret = $this->postJson("/api/v1/webhook-endpoints/$id/rotate-secret")->assertOk()->json('secret');
        $this->mock(WebhookTransport::class)->shouldReceive('send')->once()->withArgs(function ($target, $body, $headers) use ($secret) {
            return $headers['X-Webhook-Signature'] === 'v1='.hash_hmac('sha256', $headers['X-Webhook-Timestamp'].'.'.$body, $secret);
        })->andReturn(['status_code' => 302, 'error_code' => null]);
        (new DeliverWebhook($delivery->id))->handle();
        (new DeliverWebhook($delivery->id))->handle();
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
    }

    public function test_submission_rollback_removes_delivery_and_its_audit(): void
    {
        $form = $this->prepare();
        $this->endpoint($form);
        DB::beginTransaction();
        Submission::factory()->submitted()->create(['form_id' => $form->id]);
        $this->assertDatabaseCount('webhook_deliveries', 1);
        DB::rollBack();
        $this->assertDatabaseCount('webhook_deliveries', 0);
        $this->assertSame(0, DB::table('integration_events')->where('action', 'webhook.queued')->count());
    }

    public function test_delivery_quota_and_expired_pending_delivery_are_bounded(): void
    {
        $form = $this->prepare();
        [$id] = $this->endpoint($form);
        $endpoint = WebhookEndpoint::findOrFail($id);
        for ($i = 0; $i < 200; $i++) {
            WebhookDelivery::create(['webhook_endpoint_id' => $id, 'user_id' => $endpoint->user_id, 'event_id' => (string) Str::uuid(), 'event_type' => 'webhook.test', 'payload' => [], 'status' => 'pending', 'next_attempt_at' => now()]);
        }
        $this->postJson("/api/v1/webhook-endpoints/$id/test")->assertStatus(429);
        $this->assertDatabaseCount('webhook_deliveries', 200);
        $delivery = WebhookDelivery::firstOrFail();
        $this->travel(25)->hours();
        $this->mock(WebhookTransport::class)->shouldNotReceive('send');
        (new DeliverWebhook($delivery->id))->handle();
        $this->assertSame('failed', $delivery->fresh()->status);
    }
}
