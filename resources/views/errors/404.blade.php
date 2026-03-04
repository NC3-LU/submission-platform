<x-app-layout>
    <div class="py-16">
        <div class="max-w-lg mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-8 text-center">
                <div class="w-16 h-16 rounded-full bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                <p class="text-5xl font-bold text-gray-200 dark:text-gray-700 mb-2">404</p>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Page Not Found</h1>
                <p class="text-gray-500 dark:text-gray-400 mb-8">
                    {{ $exception->getMessage() ?: "The page you're looking for doesn't exist or has been moved." }}
                </p>

                <div class="flex items-center justify-center gap-4">
                    <a href="{{ url()->previous() }}" class="inline-flex items-center gap-2 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Go Back
                    </a>
                    <a href="/" class="inline-flex items-center gap-2 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg transition-colors">
                        Return Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
