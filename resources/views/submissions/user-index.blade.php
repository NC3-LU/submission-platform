<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('My Submissions') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl overflow-hidden">
                @if($submissions->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400">You haven't submitted anything yet.</p>
                        <a href="{{ route('forms.public_index') }}" class="mt-3 inline-flex items-center text-sm text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 font-medium transition-colors">
                            Browse available forms
                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Form Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Activity</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($submissions as $submission)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $submission->form->title }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            @if($submission->status === 'draft') bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300
                                            @elseif($submission->status === 'ongoing') bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400
                                            @elseif($submission->status === 'submitted') bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400
                                            @elseif($submission->status === 'under_review') bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400
                                            @elseif($submission->status === 'completed') bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $submission->updated_at->diffForHumans() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center gap-3">
                                            @if($submission->status === 'draft')
                                                <a href="{{ route('submissions.edit', ['form' => $submission->form, 'submission' => $submission]) }}"
                                                   class="text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 font-medium transition-colors">
                                                    Continue
                                                </a>
                                                <form action="{{ route('submissions.destroy', $submission) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition-colors"
                                                            onclick="return confirm('Are you sure you want to delete this draft?')">
                                                        Delete
                                                    </button>
                                                </form>
                                            @elseif($submission->status === 'ongoing')
                                                <a href="{{ route('submissions.edit', ['form' => $submission->form, 'submission' => $submission]) }}"
                                                   class="text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 font-medium transition-colors">
                                                    Continue
                                                </a>
                                            @else
                                                <a href="{{ route('submissions.show', ['form' => $submission->form, 'submission' => $submission]) }}"
                                                   class="text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 transition-colors">
                                                    View
                                                </a>
                                            @endif

                                            @can('export', $submission)
                                                <a href="{{ route('submissions.export.single.pdf', ['form' => $submission->form, 'submission' => $submission]) }}"
                                                   class="text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 transition-colors">
                                                    Export PDF
                                                </a>
                                                <a href="{{ route('submissions.export.single.json', ['form' => $submission->form, 'submission' => $submission]) }}"
                                                   class="text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 transition-colors">
                                                    Export JSON
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
