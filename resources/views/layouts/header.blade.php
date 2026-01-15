<header class="bg-white border-b border-gray-100 h-16 flex items-center justify-between px-4 lg:px-8 shrink-0 z-30">
    <div class="flex items-center gap-4">
        <button @click="sidebarMobile = true" class="lg:hidden text-gray-500 hover:text-blue-600 p-2 rounded-lg">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <h2 class="hidden sm:block text-xs font-bold text-gray-400 uppercase tracking-widest">
            @yield('header', 'Dashboard')</h2>
    </div>

    <div class="flex items-center gap-3">

        <div class="h-6 w-[1px] bg-gray-100 mx-2"></div>

        <div x-data="{ userMenu: false }" class="relative">
            <button @click="userMenu = !userMenu" @click.away="userMenu = false"
                class="flex items-center gap-3 p-1 rounded-xl hover:bg-gray-50 transition-all">
                <div
                    class="h-9 w-9 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-xs shadow-md shadow-blue-100 uppercase">
                    {{ substr(Auth::user()->name ?? 'AD', 0, 2) }}
                </div>
                <div class="hidden md:block text-left pr-2">
                    <p class="text-sm font-bold text-gray-900 leading-none mb-1">
                        {{ Auth::user()->name ?? 'Admin User' }}</p>
                    <p class="text-[11px] text-gray-500 leading-none uppercase tracking-tighter">
                        {{ Auth::user()->role ?? 'Super Admin' }}</p>
                </div>
                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="userMenu ? 'rotate-180' : ''"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="userMenu" x-cloak x-transition.origin.top.right
                class="absolute right-0 mt-2 w-56 bg-white border border-gray-100 rounded-2xl shadow-xl z-50 p-2">
                <div class="px-4 py-3 border-b border-gray-50">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Signed in as</p>
                    <p class="text-sm font-medium text-gray-900 truncate">{{ Auth::user()->email ??
                        'admin@linktown.co.id' }}</p>
                </div>
                <div class="py-2">
                    <a href="#"
                        class="flex items-center gap-3 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 hover:text-blue-600 rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        My Profile
                    </a>
                    <a href="#"
                        class="flex items-center gap-3 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 hover:text-blue-600 rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </a>
                </div>
                <div class="pt-2 border-t border-gray-50">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-50 rounded-xl transition-all font-semibold">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Log Out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>