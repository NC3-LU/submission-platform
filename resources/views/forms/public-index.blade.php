<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-200 leading-tight">
            {{ __('Available Forms') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if($forms->isEmpty())
                <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-12 text-center">
                    <svg class="mx-auto w-12 h-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">No Forms Available</h3>
                    <p class="mt-2 text-slate-500 dark:text-slate-400">Check back later or contact support for more information.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($forms as $form)
                        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 border-l-4 border-l-sky-500 p-6 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $form->title }}</h3>
                            <p class="text-sm text-slate-600 dark:text-slate-400 line-clamp-3 mt-2">{{ $form->description }}</p>
                            <div class="mt-4 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    @if($form->visibility === 'public')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">Public</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Login Required</span>
                                    @endif
                                    <span class="text-xs text-slate-400">{{ $form->created_at->diffForHumans() }}</span>
                                </div>
                                @if($form->visibility === 'public' || auth()->check())
                                    <a href="{{ route('submissions.create', $form) }}" class="text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 font-medium text-sm inline-flex items-center">
                                        Apply
                                        <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                @else
                                    <a href="{{ route('login') }}" class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium text-sm">Login to apply</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $forms->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
