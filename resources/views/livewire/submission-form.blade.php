<div>
    <!-- Global Error Display -->
    @if ($errors->any())
        <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-500 dark:text-red-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-300">Please fix the following errors:</h3>
                    <ul class="mt-2 list-disc pl-5 space-y-1 text-sm text-red-700 dark:text-red-400">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <!-- Auto-save notification -->
    <div x-data="{ show: false, message: '' }"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         x-on:success.window="message = $event.detail; show = true; setTimeout(() => show = false, 2000)"
         class="fixed bottom-4 right-4 z-50 flex items-center gap-2 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300 px-4 py-2.5 rounded-lg shadow-lg"
         style="display: none;">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span x-text="message" class="text-sm font-medium"></span>
    </div>

    <!-- Progress Navigation -->
    @if($totalSteps > 1)
        <div class="mb-8">
            <!-- Current Step Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $this->currentStepData['name'] }}
                    </h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300">
                        Step {{ $currentStep }} of {{ $totalSteps }}
                    </span>
                </div>

                @if($this->currentStepData['description'])
                    <div class="text-sm text-gray-500 dark:text-gray-400 prose prose-sm dark:prose-invert max-w-none">
                        {!! \App\Helpers\MarkdownHelper::toHtml($this->currentStepData['description']) !!}
                    </div>
                @endif
            </div>

            <!-- Progress Track -->
            <div class="relative">
                <div class="overflow-hidden h-1.5 bg-gray-200 rounded-full dark:bg-gray-700">
                    <div class="h-1.5 rounded-full bg-gradient-to-r from-sky-500 to-sky-600 transition-all duration-500 ease-out dark:from-sky-600 dark:to-sky-700"
                         style="width: {{ (($currentStep - 1) / ($totalSteps - 1)) * 100 }}%">
                    </div>
                </div>

                <!-- Step Indicators -->
                <div class="flex justify-between mt-3">
                    @foreach($steps as $index => $step)
                        <button wire:click="$set('currentStep', {{ $index + 1 }})"
                                @if($currentStep <= $index + 1) disabled @endif
                                class="flex flex-col items-center gap-1.5 group">
                            <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-medium transition-all duration-300
                                {{ $currentStep > $index + 1 ? 'bg-sky-600 text-white hover:bg-sky-700 cursor-pointer' : '' }}
                                {{ $currentStep === $index + 1 ? 'bg-sky-600 text-white ring-4 ring-sky-100 dark:ring-sky-900/50 scale-110' : '' }}
                                {{ $currentStep < $index + 1 ? 'bg-gray-200 dark:bg-gray-600 text-gray-500 dark:text-gray-400 cursor-default' : '' }}">
                                @if($currentStep > $index + 1)
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </span>
                            <span class="hidden sm:block text-xs font-medium max-w-[80px] text-center truncate
                                {{ $currentStep === $index + 1 ? 'text-sky-700 dark:text-sky-400' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $step['name'] }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <!-- Single step: show category name if available -->
        @if($this->currentStepData['description'])
            <div class="mb-6 text-sm text-gray-500 dark:text-gray-400 prose prose-sm dark:prose-invert max-w-none">
                {!! \App\Helpers\MarkdownHelper::toHtml($this->currentStepData['description']) !!}
            </div>
        @endif
    @endif

    <!-- Form Fields -->
    @foreach($form->categories as $index => $category)
        <div x-show="$wire.currentStep === {{ $index + 1 }}"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="space-y-5">
            @foreach($category->fields as $field)
                @if($field->depends_on_field_id)
                    <div x-show="$wire.fieldValues[{{ (int) $field->depends_on_field_id }}] == @js($field->depends_on_value)"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100">
                @endif
                @if($field->type === 'header')
                    <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 pt-2">
                        {{ $field->content }}
                    </h4>
                @elseif($field->type === 'description')
                    <div class="text-sm text-gray-600 dark:text-gray-400 prose prose-sm dark:prose-invert max-w-none">
                        {!! \App\Helpers\MarkdownHelper::toHtml($field->content) !!}
                    </div>
                @else
                    <div>
                        <label for="field_{{ $field->id }}"
                               class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ $field->label }}
                            @if($field->required)
                                <span class="text-red-500 ml-0.5">*</span>
                            @endif
                        </label>

                        @if($field->help_text)
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1.5">{{ $field->help_text }}</p>
                        @endif

                        @if($field->type === 'text')
                            <input type="text"
                                   id="field_{{ $field->id }}"
                                   wire:model.live="fieldValues.{{ $field->id }}"
                                   @if($field->char_limit) placeholder="Max {{ $field->char_limit }} characters" @endif
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white transition-colors"
                                   @if($field->char_limit) maxlength="{{ $field->char_limit }}" @endif>

                        @elseif($field->type === 'textarea')
                            <textarea id="field_{{ $field->id }}"
                                      wire:model.live="fieldValues.{{ $field->id }}"
                                      rows="4"
                                      @if($field->char_limit) placeholder="Max {{ $field->char_limit }} characters" @endif
                                      class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white transition-colors"
                                      @if($field->char_limit) maxlength="{{ $field->char_limit }}" @endif></textarea>

                        @elseif($field->type === 'select')
                            <select id="field_{{ $field->id }}"
                                    wire:model="fieldValues.{{ $field->id }}"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white transition-colors">
                                <option value="">Select an option</option>
                                @foreach(explode(',', $field->options) as $option)
                                    <option value="{{ trim($option) }}">{{ trim($option) }}</option>
                                @endforeach
                            </select>

                        @elseif(in_array($field->type, ['checkbox', 'radio']))
                            <div class="mt-1.5 space-y-2">
                                @foreach(explode(',', $field->options) as $option)
                                    <label for="field_{{ $field->id }}_{{ $loop->index }}"
                                           class="flex items-center gap-3 p-2.5 rounded-lg border border-transparent hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer">
                                        @if($field->type === 'checkbox')
                                            <input type="checkbox"
                                                   id="field_{{ $field->id }}_{{ $loop->index }}"
                                                   wire:model="fieldValues.{{ $field->id }}.{{ $loop->index }}"
                                                   value="{{ trim($option) }}"
                                                   class="rounded focus:ring-sky-500 h-4 w-4 text-sky-600 border-gray-300 dark:border-gray-600 transition-colors">
                                        @else
                                            <input type="radio"
                                                   id="field_{{ $field->id }}_{{ $loop->index }}"
                                                   wire:model="fieldValues.{{ $field->id }}"
                                                   value="{{ trim($option) }}"
                                                   class="focus:ring-sky-500 h-4 w-4 text-sky-600 border-gray-300 dark:border-gray-600 transition-colors">
                                        @endif
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ trim($option) }}</span>
                                    </label>
                                @endforeach
                            </div>

                        @elseif($field->type === 'file')
                            <div class="mt-1 space-y-3">
                                @if(isset($fieldValues[$field->id]))
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-8 h-8 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">
                                                    {{ basename($fieldValues[$field->id]) }}
                                                </p>
                                                @if($submission && $submission->id)
                                                    <a href="{{ route('submissions.download', ['submission' => $submission->id, 'filename' => basename($fieldValues[$field->id])]) }}"
                                                       class="text-xs text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300 transition-colors">
                                                        Download file
                                                    </a>
                                                @endif
                                            </div>
                                        </div>

                                        <button type="button"
                                                wire:click="deleteFile({{ $field->id }})"
                                                wire:confirm="Are you sure you want to delete this file?"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            Delete
                                        </button>
                                    </div>
                                @endif

                                <div class="relative">
                                    <input type="file"
                                           id="field_{{ $field->id }}"
                                           wire:model="tempFiles.field_{{ $field->id }}"
                                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 dark:text-gray-400 dark:file:bg-sky-900/30 dark:file:text-sky-300 transition-colors">

                                    <div wire:loading wire:target="tempFiles.field_{{ $field->id }}"
                                         class="absolute inset-0 bg-white/60 dark:bg-gray-800/60 rounded-lg flex items-center justify-center">
                                        <div class="flex items-center gap-2 text-sm text-sky-600 dark:text-sky-400">
                                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span>Uploading...</span>
                                        </div>
                                    </div>
                                </div>

                                @error('tempFiles.field_' . $field->id)
                                    <p class="text-sm text-red-600 dark:text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        @error('fieldValues.' . $field->id)
                            <p class="mt-1.5 text-sm text-red-600 dark:text-red-500">{{ $message }}</p>
                        @enderror

                        @if($field->char_limit)
                            @php
                                $used = strlen($fieldValues[$field->id] ?? '');
                                $limit = (int) $field->char_limit;
                                $threshold = max((int) ceil($limit * 0.9), 1);
                                $nearLimit = $used >= $threshold && $used < $limit;
                            @endphp
                            <p class="mt-1 text-xs tabular-nums" aria-live="polite"
                               @class([
                                   'text-gray-400 dark:text-gray-500' => !$nearLimit && $used < $limit,
                                   'text-amber-600 dark:text-amber-400 font-medium' => $nearLimit,
                                   'text-red-600 dark:text-red-500 font-medium' => $used >= $limit,
                               ])>
                                {{ $used }}/{{ $limit }} characters
                            </p>
                        @endif
                    </div>
                @endif
                @if($field->depends_on_field_id)
                    </div>
                @endif
            @endforeach
        </div>
    @endforeach

    <!-- Navigation Buttons -->
    <div class="flex items-center justify-between mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
        <div>
            @if($currentStep > 1)
                <button wire:click="previousStep" type="button"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Previous
                </button>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <button wire:click="saveAsDraft" type="button"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                Save as Draft
            </button>

            @if($currentStep < $totalSteps)
                <button wire:click="nextStep" type="button"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 rounded-lg transition-colors">
                    Next
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            @else
                <button wire:click="submit" type="button"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 rounded-lg transition-colors disabled:opacity-50">
                    <span wire:loading.remove wire:target="submit">Submit</span>
                    <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Submitting...
                    </span>
                    <svg wire:loading.remove wire:target="submit" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </button>
            @endif
        </div>
    </div>

    <!-- Auto-save script -->
    <script>
        document.addEventListener('livewire:init', () => {
            setInterval(() => {
                @this.autosaveDraft();
            }, {{ $autoSaveInterval }});
        });
    </script>
</div>
