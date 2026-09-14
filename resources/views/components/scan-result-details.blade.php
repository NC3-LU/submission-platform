@props(['scanResult' => null])

@php
    $report = \App\Services\ScanReport::redact($scanResult?->scan_results ?? []);
    $workers = $report['workers'] ?? [];
@endphp
<details class="mt-3 text-sm text-gray-700 dark:text-gray-200">
    <summary class="cursor-pointer rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-600">View scan details</summary>
    <div class="mt-2 space-y-3 border-l-2 border-gray-300 pl-3 dark:border-gray-600">
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
            <dt>Scanner</dt><dd>{{ $scanResult?->scanner_used ?? 'pandora' }}</dd>
            <dt>Status</dt><dd>{{ $scanResult?->status ?? 'pending' }}</dd>
            @if($scanResult?->updated_at)
                <dt>Last updated</dt><dd><time datetime="{{ $scanResult->updated_at->toIso8601String() }}">{{ $scanResult->updated_at->format('Y-m-d H:i T') }}</time></dd>
            @endif
        </dl>
        @forelse($workers as $name => $worker)
            <div>
                <p class="font-semibold break-words">{{ $name }} — {{ is_array($worker) ? ($worker['status'] ?? 'Unknown') : $worker }}</p>
                @if(is_array($worker) && !empty($worker['details']))
                    <pre class="mt-1 max-h-96 overflow-auto whitespace-pre-wrap break-words rounded bg-white p-3 text-xs dark:bg-gray-800">{{ json_encode($worker['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre>
                @endif
            </div>
        @empty
            <p>{{ (!$scanResult || $scanResult->status === 'pending') ? 'Scan has not completed yet.' : 'Worker details were not stored for this scan.' }}</p>
            @if(!empty($report['workersStatus']))
                <pre class="whitespace-pre-wrap break-words text-xs">{{ json_encode($report['workersStatus'], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre>
            @endif
        @endforelse
    </div>
</details>
