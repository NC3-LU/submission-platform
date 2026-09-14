<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Form;
use App\Models\IntegrationEvent;
use App\Models\Submission;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class Webhooks
{
    public function submissionChanged(Submission $submission, bool $created): void
    {
        if (! config('integrations.webhooks.enabled') || ! in_array($submission->status, Submission::REVIEW_STATUSES, true)) {
            return;
        }
        $previous = $created ? null : $submission->getRawOriginal('status');
        if (! $created && ! $submission->wasChanged('status')) {
            return;
        }
        $type = $created || in_array($previous, Submission::EDITABLE_STATUSES, true) ? 'submission.created' : 'submission.status_changed';
        $this->emit($submission->form_id, $type, ['form_id' => $submission->form_id, 'submission_id' => $submission->id, 'status' => $submission->status, 'previous_status' => $previous]);
    }

    public function formChanged(Form $form): void
    {
        if (config('integrations.webhooks.enabled') && $form->wasChanged('status')) {
            $this->emit($form->id, 'form.status_changed', ['form_id' => $form->id, 'status' => $form->status, 'previous_status' => $form->getRawOriginal('status')]);
        }
    }

    public function emit(int $formId, string $type, array $data): void
    {
        $eventId = (string) Str::uuid7();
        foreach (WebhookEndpoint::where('form_id', $formId)->where('enabled', true)->get() as $endpoint) {
            if (in_array($type, $endpoint->events, true) && $this->authorized($endpoint)) {
                $this->enqueue($endpoint, $type, $data, $eventId);
            }
        }
    }

    public function authorized(WebhookEndpoint $endpoint): bool
    {
        return $endpoint->user && $endpoint->form && Gate::forUser($endpoint->user)->allows('manageCollaborators', $endpoint->form);
    }

    public function enqueue(WebhookEndpoint $endpoint, string $type, array $data, ?string $eventId = null): ?WebhookDelivery
    {
        if (! config('integrations.webhooks.enabled') || ! $endpoint->enabled || ! $this->authorized($endpoint)) {
            return null;
        }

        return DB::transaction(function () use ($endpoint, $type, $data, $eventId) {
            // Atomic upsert takes the quota row's write lock until commit. Do not lock
            // users here: submissions may already hold a form lock in the outer transaction.
            DB::table('webhook_delivery_locks')->upsert(
                [['user_id' => $endpoint->user_id, 'updated_at' => now()]], ['user_id'], ['updated_at']
            );
            $base = WebhookDelivery::where('user_id', $endpoint->user_id);
            if ((clone $base)->whereIn('status', ['pending', 'retrying', 'delivering'])->count() >= 200
                || (clone $base)->where('created_at', '>=', now()->subDay())->count() >= 1000) {
                if (Cache::add('webhook-quota:'.$endpoint->user_id, true, 3600)) {
                    IntegrationEvent::record('webhook.quota_exceeded', $endpoint->form_id, $endpoint->id, $endpoint->user_id);
                }

                return null;
            }
            $eventId ??= (string) Str::uuid7();
            $delivery = WebhookDelivery::firstOrCreate(['webhook_endpoint_id' => $endpoint->id, 'event_id' => $eventId], [
                'user_id' => $endpoint->user_id, 'event_type' => $type, 'status' => 'pending', 'next_attempt_at' => now(),
                'payload' => ['id' => $eventId, 'type' => $type, 'created_at' => now()->toIso8601String(), 'data' => $data],
            ]);
            if ($delivery->wasRecentlyCreated) {
                IntegrationEvent::record('webhook.queued', $endpoint->form_id, $delivery->id, $endpoint->user_id, metadata: ['event_type' => $type]);
                $this->dispatch($delivery);
            }

            return $delivery;
        });
    }

    public function dispatch(WebhookDelivery $delivery): void
    {
        $connection = config('integrations.queue_connection');
        if (in_array(config("queue.connections.$connection.driver"), ['sync', 'null', 'deferred', 'background', null], true)) {
            return;
        }
        DeliverWebhook::dispatch($delivery->id)->onConnection($connection)->delay($delivery->next_attempt_at)->afterCommit();
    }

    public function recoverAndPrune(): void
    {
        // A killed worker may have delivered before losing its acknowledgement. Retry the same ID.
        WebhookDelivery::where('status', 'delivering')->where('updated_at', '<', now()->subMinutes(5))
            ->update(['status' => 'retrying', 'next_attempt_at' => now(), 'updated_at' => now()]);
        if (config('integrations.webhooks.enabled')) {
            WebhookDelivery::whereIn('status', ['pending', 'retrying'])->where('next_attempt_at', '<=', now())->orderBy('next_attempt_at')->limit(200)->get()
                ->each(fn ($delivery) => $this->dispatch($delivery));
        }
        WebhookDelivery::where('created_at', '<', now()->subDays(7))->delete();
    }
}
