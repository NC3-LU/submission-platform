@props(['scanResult' => null])

@php
    // Resolve the effective status. Prefer the explicit `status` column, but
    // stay backward compatible with the legacy `is_malicious` boolean and
    // treat a missing scan result as still-queued (pending).
    if (isset($scanResult) && !empty($scanResult->status)) {
        $status = $scanResult->status;
    } elseif (isset($scanResult) && isset($scanResult->is_malicious)) {
        $status = $scanResult->is_malicious ? 'malicious' : 'clean';
    } else {
        $status = 'pending';
    }

    $config = match ($status) {
        'clean' => [
            'label' => 'Scanned — clean',
            'classes' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
            'dot' => 'text-emerald-500',
        ],
        'malicious' => [
            'label' => 'Blocked — malware detected',
            'classes' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            'dot' => 'text-red-500',
        ],
        'failed' => [
            'label' => 'Scan failed',
            'classes' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
            'dot' => 'text-orange-500',
        ],
        default => [
            'label' => 'Awaiting malware scan',
            'classes' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'dot' => 'text-amber-500',
        ],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $config['classes']]) }}
      role="status"
      title="{{ $config['label'] }}">
    <svg class="mr-1.5 h-2 w-2 {{ $config['dot'] }}" fill="currentColor" viewBox="0 0 8 8" aria-hidden="true">
        <circle cx="4" cy="4" r="3" />
    </svg>
    {{ $config['label'] }}
</span>
