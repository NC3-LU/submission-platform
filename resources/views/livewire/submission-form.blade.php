<div>
    <!-- Global Error Display -->
    @if ($errors->any())
        <div class="mb-6">
            <div class="bg-red-50 dark:bg-red-900 text-red-800 dark:text-red-200 px-4 py-3 rounded-md shadow-md">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
    <!-- Auto-save notification -->
    <div x-data="{ show: false, message: '' }"
         x-show="show"
         x-on:success.window="message = $event.detail; show = true; setTimeout(() => show = false, 2000)"
         class="fixed top-4 right-4 bg-green-50 text-green-800 px-4 py-2 rounded-md shadow-sm"
         style="display: none;">
        <span x-text="message"></span>
    </div>


    <!-- Progress Navigation -->
    <div class="mb-8">
        <!-- Current Progress Header -->
        <div class="relative mb-8">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg sm:text-xl font-medium text-gray-900 dark:text-gray-100">
                        {{ $this->currentStepData['name'] }}
                    </h2>
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300">
                        {{ $currentStep }}/{{ $totalSteps }}
                    </span>
                </div>
            </div>

            @if($this->currentStepData['description'])
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {!! \App\Helpers\MarkdownHelper::toHtml($this->currentStepData['description']) !!}
                </p>
            @endif
        </div>

        <!-- Progress Track -->
        <div class="relative">
            <div class="overflow-hidden mb-8 w-full h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                <div
                    class="h-2 rounded-full bg-gradient-to-r from-sky-500 to-sky-600 transition-all duration-500 ease-out dark:from-sky-600 dark:to-sky-700"
                    style="width: {{ $totalSteps > 1 ? (($currentStep - 1) / ($totalSteps - 1)) * 100 : ($currentStep == 1 ? 0 : 100) }}%">
                </div>
            </div>

            <!-- Step Indicators -->
            <div class="absolute -top-2 w-full">
                <div class="relative flex justify-between">
                    @foreach($steps as $index => $step)
                        <div class="relative flex flex-col items-center group">
                            <button wire:click="$set('currentStep', {{ $index + 1 }})"
                                    @if($currentStep <= $index + 1) disabled @endif
                                    class="w-6 h-6 rounded-full transition-all duration-300 ease-in-out flex items-center justify-center
                                    {{ $currentStep > $index + 1 ? 'bg-sky-600 hover:bg-sky-700' : '' }}
                                    {{ $currentStep === $index + 1 ? 'ring-4 ring-white dark:ring-gray-800 bg-sky-600 scale-110' : '' }}
                                    {{ $currentStep < $index + 1 ? 'bg-gray-300 dark:bg-gray-600' : '' }}">
                                <span
                                    class="text-xs font-medium {{ $currentStep >= $index + 1 ? 'text-white' : 'text-gray-600 dark:text-gray-300' }}">
                                    {{ $index + 1 }}
                                </span>
                            </button>

                            <!-- Mobile Tooltip -->
                            @if($totalSteps > 1)
                                <div
                                    class="absolute bottom-full mb-2 -translate-x-1/2 translate-y-3 left-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none md:hidden">
                                    <div class="bg-gray-900 text-white text-xs px-2 py-1 rounded shadow-lg">
                                        {{ $step['name'] }}
                                    </div>
                                    <div
                                        class="w-2 h-2 bg-gray-900 transform rotate-45 translate-y-1 ml-[calc(50%-4px)]"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>


    <!-- Form Fields -->
    @foreach($form->categories as $index => $category)
        <div x-show="$wire.currentStep === {{ $index + 1 }}"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform translate-y-4"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             class="space-y-6">
            @foreach($category->fields as $field)
                <div class="space-y-2">
                    @if($field->type === 'header')
                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ $field->content }}
                        </h4>
                    @elseif($field->type === 'description')
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {!! \App\Helpers\MarkdownHelper::toHtml($field->content) !!}
                        </div>
                    @else
                        <label for="field_{{ $field->id }}"
                               class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $field->label }}
                            @if($field->required)
                                <span class="text-red-500">*</span>
                            @endif
                        </label>

                        @if($field->help_text)
                            <p class="mt-1 text-sm text-gray-500">{{ $field->help_text }}</p>
                        @endif

                        @if($field->type === 'text')
                            <input type="text"
                                   id="field_{{ $field->id }}"
                                   wire:model.live="fieldValues.{{ $field->id }}"
                                   @if($field->char_limit) placeholder="Max {{ $field->char_limit }} characters" @endif
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                   @if($field->char_limit) maxlength="{{ $field->char_limit }}" @endif>

                        @elseif($field->type === 'textarea')
                            <textarea id="field_{{ $field->id }}"
                                      wire:model.live="fieldValues.{{ $field->id }}"
                                      rows="3"
                                      @if($field->char_limit) placeholder="Max {{ $field->char_limit }} characters" @endif
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                      @if($field->char_limit) maxlength="{{ $field->char_limit }}" @endif></textarea>

                        @elseif($field->type === 'select')
                            <select id="field_{{ $field->id }}"
                                    wire:model="fieldValues.{{ $field->id }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="">Select an option</option>
                                @foreach(explode(',', $field->options) as $option)
                                    <option value="{{ trim($option) }}">{{ trim($option) }}</option>
                                @endforeach
                            </select>

                        @elseif(in_array($field->type, ['checkbox', 'radio']))
                            <div class="mt-2 space-y-2">
                                @foreach(explode(',', $field->options) as $option)
                                    <div class="flex items-center">
                                        @if($field->type === 'checkbox')
                                            <input type="checkbox"
                                                   id="field_{{ $field->id }}_{{ $loop->index }}"
                                                   wire:model="fieldValues.{{ $field->id }}.{{ $loop->index }}"
                                                   value="{{ trim($option) }}"
                                                   class="focus:ring-sky-500 h-4 w-4 text-sky-600 border-gray-300 dark:border-gray-600">
                                        @else
                                            <input type="radio"
                                                   id="field_{{ $field->id }}_{{ $loop->index }}"
                                                   wire:model="fieldValues.{{ $field->id }}"
                                                   value="{{ trim($option) }}"
                                                   class="focus:ring-sky-500 h-4 w-4 text-sky-600 border-gray-300 dark:border-gray-600">
                                        @endif
                                        <label for="field_{{ $field->id }}_{{ $loop->index }}"
                                               class="ml-3 block text-sm text-gray-700 dark:text-gray-300">
                                            {{ trim($option) }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                        @elseif($field->type === 'file')
                            <div class="mt-1 space-y-2">
                                @if(isset($fieldValues[$field->id]))
                                    <div
                                        class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24"
                                                 stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                            <div>
                                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                                    {{ basename($fieldValues[$field->id]) }}
                                                </p>
                                                @if($submission && $submission->id)
                                                    <a href="{{ route('submissions.download', ['submission' => $submission->id, 'filename' => basename($fieldValues[$field->id])]) }}"
                                                       class="text-xs text-sky-600 hover:text-sky-900 dark:text-sky-400 dark:hover:text-sky-300">
                                                        Download file
                                                    </a>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex items-center">
                                            <button type="button"
                                                    wire:click="deleteFile({{ $field->id }})"
                                                    wire:confirm="Are you sure you want to delete this file?"
                                                    class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900 hover:bg-red-200 dark:hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                @endif

                                <div class="relative">
                                    <input type="file"
                                           id="field_{{ $field->id }}"
                                           wire:model="tempFiles.field_{{ $field->id }}"
                                           class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 dark:text-gray-400 dark:file:bg-gray-700 dark:file:text-gray-300">

                                    <div wire:loading wire:target="tempFiles.field_{{ $field->id }}"
                                         class="absolute inset-0 bg-gray-100 bg-opacity-50 flex items-center justify-center">
                                        <svg class="animate-spin h-5 w-5 text-sky-600"
                                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                    stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                </div>

                                @error('tempFiles.field_' . $field->id)
                                <p class="mt-1 text-sm text-red-600 dark:text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                        @endif

                        @error('fieldValues.' . $field->id)
                        <p class="mt-2 text-sm text-red-600 dark:text-red-500">{{ $message }}</p>
                        @enderror

                        @if($field->char_limit)
                            @php
                                $used = strlen($fieldValues[$field->id] ?? '');
                                $limit = (int) $field->char_limit;
                                $threshold = max((int) ceil($limit * 0.9), 1); // 90% used
                                $nearLimit = $used >= $threshold && $used < $limit;
                            @endphp
                            <p class="mt-1 text-sm aria-live-polite"
                               aria-live="polite"
                               @class([
                                   'text-gray-500 dark:text-gray-400' => !$nearLimit && $used < $limit,
                                   'text-amber-600 dark:text-amber-400' => $nearLimit,
                                   'text-red-600 dark:text-red-500' => $used >= $limit,
                               ])>
                                {{ $used }}/{{ $limit }} characters
                            </p>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach

    <!-- Navigation buttons -->
    <div class="flex justify-between items-center mt-8">
        <div>
            @if($currentStep > 1)
                <button wire:click="previousStep" type="button"
                        class="bg-white dark:bg-gray-800 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                    Previous
                </button>
            @endif
        </div>

        <div class="flex space-x-4">
            <button wire:click="saveAsDraft" type="button"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                Save as Draft
            </button>

            @if($currentStep < $totalSteps)
                <button wire:click="nextStep" type="button"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                    Next
                </button>
            @else
                <button wire:click="submit" type="button"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Submit
                </button>
            @endif
        </div>
    </div>

    <!-- Auto-save script -->
    <script>
        document.addEventListener('livewire:init', () => {
            setInterval(() => {
            @this.autosaveDraft()
                ;
            }, {{ $autoSaveInterval }});
        });
    </script>
</div>
