<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            My Forms
        </h2>
    </x-slot>

    <!-- Container -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">

        <!-- Create New Form Button -->
        <div class="flex justify-between items-center mb-6">
            <a href="{{ route('forms.create') }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition duration-200">
                Create New Form
            </a>
        </div>

        <!-- Content -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            @if($forms->isEmpty())
                <p class="text-gray-600 dark:text-gray-300">You have not created any forms yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                Title
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                Role
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                Visibility
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                Created At
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($forms as $form)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $form->title }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    @if($form->user_id === Auth::id())
                                        <span class="px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">Owner</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Assignee</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 capitalize">
                                    {{ $form->status }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 capitalize">
                                    {{ $form->visibility }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ $form->created_at->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                      @if($form->user_id === Auth::id() || $form->appointedUsers()->where('user_id', Auth::id())->where('can_edit', true)->exists())
                                        <a href="{{ route('forms.edit', $form) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                            Edit
                                        </a>
                                        <span class="text-gray-300 dark:text-gray-500 mx-1">|</span>
                                    @endif
                                    <a href="{{ route('submissions.index', $form) }}" class="text-green-600 dark:text-green-400 hover:underline">
                                        Submissions
                                    </a>
                                    @if($form->user_id === Auth::id())
                                        <span class="text-gray-300 dark:text-gray-500 mx-1">|</span>
                                        <form action="{{ route('forms.destroy', $form) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                    @if($form->status === 'published')
                                        <span class="text-gray-300 dark:text-gray-500 mx-1">|</span>
                                        <a href="{{ route('forms.preview', $form) }}" class="text-sky-600 dark:text-sky-400 hover:underline">
                                            Preview
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
