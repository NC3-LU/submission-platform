<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ url()->previous() }}" class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $form->title }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Submit Form') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if($form->header_image)
                <div class="mb-6 overflow-hidden rounded-xl shadow"
                     @if($form->header_theme_color) style="border-top: 4px solid {{ $form->header_theme_color }}" @endif>
                    <img src="{{ $form->header_image_url }}" alt="{{ $form->title }}"
                         class="w-full h-48 sm:h-64 object-cover"
                         style="object-position: 50% {{ $form->header_image_position }}%">
                </div>
            @endif

            @if($form->description)
                <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                    <div class="text-gray-600 dark:text-gray-400 prose dark:prose-invert max-w-none text-sm">
                        {!! \App\Helpers\MarkdownHelper::toHtml($form->description) !!}
                    </div>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                @livewire('submission-form', ['form' => $form])
            </div>
        </div>
    </div>
</x-app-layout>
