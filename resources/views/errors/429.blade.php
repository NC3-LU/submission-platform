<x-app-layout>
    <div class="py-16">
        <div class="max-w-lg mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-8 text-center">
                <div class="w-16 h-16 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>

                <p class="text-5xl font-bold text-gray-200 dark:text-gray-700 mb-2">429</p>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Too Many Requests</h1>
                <p class="text-gray-500 dark:text-gray-400 mb-8">
                    You've made too many requests. Please wait a moment and try again.
                </p>

                <a href="/" class="inline-flex items-center gap-2 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg transition-colors">
                    Return Home
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
