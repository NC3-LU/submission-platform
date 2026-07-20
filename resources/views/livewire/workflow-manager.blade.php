<div class="space-y-6">
    <!-- Workflow Creation/Configuration -->
    @if(!$workflow)
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">Create Workflow</h3>

            <form wire:submit="createWorkflow" class="space-y-4">
                <div>
                    <label for="workflowName" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Workflow Name</label>
                    <input type="text" id="workflowName" wire:model="workflowName"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
                    @error('workflowName') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="workflowDescription" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea id="workflowDescription" wire:model="workflowDescription" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-sky-500 sm:text-sm"></textarea>
                    @error('workflowDescription') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                        Create Workflow
                    </button>
                </div>
            </form>
        </div>
    @else
        <!-- Existing Workflow Management -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg divide-y divide-gray-200 dark:divide-gray-700">
            <!-- Workflow Header -->
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $workflow->name }}</h3>
                        @if($workflow->description)
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $workflow->description }}</p>
                        @endif
                    </div>
                    <button wire:click="showAddStep" type="button"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add Step
                    </button>
                </div>
            </div>

            <!-- Workflow Steps -->
            <div class="px-6 py-4">
                <div wire:sortable="stepOrderUpdated" wire:sortable.options="{ animation: 150 }" class="space-y-3">
                    @foreach($steps as $index => $step)
                        <div wire:key="step-{{ $step['id'] }}" wire:sortable.item="{{ $step['id'] }}"
                             class="flex items-center space-x-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg relative group">

                            <!-- Drag Handle -->
                            <div wire:sortable.handle class="flex-shrink-0 cursor-move text-gray-400 hover:text-gray-500">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M3 7a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 13a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>

                            <!-- Step Number -->
                            <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-sky-100 dark:bg-sky-900">
                                <span class="text-sm font-medium text-sky-800 dark:text-sky-200">{{ $index + 1 }}</span>
                            </div>

                            <!-- Step Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                        {{ $step['name'] }}
                                    </p>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $step['type'] === 'review' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                                           ($step['type'] === 'approval' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                           ($step['type'] === 'notification' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' :
                                            'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200')) }}">
                                        {{ ucfirst($step['type']) }}
                                    </span>
                                </div>

                                <!-- Assignees -->
                                @if(!empty($step['assignments']))
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($step['assignments'] as $assignment)
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs
                                                {{ $assignment['role'] === 'reviewer' ? 'bg-blue-50 text-blue-700 dark:bg-blue-900 dark:text-blue-200' :
                                                   ($assignment['role'] === 'approver' ? 'bg-green-50 text-green-700 dark:bg-green-900 dark:text-green-200' :
                                                    'bg-gray-50 text-gray-700 dark:bg-gray-900 dark:text-gray-200') }}">
                                                {{ $assignment['user']['name'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <!-- Actions -->
                            <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="editStep({{ $step['id'] }})" type="button"
                                        class="text-sky-600 hover:text-sky-900 dark:text-sky-400 dark:hover:text-sky-300">
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if(empty($steps))
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No steps</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new step.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Step Modal -->
        <x-dialog-modal wire:model.live="showNewStepModal">
            <x-slot name="title">
                {{ $editingStep ? 'Edit Step' : 'Add New Step' }}
            </x-slot>

            <x-slot name="content">
                <div class="space-y-4">
                    <div>
                        <label for="step-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                        <input type="text" id="step-name" wire:model="newStep.name"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        @error('newStep.name') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="step-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                        <select id="step-type" wire:model="newStep.type"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            <option value="">Select type...</option>
                            <option value="review">Review</option>
                            <option value="approval">Approval</option>
                            <option value="notification">Notification</option>
                            <option value="automated">Automated</option>
                        </select>
                        @error('newStep.type') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="step-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea id="step-description" wire:model="newStep.description" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"></textarea>
                    </div>

                    <!-- Assignee Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Assignees</label>
                        <div class="space-y-2">
                            @foreach($this->availableUsers as $user)
                                <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded-md">
                                    <span class="text-sm text-gray-900 dark:text-gray-100">{{ $user->name }}</span>
                                    <select wire:model="newStep.assignees.{{ $user->id }}.role"
                                            class="ml-2 text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                                        <option value="">Not assigned</option>
                                        <option value="reviewer">Reviewer</option>
                                        <option value="approver">Approver</option>
                                        <option value="observer">Observer</option>
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-slot>

            <x-slot name="footer">
                <div class="flex justify-end space-x-3">
                    <x-secondary-button wire:click="$set('showNewStepModal', false)">
                        Cancel
                    </x-secondary-button>
                    <x-button wire:click="{{ $editingStep ? 'updateStep' : 'addStep' }}">
                        {{ $editingStep ? 'Update' : 'Add' }} Step
                    </x-button>
                </div>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
@endpush
