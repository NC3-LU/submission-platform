<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('submissions.user_index') }}" class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Edit Submission') }}
                </h2>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                @if($submission->status === 'draft') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                @elseif($submission->status === 'ongoing') bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400
                @elseif($submission->status === 'submitted') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                @endif">
                {{ ucfirst($submission->status) }}
            </span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                @if($submission->status === 'submitted')
                    <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300 rounded-lg border border-amber-200 dark:border-amber-800">
                        <strong>Note:</strong> This submission has already been submitted. Any changes will be logged.
                    </div>
                @endif

                <livewire:submission-form
                    :form="$submission->form"
                    :submission="$submission"
                    :edit-mode="true" />
            </div>
        </div>
    </div>
</x-app-layout>
