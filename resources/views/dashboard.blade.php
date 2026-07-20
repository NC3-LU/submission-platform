<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(Auth::user()->isAdmin() && $adminStats)
                <x-dashboard.admin-statistics :stats="$adminStats" />
            @endif

            <x-dashboard.user-forms :form-stats="$formStats" />
        </div>
    </div>
</x-app-layout>
