@props(['formStats'])

<div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
    <div class="flex items-center gap-3 mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center">
            <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Your Forms</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Forms you own or are assigned to</p>
        </div>
    </div>

    @if($formStats->isEmpty())
        <div class="text-center py-8">
            <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <p class="text-gray-500 dark:text-gray-400">You don't have any forms yet.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($formStats as $stat)
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 border-l-4 border-l-sky-500 p-5 hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start mb-3">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">{{ $stat['form']->title }}</h4>
                        @if($stat['form']->user_id === Auth::id())
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400">Owner</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">Assignee</span>
                        @endif
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 mb-4">{{ Str::limit($stat['form']->description, 100) }}</p>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <x-statistics-card label="Drafts" :value="$stat['draft_count']" />
                        <x-statistics-card label="Submitted" :value="$stat['submitted_count']" />
                    </div>

                    <div class="flex items-center justify-end gap-4 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <a href="{{ route('submissions.index', $stat['form']) }}"
                           class="text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 text-sm font-medium transition-colors">
                            View Submissions
                        </a>
                        @if($stat['form']->user_id === Auth::id())
                            <a href="{{ route('forms.edit', $stat['form']) }}"
                               class="text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 text-sm font-medium transition-colors">
                                Edit Form
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
