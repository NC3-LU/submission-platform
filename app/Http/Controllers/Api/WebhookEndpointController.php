<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookDeliveryResource;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\ApiToken;
use App\Models\Form;
use App\Models\IntegrationEvent;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDestination;
use App\Services\Webhooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookEndpointController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $token = ApiToken::fromRequest($request);
        $data = $request->validate(['per_page' => 'sometimes|integer|min:1|max:100']);

        return WebhookEndpointResource::collection(WebhookEndpoint::where('user_id', $token->user_id)
            ->whereHas('form', fn ($query) => $query->when(! $token->user->isAdmin(), fn ($query) => $query->where('user_id', $token->user_id)))
            ->latest()->paginate($data['per_page'] ?? 25));
    }

    public function store(Request $request, WebhookDestination $destination): JsonResponse
    {
        $this->enabled();
        $token = ApiToken::fromRequest($request);
        $data = $request->validate(['form_id' => 'required|integer|min:1', ...$this->rules()]);
        $form = Form::find($data['form_id']);
        abort_unless($form && Gate::forUser($token->user)->allows('manageCollaborators', $form), 404);
        $data['url'] = $destination->resolve($data['url'])['url'];
        $secret = Str::random(64);
        $endpoint = DB::transaction(function () use ($data, $token, $secret, $form) {
            User::whereKey($token->user_id)->lockForUpdate()->firstOrFail();
            $form = Form::whereKey($form->id)->lockForUpdate()->firstOrFail();
            abort_unless(Gate::forUser($token->user)->allows('manageCollaborators', $form), 404);
            abort_if(WebhookEndpoint::where('user_id', $token->user_id)->count() >= 10 || WebhookEndpoint::where('form_id', $form->id)->count() >= 5, 429, 'Webhook endpoint quota reached.');
            $endpoint = WebhookEndpoint::create([...$data, 'user_id' => $token->user_id, 'secret' => $secret]);
            IntegrationEvent::record('webhook.created', $form->id, $endpoint->id, $token->user_id, $token->id);

            return $endpoint;
        });

        return response()->json(['data' => new WebhookEndpointResource($endpoint), 'secret' => $secret], 201)->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, string $endpoint): WebhookEndpointResource
    {
        return new WebhookEndpointResource($this->owned($request, $endpoint));
    }

    public function update(Request $request, string $endpoint, WebhookDestination $destination): WebhookEndpointResource
    {
        $this->owned($request, $endpoint);
        $data = $request->validate($this->rules(true));
        if (isset($data['url'])) {
            $data['url'] = $destination->resolve($data['url'])['url'];
        }

        return DB::transaction(function () use ($request, $endpoint, $data) {
            $record = $this->owned($request, $endpoint, true);
            $cancel = (isset($data['url']) && $data['url'] !== $record->url) || (isset($data['enabled']) && ! $data['enabled']);
            $record->update($data);
            if ($cancel) {
                $record->deliveries()->whereIn('status', ['pending', 'retrying'])->update(['status' => 'canceled', 'error_code' => 'endpoint_changed', 'next_attempt_at' => null]);
            }
            $this->audit($request, $record, 'webhook.updated');

            return new WebhookEndpointResource($record);
        });
    }

    public function destroy(Request $request, string $endpoint): Response
    {
        DB::transaction(function () use ($request, $endpoint) {
            $record = $this->owned($request, $endpoint, true);
            $this->audit($request, $record, 'webhook.deleted');
            $record->delete();
        });

        return response()->noContent();
    }

    public function rotate(Request $request, string $endpoint): JsonResponse
    {
        $secret = Str::random(64);
        DB::transaction(function () use ($request, $endpoint, $secret) {
            $record = $this->owned($request, $endpoint, true);
            $record->update(['secret' => $secret]);
            $this->audit($request, $record, 'webhook.secret_rotated');
        });

        return response()->json(['secret' => $secret])->header('Cache-Control', 'no-store, private');
    }

    public function deliveries(Request $request, string $endpoint): AnonymousResourceCollection
    {
        $record = $this->owned($request, $endpoint);
        $data = $request->validate(['per_page' => 'sometimes|integer|min:1|max:100']);

        return WebhookDeliveryResource::collection($record->deliveries()->latest('id')->paginate($data['per_page'] ?? 25));
    }

    public function test(Request $request, string $endpoint, Webhooks $webhooks): JsonResponse
    {
        $this->enabled();
        $record = $this->owned($request, $endpoint);
        abort_unless($record->enabled, 409, 'Enable this endpoint before testing.');
        $delivery = $webhooks->enqueue($record, 'webhook.test', ['form_id' => $record->form_id]);
        abort_unless($delivery, 429, 'Webhook delivery quota reached.');
        $this->audit($request, $record, 'webhook.test_requested');

        return (new WebhookDeliveryResource($delivery))->response()->setStatusCode(202);
    }

    public function redeliver(Request $request, string $endpoint, string $delivery, Webhooks $webhooks): JsonResponse
    {
        $this->enabled();
        $record = $this->owned($request, $endpoint);
        abort_unless($record->enabled, 409, 'Enable this endpoint before redelivery.');
        $result = DB::transaction(function () use ($request, $record, $delivery, $webhooks) {
            $delivery = $record->deliveries()->whereKey($delivery)->lockForUpdate()->firstOrFail();
            abort_unless($delivery->status === 'failed' && $delivery->max_attempts === 5 && $delivery->created_at->gt(now()->subDay()), 409, 'This delivery cannot be retried.');
            $delivery->update(['status' => 'pending', 'max_attempts' => 10, 'next_attempt_at' => now(), 'error_code' => null]);
            $this->audit($request, $record, 'webhook.redelivery_requested');
            $webhooks->dispatch($delivery);

            return $delivery;
        });

        return (new WebhookDeliveryResource($result))->response()->setStatusCode(202);
    }

    private function owned(Request $request, string $id, bool $lock = false): WebhookEndpoint
    {
        $token = ApiToken::fromRequest($request);
        $record = WebhookEndpoint::where('user_id', $token->user_id)->whereKey($id)->when($lock, fn ($query) => $query->lockForUpdate())->firstOrFail();
        abort_unless($record->form && Gate::forUser($token->user)->allows('manageCollaborators', $record->form), 404);

        return $record;
    }

    private function rules(bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';

        return ['url' => [$required, 'string', 'max:2048'], 'events' => [$required, 'array', 'min:1', 'max:3'],
            'events.*' => ['string', 'distinct', Rule::in(WebhookEndpoint::EVENTS)], 'enabled' => ['sometimes', 'boolean']];
    }

    private function enabled(): void
    {
        $connection = config('integrations.queue_connection');
        abort_unless(config('integrations.webhooks.enabled'), 503, 'Webhooks are not enabled.');
        abort_if(in_array(config("queue.connections.$connection.driver"), ['sync', 'null', 'deferred', 'background', null], true), 503, 'An asynchronous integration queue is required.');
    }

    private function audit(Request $request, WebhookEndpoint $endpoint, string $action): void
    {
        $token = ApiToken::fromRequest($request);
        IntegrationEvent::record($action, $endpoint->form_id, $endpoint->id, $token->user_id, $token->id);
    }
}
