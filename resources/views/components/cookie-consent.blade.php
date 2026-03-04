{{-- Cookie Consent Banner for Essential Cookies --}}
{{-- GDPR: Essential cookies are exempt from consent, but users must be informed --}}

<div 
    x-data="{ 
        show: false,
        init() {
            this.show = !localStorage.getItem('cookie_consent_acknowledged');
        },
        acknowledge() {
            localStorage.setItem('cookie_consent_acknowledged', new Date().toISOString());
            this.show = false;
        }
    }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-4"
    x-cloak
    class="fixed bottom-0 inset-x-0 z-50 pb-2 sm:pb-5"
    role="dialog"
    aria-label="Cookie notice"
>
    <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
        <div class="p-4 rounded-lg bg-gray-800 dark:bg-gray-700 shadow-lg sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex-1 flex items-start sm:items-center">
                    {{-- Cookie Icon --}}
                    <span class="flex p-2 rounded-lg bg-gray-700 dark:bg-gray-600 shrink-0">
                        <svg class="h-6 w-6 text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </span>
                    
                    <p class="ml-3 text-sm text-white">
                        <span class="font-medium">Cookie Notice:</span>
                        <span class="ml-1">
                            This website uses essential cookies required for basic functionality, including session management and security. 
                            No tracking or third-party cookies are used.
                        </span>
                        <a href="{{ route('policy.show') }}" class="ml-1 font-medium text-sky-400 hover:text-sky-300 underline whitespace-nowrap">
                            Learn more
                        </a>
                    </p>
                </div>
                
                <div class="shrink-0 flex items-center gap-3">
                    {{-- Dismiss button --}}
                    <button 
                        @click="acknowledge()"
                        type="button" 
                        class="flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-gray-800 bg-sky-400 hover:bg-sky-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-sky-500 transition-colors duration-200"
                    >
                        Got it
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
