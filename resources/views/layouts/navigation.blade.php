<nav x-data="{ open: false }"
     class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 sticky top-0 z-50">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ url('/') }}">
                        <x-application-logo class="h-8 w-auto" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-1 lg:flex lg:ml-8 lg:items-center">
                    <a href="{{ url('/') }}"
                       @if(request()->is('/')) aria-current="page" @endif
                       class="inline-flex items-center px-3 py-2 text-sm font-medium border-b-2 transition-colors duration-200 {{ request()->is('/') ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                        {{ __('Home') }}
                    </a>
                    <a href="{{ route('forms.public_index') }}"
                       @if(request()->routeIs('forms.public_index')) aria-current="page" @endif
                       class="inline-flex items-center px-3 py-2 text-sm font-medium border-b-2 transition-colors duration-200 {{ request()->routeIs('forms.public_index') ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                        {{ __('Forms') }}
                    </a>
                    @auth
                        <a href="{{ route('dashboard') }}"
                           @if(request()->routeIs('dashboard')) aria-current="page" @endif
                           class="inline-flex items-center px-3 py-2 text-sm font-medium border-b-2 transition-colors duration-200 {{ request()->routeIs('dashboard') ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                            {{ __('Dashboard') }}
                        </a>
                        @if(auth()->user()->role === 'internal_evaluator' || auth()->user()->isAdmin() || auth()->user()->role === 'external_evaluator')
                        <a href="{{ route('forms.user_index') }}"
                           @if(request()->routeIs('forms.user_index')) aria-current="page" @endif
                           class="inline-flex items-center px-3 py-2 text-sm font-medium border-b-2 transition-colors duration-200 {{ request()->routeIs('forms.user_index') ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                            {{ __('My Forms') }}
                        </a>
                        @endif
                        @if(auth()->user()->isAdmin())
                        <a href="{{ url('/admin') }}"
                           @if(request()->is('admin*')) aria-current="page" @endif
                           class="inline-flex items-center px-3 py-2 text-sm font-medium border-b-2 transition-colors duration-200 {{ request()->is('admin*') ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                            {{ __('Admin') }}
                        </a>
                        @endif
                        <a href="{{ route('submissions.user') }}"
                           @if(request()->routeIs('submissions.user')) aria-current="page" @endif
                           class="inline-flex items-center px-3 py-2 text-sm font-medium border-b-2 transition-colors duration-200 {{ request()->routeIs('submissions.user') ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                            {{ __('Submissions') }}
                        </a>
                    @endauth
                </div>
            </div>

            <!-- Settings Dropdown for authenticated users -->
            @auth
                <div class="hidden lg:flex lg:items-center lg:ml-6">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors duration-200">
                                <div class="w-7 h-7 rounded-full bg-sky-600 flex items-center justify-center mr-2 text-xs font-bold text-white">
                                    {{ substr(Auth::user()->name, 0, 1) }}
                                </div>
                                {{ Auth::user()->name }}
                                <svg class="ml-2 -mr-0.5 h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.show')">
                                {{ __('Profile') }}
                            </x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                                 onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            @else
                <!-- Login/Register for non-authenticated users -->
                <div class="hidden lg:flex lg:items-center lg:space-x-3">
                    <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors duration-200">
                        {{ __('Login') }}
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex items-center bg-sky-600 hover:bg-sky-700 text-white rounded-lg px-4 py-2 text-sm font-medium transition-colors duration-200">
                        {{ __('Register') }}
                    </a>
                </div>
            @endauth

            <!-- Hamburger -->
            <div class="flex items-center lg:hidden">
                <button @click="open = !open"
                        aria-label="Toggle navigation menu"
                        :aria-expanded="open.toString()"
                        class="inline-flex items-center justify-center p-2 rounded-lg text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/50 transition duration-200">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': !open }" class="inline-flex"
                              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16"/>
                        <path :class="{'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                              stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': !open}" class="hidden lg:hidden bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-700">
        <div class="pt-2 pb-3 space-y-1 px-3">
            <a href="{{ url('/') }}"
               class="block px-4 py-2 text-base font-medium rounded-lg transition-colors duration-200 {{ request()->is('/') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                {{ __('Home') }}
            </a>
            <a href="{{ route('forms.public_index') }}"
               class="block px-4 py-2 text-base font-medium rounded-lg transition-colors duration-200 {{ request()->routeIs('forms.public_index') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                {{ __('Forms') }}
            </a>
            @auth
                <a href="{{ route('dashboard') }}"
                   class="block px-4 py-2 text-base font-medium rounded-lg transition-colors duration-200 {{ request()->routeIs('dashboard') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                    {{ __('Dashboard') }}
                </a>
                @if(auth()->user()->role === 'internal_evaluator' || auth()->user()->isAdmin() || auth()->user()->role === 'external_evaluator')
                    <a href="{{ route('forms.user_index') }}"
                       class="block px-4 py-2 text-base font-medium rounded-lg transition-colors duration-200 {{ request()->routeIs('forms.user_index') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                        {{ __('My Forms') }}
                    </a>
                @endif
                @if(auth()->user()->isAdmin())
                    <a href="{{ url('/admin') }}"
                       class="block px-4 py-2 text-base font-medium rounded-lg transition-colors duration-200 {{ request()->is('admin*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                        {{ __('Admin') }}
                    </a>
                @endif
                <a href="{{ route('submissions.user') }}"
                   class="block px-4 py-2 text-base font-medium rounded-lg transition-colors duration-200 {{ request()->routeIs('submissions.user') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                    {{ __('Submissions') }}
                </a>
            @endauth
        </div>

        @auth
            <!-- Responsive Settings Options -->
            <div class="pt-4 pb-3 border-t border-slate-200 dark:border-slate-700">
                <div class="px-4 flex items-center">
                    <div class="w-10 h-10 rounded-full bg-sky-600 flex items-center justify-center mr-3 text-sm font-bold text-white">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                    <div>
                        <div class="font-medium text-base text-slate-900 dark:text-white">{{ Auth::user()->name }}</div>
                        <div class="font-medium text-sm text-slate-500 dark:text-slate-400">{{ Auth::user()->email }}</div>
                    </div>
                </div>

                <div class="mt-3 space-y-1 px-3">
                    <a href="{{ route('profile.show') }}"
                       class="block px-4 py-2 text-base font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800 rounded-lg transition-colors duration-200">
                        {{ __('Profile') }}
                    </a>

                    <!-- Authentication -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="w-full text-left px-4 py-2 text-base font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors duration-200">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>
            </div>
        @else
            <!-- Login/Register for mobile -->
            <div class="pt-4 pb-3 border-t border-slate-200 dark:border-slate-700 px-3 space-y-2">
                <a href="{{ route('login') }}"
                   class="block text-center px-4 py-2 text-base font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-800 rounded-lg transition-colors duration-200">
                    {{ __('Login') }}
                </a>
                <a href="{{ route('register') }}"
                   class="block text-center px-4 py-2 text-base font-medium bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors duration-200">
                    {{ __('Register') }}
                </a>
            </div>
        @endauth
    </div>
</nav>
