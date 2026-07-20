<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ $backLink }}" class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Submission') }} #{{ $submission->id }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $form->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                <div class="flex items-center justify-between mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $form->title }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Submitted on {{ $submission->created_at->format('M d, Y \a\t g:i A') }}
                        </p>
                    </div>
                </div>

                <div class="space-y-8">
                    @foreach($categories as $category)
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">{{ $category['name'] }}</h4>
                            @if($category['description'])
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ $category['description'] }}</p>
                            @endif

                            <div class="space-y-4">
                                @foreach($category['fields'] as $field)
                                    <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                        <h5 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">{{ $field['label'] }}</h5>
                                        @if($field['type'] === 'file')
                                            @if($field['displayValue'])
                                                @php
                                                    $scanResult = $field['scanResult'] ?? null;
                                                    $scanStatus = $scanResult->status ?? 'pending';
                                                    // The controller returns 423 for any non-clean file when
                                                    // malicious blocking is enabled, so the download is unavailable.
                                                    $downloadBlocked = config('services.pandora.enabled')
                                                        && config('services.pandora.block_malicious')
                                                        && $scanStatus !== 'clean';
                                                @endphp
                                                <div class="flex items-center gap-2">
                                                    @if($downloadBlocked)
                                                        <span class="text-gray-400 dark:text-gray-500 font-medium cursor-not-allowed line-through" title="This file cannot be downloaded until it passes the malware scan.">
                                                            {{ basename($field['value']) }}
                                                        </span>
                                                    @else
                                                        <a href="{{ $field['displayValue'] }}" target="_blank" class="text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300 font-medium transition-colors">
                                                            {{ basename($field['value']) }}
                                                        </a>
                                                    @endif
                                                    @if(config('services.pandora.enabled'))
                                                        <x-scan-result-badge :scanResult="$scanResult" />
                                                    @endif
                                                </div>
                                            @else
                                                <p class="text-gray-400 dark:text-gray-500 italic">No file uploaded</p>
                                            @endif
                                        @elseif($field['type'] === 'checkbox')
                                            <p class="text-gray-900 dark:text-white">{{ $field['displayValue'] ?: 'No' }}</p>
                                        @elseif($field['type'] === 'radio' || $field['type'] === 'select')
                                            <p class="text-gray-900 dark:text-white">{{ $field['displayValue'] ?: 'Not selected' }}</p>
                                        @elseif($field['type'] === 'textarea')
                                            <pre class="whitespace-pre-wrap text-gray-900 dark:text-white text-sm">{{ $field['displayValue'] ?: 'N/A' }}</pre>
                                        @else
                                            <p class="text-gray-900 dark:text-white">{{ $field['displayValue'] ?: '' }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
