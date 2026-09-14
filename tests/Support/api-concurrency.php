<?php

use App\Models\ApiToken;
use App\Models\ApiTokenEvent;
use App\Models\Form;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\ApiTokenService;
use App\Services\Webhooks;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

// Standalone concurrency probe: never target the application database.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'mysql'
    || getenv('DB_HOST') !== '127.0.0.1' || ! in_array(getenv('DB_DATABASE'), ['submission_test', 'submission_api_test'], true)) {
    throw new RuntimeException('Use only the disposable loopback MySQL test database.');
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): never {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
});
config(['integrations.webhooks.enabled' => true]);
Queue::fake();
$waitFor = function (string $path): void {
    $deadline = microtime(true) + 8;
    while (! file_exists($path)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Concurrency barrier timed out.');
        }
        usleep(10000);
    }
};
if (isset($argv[1])) {
    $data = json_decode(file_get_contents($argv[2].'/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
    if ($argv[1] === 'revoke') {
        echo json_encode(app(ApiTokenService::class)->revokeBatch(ApiToken::findOrFail($data['caller']), ['token_ids' => $data['targets']]));
    } elseif ($argv[1] === 'form-first') {
        DB::transaction(function () use ($data, $argv, $waitFor) {
            Form::whereKey($data['form'])->lockForUpdate()->firstOrFail();
            touch($argv[2].'/form-locked');
            $waitFor($argv[2].'/user-locked');
            app(Webhooks::class)->enqueue(WebhookEndpoint::findOrFail($data['endpoint']), 'webhook.test', ['form_id' => $data['form']]);
        });
        echo 'form-first completed';
    } elseif ($argv[1] === 'user-first') {
        DB::transaction(function () use ($data, $argv, $waitFor) {
            User::whereKey($data['user'])->lockForUpdate()->firstOrFail();
            touch($argv[2].'/user-locked');
            $waitFor($argv[2].'/form-locked');
            usleep(100000);
            Form::whereKey($data['form'])->lockForUpdate()->firstOrFail();
        });
        echo 'user-first completed';
    }
    exit;
}
$directory = sys_get_temp_dir().'/submission-api-concurrency-'.Str::uuid();
mkdir($directory, 0700);
$owner = User::factory()->create(['role' => 'internal_evaluator']);
$form = Form::factory()->create(['user_id' => $owner->id]);
$endpoint = WebhookEndpoint::create(['user_id' => $owner->id, 'form_id' => $form->id, 'url' => 'https://receiver.example.com/events', 'secret' => Str::random(64), 'events' => ['submission.created']]);
$makeToken = fn () => ApiToken::create(['user_id' => $owner->id, 'name' => 'Synthetic concurrency', 'token' => hash('sha256', Str::random(48)), 'abilities' => ['tokens:manage']]);
$caller = $makeToken();
$targets = [$makeToken()->id, $makeToken()->id];
file_put_contents($directory.'/fixture.json', json_encode(['user' => $owner->id, 'form' => $form->id, 'endpoint' => $endpoint->id, 'caller' => $caller->id, 'targets' => $targets]));
$run = function (array $modes) use ($directory): array {
    $processes = array_map(fn ($mode) => new Process([PHP_BINARY, __FILE__, $mode, $directory], timeout: 15), $modes);
    foreach ($processes as $process) {
        $process->start();
    }
    foreach ($processes as $process) {
        $process->wait();
        if (! $process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput().$process->getOutput());
        }
    }

    return array_map(fn ($process) => $process->getOutput(), $processes);
};
try {
    $results = $run(['revoke', 'revoke']);
    $counts = array_map(fn ($result) => json_decode($result, true)['revoked_count'], $results);
    sort($counts);
    if ($counts !== [0, 2] || ApiTokenEvent::whereIn('target_token_id', $targets)->where('action', 'revoked')->count() !== 2) {
        throw new RuntimeException('Concurrent revocation was not idempotent.');
    }
    echo "Concurrent batch revocation: two deletions and exactly two audit events.\n";
    $run(['form-first', 'user-first']);
    if ($endpoint->deliveries()->count() !== 1) {
        throw new RuntimeException('Concurrent webhook was not queued.');
    }
    echo "Webhook enqueue and account/form mutation: no lock inversion.\n";
} finally {
    $form->delete();
    ApiTokenEvent::where('target_user_id', $owner->id)->delete();
    DB::table('integration_events')->where('actor_user_id', $owner->id)->delete();
    DB::table('webhook_delivery_locks')->where('user_id', $owner->id)->delete();
    $owner->delete();
    File::deleteDirectory($directory);
}
