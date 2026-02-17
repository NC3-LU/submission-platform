@props(['forms'])

{{-- Section A: Hero --}}
<div class="relative overflow-hidden bg-gradient-to-br from-[#0A1745] to-[#113885] dark:from-[#060E2B] dark:to-[#0A1745]">
    {{-- Decorative logo watermark --}}
    <img src="{{ asset('img/nc3-logo-no-text-no-bg.png') }}" alt="" class="absolute right-0 top-1/2 -translate-y-1/2 w-96 h-96 opacity-[0.07] pointer-events-none select-none" aria-hidden="true">

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24">
        <span class="text-teal-400 text-sm font-semibold tracking-wider uppercase">NC3 Submission Platform</span>

        <h1 class="mt-4 text-3xl lg:text-4xl xl:text-5xl font-bold text-white leading-tight">
            Secure Submissions for Luxembourg's Cybersecurity Ecosystem
        </h1>

        <p class="mt-6 text-lg text-slate-300 max-w-2xl">
            Submit applications, track progress, and collaborate with NC3 through our streamlined and secure platform.
        </p>

        <a href="#forms" class="mt-8 inline-flex items-center px-6 py-3 text-base font-semibold text-white bg-teal-600 hover:bg-teal-500 rounded-lg transition-colors shadow-lg shadow-teal-600/20">
            Browse Available Forms
            <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>

{{-- Section B: Forms Grid --}}
<div id="forms" class="bg-slate-50 dark:bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Available Forms</h2>
        <p class="mt-2 text-slate-600 dark:text-slate-400">Select an application to get started</p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-8">
            @forelse($forms as $form)
                <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 border-l-4 border-l-teal-500 p-6 hover:shadow-md transition-shadow">
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
                            <a href="{{ route('submissions.create', $form) }}" class="text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 font-medium text-sm inline-flex items-center">
                                Apply
                                <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium text-sm">Login to apply</a>
                        @endif
                    </div>
                </div>
            @empty
                {{-- Empty state --}}
                <div class="col-span-full text-center py-12">
                    <div class="mx-auto w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">No Forms Available</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">There are currently no application forms available. Please check back later.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-8 text-center">
            <a href="{{ route('forms.public_index') }}" class="inline-flex items-center text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 font-medium">
                View all available forms
                <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</div>
