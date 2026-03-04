<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Submission #') . $submission->id }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">{{ $form->title }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                        Submitted on: {{ $submission->created_at->format('Y-m-d H:i:s') }}
                    </p>

                    @foreach($categories as $category)
                        <div class="mb-10">
                            <h3 class="text-xl font-bold mb-4">{{ $category['name'] }}</h3>
                            @if($category['description'])
                                <p class="text-gray-600 dark:text-gray-400 mb-6">{{ $category['description'] }}</p>
                            @endif

                            @foreach($category['fields'] as $field)
                                <div class="mb-6">
                                    <h4 class="text-md font-medium text-gray-700 dark:text-gray-300">{{ $field['label'] }}</h4>
                                    @if($field['type'] === 'file')
                                        @if($field['displayValue'])
                                            <div class="flex items-center space-x-2">
                                                <a href="{{ $field['displayValue'] }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                    {{ basename($field['value']) }}
                                                </a>
                                                @if(isset($field['scanResult']))
                                                    <x-scan-result-badge :scanResult="$field['scanResult']" />
                                                @else 
                                                    <x-scan-result-badge :scanResult="null" />
                                                @endif
                                            </div>
                                        @else
                                            <p class="text-gray-500 dark:text-gray-400">No file uploaded</p>
                                        @endif
                                    @elseif($field['type'] === 'checkbox')
                                        <p class="mt-1">{{ $field['displayValue'] ?: 'No' }}</p>
                                    @elseif($field['type'] === 'radio' || $field['type'] === 'select')
                                        <p class="mt-1">{{ $field['displayValue'] ?: 'Not selected' }}</p>
                                    @elseif($field['type'] === 'textarea')
                                        <pre class="mt-1 whitespace-pre-wrap">{{ $field['displayValue'] ?: 'N/A' }}</pre>
                                    @else
                                        <p class="mt-1">{{ $field['displayValue'] ?: ''}}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    <div class="mt-8">
                        <a href="{{ $backLink }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Back to Submissions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
