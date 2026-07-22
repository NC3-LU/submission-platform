@props(['forms', 'totalForms' => 0])

{{-- Section A: Hero --}}
<div class="relative overflow-hidden bg-gradient-to-br from-[#0A1745] to-[#113885] dark:from-[#060E2B] dark:to-[#0A1745]">
    {{-- Decorative logo watermark --}}
    <img src="{{ asset('img/nc3-logo-no-text-no-bg.png') }}" alt="" class="absolute right-0 top-1/2 -translate-y-1/2 w-96 h-96 opacity-[0.07] pointer-events-none select-none" aria-hidden="true">

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24">
        <span class="text-sky-400 text-sm font-semibold tracking-wider uppercase">NC3 Submission Platform</span>

        <h1 class="mt-4 text-3xl lg:text-4xl xl:text-5xl font-bold text-white leading-tight">
            Secure Submissions for Luxembourg's Cybersecurity Ecosystem
        </h1>

        <p class="mt-6 text-lg text-slate-300 max-w-2xl">
            Submit applications, track progress, and collaborate with Cybersecurity Luxembourg Ecosystem through our streamlined and secure platform.
        </p>

        <a href="#forms" class="mt-8 inline-flex items-center px-6 py-3 text-base font-semibold text-white bg-sky-600 hover:bg-sky-500 rounded-lg transition-colors shadow-lg shadow-sky-600/20">
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

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8 mt-8">
            @forelse($forms as $form)
                @php
                    $isOpen = ! in_array($form->availabilityState(), ['scheduled', 'closed']);
                    $canFill = $isOpen && ($form->visibility === 'public' || auth()->check());
                @endphp
                <div class="group relative flex flex-col overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:border-sky-300 dark:hover:border-sky-700 focus-within:ring-2 focus-within:ring-sky-500 focus-within:ring-offset-2 dark:focus-within:ring-offset-slate-900">
                    @if($form->header_image)
                        <div class="h-44 w-full overflow-hidden"
                             @if($form->header_theme_color) style="border-top: 3px solid {{ $form->header_theme_color }}" @endif>
                            <img src="{{ $form->header_image_url }}" alt=""
                                 class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-105"
                                 style="object-position: 50% {{ $form->header_image_position }}%">
                        </div>
                    @else
                        <div class="h-1.5 w-full bg-gradient-to-r from-sky-500 to-cyan-400"></div>
                    @endif

                    <div class="flex flex-1 flex-col p-6">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white transition-colors group-hover:text-sky-600 dark:group-hover:text-sky-400">{{ $form->title }}</h3>
                        <div class="mt-2 flex-1 text-sm text-slate-600 dark:text-slate-400 line-clamp-3 prose prose-sm dark:prose-invert max-w-none">{!! \App\Helpers\MarkdownHelper::toHtml($form->description) !!}</div>

                        <div class="mt-5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                @if($form->visibility === 'public')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">Public</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Login Required</span>
                                @endif
                                <span class="text-xs text-slate-400">{{ $form->created_at->diffForHumans() }}</span>
                            </div>
                            @if($form->availabilityState() === 'scheduled')
                                <span class="shrink-0 text-sm text-amber-600 dark:text-amber-400">Opens {{ $form->available_from->format('M j, Y') }}</span>
                            @elseif($form->availabilityState() === 'closed')
                                <span class="shrink-0 text-sm text-red-600 dark:text-red-400">Closed {{ $form->available_until->format('M j, Y') }}</span>
                            @elseif($canFill)
                                <span class="shrink-0 inline-flex items-center text-sm font-medium text-sky-600 dark:text-sky-400">
                                    Fill
                                    <svg class="ml-1 w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </span>
                            @else
                                <a href="{{ route('login') }}" class="relative z-10 shrink-0 text-sm font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Login to fill</a>
                            @endif
                        </div>
                    </div>

                    @if($canFill)
                        {{-- Stretched link: whole card is clickable to fill the form --}}
                        <a href="{{ route('submissions.create', $form) }}" class="absolute inset-0 focus:outline-none" aria-label="Fill {{ $form->title }}"></a>
                    @endif
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

        @if($totalForms > 6)
        <div class="mt-8 text-center">
            <a href="{{ route('forms.public_index') }}" class="inline-flex items-center text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 font-medium">
                View all available forms
                <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        @endif
    </div>
</div>
