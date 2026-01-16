<aside :class="{
            'translate-x-0 w-72': sidebarMobile, 
            '-translate-x-full lg:translate-x-0': !sidebarMobile,
            'lg:w-72': sidebarDesktop,
            'lg:w-20': !sidebarDesktop
        }"
    class="fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-100 transition-all duration-300 flex flex-col shadow-sm lg:static">

    <div class="flex items-center h-16 px-6 shrink-0 border-b border-gray-50">
        <div class="flex items-center gap-3 overflow-hidden">
            <div
                class="h-8 w-8 bg-[#FB9300] rounded-lg flex items-center justify-center shrink-0 shadow-lg shadow-orange-200">
                <span class="text-white font-bold text-lg">L</span>
            </div>
            <span x-show="sidebarDesktop" x-transition.opacity
                class="text-xl font-bold tracking-tight text-[#343F56] whitespace-nowrap">LinkTown</span>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        @forelse($sidebarMenu as $item)
            @php
                $isCurrent = request()->routeIs($item['route']);
                $hasActiveChild = isset($item['has_active_child']) && $item['has_active_child'];
            @endphp

            @if(empty($item['children']))
                {{-- Single Menu --}}
                <a href="{{ $item['route'] ? route($item['route']) : '#' }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all group {{ $isCurrent ? 'bg-orange-50 text-[#FB9300]' : 'text-gray-500 hover:bg-gray-50 hover:text-[#343F56]' }}"
                    :class="sidebarDesktop ? 'justify-start' : 'justify-center'"
                    :title="!sidebarDesktop ? '{{ $item['name'] }}' : ''">

                    @if(!empty($item['icon']))
                        <i
                            class="{{ $item['icon'] }} w-5 h-5 shrink-0 {{ $isCurrent ? 'text-[#FB9300]' : 'text-gray-400 group-hover:text-[#343F56]' }}"></i>
                    @endif
                    <span x-show="sidebarDesktop" x-transition.opacity class="whitespace-nowrap">{{ $item['name'] }}</span>
                </a>
            @else
                {{-- Dropdown Menu --}}
                <div x-data="{ open: {{ $hasActiveChild ? 'true' : 'false' }} }" class="space-y-1">
                    <button type="button"
                        @click="if(!sidebarDesktop) { sidebarDesktop = true; open = true; } else { open = !open }"
                        class="w-full flex items-center rounded-xl px-3 py-2.5 text-sm font-medium transition-all group {{ $hasActiveChild ? 'text-[#FB9300] bg-orange-50/50' : 'text-gray-500 hover:bg-gray-50 hover:text-[#343F56]' }}"
                        :class="sidebarDesktop ? 'justify-between' : 'justify-center'">
                        <div class="flex items-center gap-3">
                            @if(!empty($item['icon']))
                                <i
                                    class="{{ $item['icon'] }} w-5 h-5 shrink-0 {{ $hasActiveChild ? 'text-[#FB9300]' : 'text-gray-400 group-hover:text-[#343F56]' }}"></i>
                            @endif
                            <span x-show="sidebarDesktop" x-transition.opacity
                                class="whitespace-nowrap">{{ $item['name'] }}</span>
                        </div>
                        <svg x-show="sidebarDesktop" :class="open ? 'rotate-90' : ''"
                            class="w-4 h-4 transition-transform text-gray-400 group-hover:text-[#343F56]" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>

                    <div x-show="open && sidebarDesktop" x-collapse class="pl-11 space-y-1">
                        @foreach($item['children'] as $child)
                            <a href="{{ $child['route'] ? route($child['route']) : '#' }}"
                                class="block px-3 py-2 rounded-lg text-sm font-medium transition-all {{ request()->routeIs($child['route']) ? 'text-[#FB9300]' : 'text-gray-400 hover:text-[#343F56]' }}">
                                {{ $child['name'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @empty
            <div x-show="sidebarDesktop" class="px-4 py-8 text-center">
                <p class="text-xs text-gray-400 italic font-medium">No menu available</p>
            </div>
        @endforelse
    </nav>

    <div class="hidden lg:flex border-t border-gray-50 p-4">
        <button @click="sidebarDesktop = !sidebarDesktop"
            class="w-full flex items-center justify-center p-2 rounded-xl bg-gray-50 text-gray-400 hover:text-[#FB9300] hover:bg-orange-50 transition-all">
            <svg :class="!sidebarDesktop ? 'rotate-180' : ''" class="w-5 h-5 transition-transform duration-500"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
        </button>
    </div>
</aside>