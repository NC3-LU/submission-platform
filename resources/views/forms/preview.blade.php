<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('forms.edit', $form) }}" class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $form->title }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Preview Mode</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                @if($form->description)
                    <div class="mb-6 text-sm text-gray-600 dark:text-gray-400 prose prose-sm dark:prose-invert max-w-none">
                        {!! \App\Helpers\MarkdownHelper::toHtml($form->description) !!}
                    </div>
                @endif

                <form x-data="{ step: 1, totalSteps: {{ $form->categories->count() }} }">
                    @foreach($form->categories as $index => $category)
                        <div x-show="step === {{ $index + 1 }}" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ $category->name }}</h3>
                            @if($category->description)
                                <div class="mb-4 text-sm text-gray-500 dark:text-gray-400 prose prose-sm dark:prose-invert max-w-none">
                                    {!! \App\Helpers\MarkdownHelper::toHtml($category->description) !!}
                                </div>
                            @endif

                            @foreach($category->fields as $field)
                                @if($field->depends_on_field_id)
                                    <div x-show="document.querySelector('[name=field_{{ $field->depends_on_field_id }}]:checked')?.value == '{{ $field->depends_on_value }}' || document.querySelector('[name=field_{{ $field->depends_on_field_id }}]')?.value == '{{ $field->depends_on_value }}'"
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
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $field->label }}{{ $field->required ? ' *' : '' }}</label>

                                    @if($field->type === 'text')
                                        <input type="text" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent" {{ $field->required ? 'required' : '' }}>
                                    @elseif($field->type === 'textarea')
                                        <textarea class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent resize-none" rows="3" {{ $field->required ? 'required' : '' }}></textarea>
                                    @elseif(in_array($field->type, ['select', 'checkbox', 'radio']))
                                        @php $options = explode(',', $field->options); @endphp

                                        @if($field->type === 'select')
                                            <select class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent" {{ $field->required ? 'required' : '' }}>
                                                @foreach($options as $option)
                                                    <option value="{{ trim($option) }}">{{ trim($option) }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            @foreach($options as $option)
                                                <div class="flex items-center mt-2">
                                                    <input type="{{ $field->type }}" name="field_{{ $field->id }}" value="{{ trim($option) }}" class="h-4 w-4 text-sky-600 border-gray-300 dark:border-gray-600 focus:ring-sky-500">
                                                    <label class="ml-3 text-sm text-gray-700 dark:text-gray-300">{{ trim($option) }}</label>
                                                </div>
                                            @endforeach
                                        @endif
                                    @elseif($field->type === 'file')
                                        <input type="file" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 dark:text-gray-400 dark:file:bg-gray-700 dark:file:text-gray-300" {{ $field->required ? 'required' : '' }}>
                                    @endif
                                </div>
                                @endif
                                @if($field->depends_on_field_id)
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach

                    <div class="mt-8 flex justify-between items-center pt-6 border-t border-gray-200 dark:border-gray-700">
                        <button
                            x-show="step > 1"
                            @click="step--"
                            type="button"
                            class="inline-flex items-center px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Previous
                        </button>
                        <div></div>
                        <button
                            x-show="step < totalSteps"
                            @click="step++"
                            type="button"
                            class="inline-flex items-center px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium rounded-lg transition-colors">
                            Next
                        </button>
                        <button
                            x-show="step === totalSteps"
                            type="submit"
                            class="inline-flex items-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors"
                            disabled>
                            Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
