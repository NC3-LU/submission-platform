<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Workflow Management') }} - {{ $form->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-xl">
                <div class="p-6">
                    {{-- Workflow Manager Livewire Component --}}
                    @livewire('workflow-manager', ['form' => $form])
                </div>
            </div>

            {{-- Display current workflow if it exists --}}
            @if($workflow)
                <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow rounded-xl p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Current Workflow
                        </h3>

                        <form action="{{ route('workflows.destroy', [$form, $workflow]) }}"
                              method="POST"
                              onsubmit="return confirm('Are you sure you want to delete this workflow?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
                                Delete Workflow
                            </button>
                        </form>
                    </div>

                    <div class="flow-root">
                        <ul role="list" class="-mb-8">
                            @foreach($workflow->steps as $step)
                                <li>
                                    <div class="relative pb-8">
                                        @if(!$loop->last)
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                                        @endif
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full bg-sky-100 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400 flex items-center justify-center ring-8 ring-white dark:ring-gray-800">
                                                    {{ $loop->iteration }}
                                                </span>
                                            </div>
                                            <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                                <div>
                                                    <p class="text-sm text-gray-900 dark:text-gray-100">{{ $step->name }}</p>
                                                    @if($step->description)
                                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $step->description }}</p>
                                                    @endif

                                                    {{-- Display Assignees --}}
                                                    @if($step->assignments->isNotEmpty())
                                                        <div class="mt-2">
                                                            <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400">Assignees:</h4>
                                                            <div class="mt-1 flex flex-wrap gap-2">
                                                                @foreach($step->assignments as $assignment)
                                                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                                                        {{ $assignment->role === 'reviewer' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                                                                           ($assignment->role === 'approver' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                                                            'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200') }}">
                                                                        {{ $assignment->user->name }} ({{ ucfirst($assignment->role) }})
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                                    <form action="{{ route('workflows.steps.destroy', [$form, $step]) }}"
                                                          method="POST"
                                                          class="inline-block"
                                                          onsubmit="return confirm('Are you sure you want to delete this step?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
