@props(['stats'])

<div class="mb-8 bg-white dark:bg-gray-800 shadow rounded-xl p-6">
    <div class="flex items-center gap-3 mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center">
            <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
        </div>
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">System Statistics</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Overview of platform usage and activity</p>
        </div>
    </div>

    <!-- User Statistics -->
    <div class="mb-6">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Users</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-statistics-card label="Total Users" :value="$stats['users']['total']" />
            <x-statistics-card label="Admins" :value="$stats['users']['admins']" />
            <x-statistics-card label="Internal Evaluators" :value="$stats['users']['internal_evaluators']" />
            <x-statistics-card label="External Evaluators" :value="$stats['users']['external_evaluators']" />
            <x-statistics-card label="Regular Users" :value="$stats['users']['regular_users']" />
            <x-statistics-card label="Users with 2FA" :value="$stats['users']['with_2fa']" />
            <x-statistics-card label="Unconfirmed Emails" :value="$stats['users']['unconfirmed_email']" />
        </div>
    </div>

    <!-- Form Statistics -->
    <div class="mb-6">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Forms</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-statistics-card label="Total Forms" :value="$stats['forms']['total']" />
            <x-statistics-card label="Draft Forms" :value="$stats['forms']['draft']" />
            <x-statistics-card label="Published Forms" :value="$stats['forms']['published']" />
            <x-statistics-card label="Archived Forms" :value="$stats['forms']['archived']" />
        </div>
    </div>

    <!-- Submission Statistics -->
    <div>
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Submissions</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <x-statistics-card label="Total Submissions" :value="$stats['submissions']['total']" />
            <x-statistics-card label="Draft Submissions" :value="$stats['submissions']['draft']" />
            <x-statistics-card label="Submitted" :value="$stats['submissions']['submitted']" />
            <x-statistics-card label="Under Review" :value="$stats['submissions']['under_review']" />
            <x-statistics-card label="Completed" :value="$stats['submissions']['completed']" />
        </div>
    </div>
</div>
