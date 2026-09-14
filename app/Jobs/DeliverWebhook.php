<?php

namespace App\Jobs;

use App\Models\IntegrationEvent;
use App\Models\WebhookDelivery;
use App\Services\WebhookDestination;
use App\Services\Webhooks;
use App\Services\WebhookTransport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $deliveryId) {}

    public function handle(): void
    {
        $delivery = DB::transaction(function () {
            $delivery = WebhookDelivery::whereKey($this->deliveryId)->lockForUpdate()->first();
            if (! $delivery || ! in_array($delivery->status, ['pending', 'retrying'], true) || $delivery->next_attempt_at?->isFuture()) {
                return null;
            }
            $endpoint = $delivery->endpoint;
            if (! config('integrations.webhooks.enabled') || ! $endpoint?->enabled || ! app(Webhooks::class)->authorized($endpoint)
                || ($delivery->event_type !== 'webhook.test' && ! in_array($delivery->event_type, $endpoint->events, true))) {
                $delivery->update(['status' => 'canceled', 'error_code' => 'access_revoked', 'next_attempt_at' => null]);

                return null;
            }
            if ($delivery->attempts >= $delivery->max_attempts || $delivery->created_at->lt(now()->subDay())) {
                $delivery->update(['status' => 'failed', 'error_code' => 'retry_limit', 'next_attempt_at' => null]);

                return null;
            }
            foreach (['webhook-deliveries:user:'.$endpoint->user_id => 120, 'webhook-deliveries:endpoint:'.$endpoint->id => 30] as $key => $limit) {
                if (RateLimiter::tooManyAttempts($key, $limit)) {
                    $delivery->update(['status' => 'retrying', 'next_attempt_at' => now()->addMinute()]);

                    return null;
                }
                RateLimiter::hit($key, 60);
            }
            $delivery->update(['status' => 'delivering', 'attempts' => $delivery->attempts + 1]);

            return $delivery;
        });
        if (! $delivery) {
            return;
        }
        try {
            $target = app(WebhookDestination::class)->resolve($delivery->endpoint->url);
            $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $timestamp = (string) now()->timestamp;
            $result = app(WebhookTransport::class)->send($target, $body, [
                'X-Webhook-Delivery' => $delivery->id, 'X-Webhook-Event' => $delivery->event_id,
                'X-Webhook-Timestamp' => $timestamp,
                'X-Webhook-Signature' => 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $delivery->endpoint->secret),
            ]);
        } catch (ValidationException) {
            $result = ['status_code' => null, 'error_code' => 'unsafe_destination'];
        } catch (\Throwable) {
            $result = ['status_code' => null, 'error_code' => 'network_error'];
        }
        DB::transaction(function () use ($delivery, $result) {
            $current = WebhookDelivery::whereKey($delivery->id)->lockForUpdate()->first();
            if (! $current || $current->status !== 'delivering' || $current->attempts !== $delivery->attempts) {
                return;
            }
            $code = $result['status_code'];
            $error = $result['error_code'];
            $success = ! $error && $code >= 200 && $code < 300;
            $retry = ! $success && ! in_array($error, ['unsafe_destination', 'response_too_large'], true)
                && (! $code || $code >= 500 || $code === 429) && $current->attempts < $current->max_attempts;
            $current->update(['status' => $success ? 'delivered' : ($retry ? 'retrying' : 'failed'), 'status_code' => $code,
                'error_code' => $success ? null : ($error ?? 'http_error'),
                'next_attempt_at' => $retry ? now()->addSeconds([60, 300, 1800, 7200][min(3, ($current->attempts - 1) % 5)]) : null]);
            IntegrationEvent::record($success ? 'webhook.delivered' : 'webhook.attempt_failed', $current->endpoint?->form_id, $current->id, $current->user_id,
                metadata: ['attempt' => $current->attempts, 'status_code' => $code, 'error_code' => $current->error_code]);
            if ($retry) {
                app(Webhooks::class)->dispatch($current);
            }
        });
    }

    public function failed(?\Throwable $error): void
    {
        // Recovery on the scheduler retries the durable delivery with its original IDs.
        WebhookDelivery::whereKey($this->deliveryId)->where('status', 'delivering')
            ->update(['status' => 'retrying', 'error_code' => 'worker_unavailable', 'next_attempt_at' => now()->addMinute()]);
    }
}
