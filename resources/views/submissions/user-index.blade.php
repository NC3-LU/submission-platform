{{-- user_index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('My Submissions') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if($submissions->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400">You haven't submitted anything yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Form Title
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Last Activity
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                @foreach($submissions as $submission)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $submission->form->title }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs rounded-full
                                                @if($submission->status === 'draft') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                                @elseif($submission->status === 'ongoing') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                                @elseif($submission->status === 'submitted') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                @elseif($submission->status === 'under_review') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                                @elseif($submission->status === 'completed') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                                                @endif">
                                                {{ ucfirst($submission->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $submission->updated_at->diffForHumans() }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            @if($submission->status === 'draft')
                                                <a href="{{ route('submissions.edit', ['form' => $submission->form, 'submission' => $submission]) }}"
                                                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm">
                                                    Continue
                                                </a>
                                                <form action="{{ route('submissions.destroy', $submission) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm"
                                                            onclick="return confirm('Are you sure you want to delete this draft? This action cannot be undone.')">
                                                        Delete
                                                    </button>
                                                </form>
                                            @elseif($submission->status === 'ongoing')
                                                <a href="{{ route('submissions.edit', ['form' => $submission->form, 'submission' => $submission]) }}"
                                                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm">
                                                    Continue
                                                </a>
                                            @else
                                                <a href="{{ route('submissions.show', ['form' => $submission->form, 'submission' => $submission]) }}"
                                                   class="text-sky-600 hover:text-sky-900 dark:text-sky-400 dark:hover:text-sky-300">
                                                    View
                                                </a>
                                            @endif

                                            @can('export', $submission)
                                                <a href="{{ route('submissions.export.single.pdf', ['form' => $submission->form, 'submission' => $submission]) }}"
                                                   class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                                                    Export PDF
                                                </a>
                                                <a href="{{ route('submissions.export.single.json', ['form' => $submission->form, 'submission' => $submission]) }}"
                                                   class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                    Export JSON
                                                </a>
                                            @endcan
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
    </div>
</x-app-layout>
