<!DOCTYPE html>
<html lang="en" class="h-full">

@include('layouts.head')

<body class="h-full overflow-hidden bg-[#F8FAFC]" x-data="{ sidebarMobile: false, sidebarDesktop: true }">

    <div class="fixed top-[-10%] left-[-5%] w-96 h-96 bg-[#FB9300]/5 rounded-full blur-[120px] pointer-events-none z-0">
    </div>
    <div
        class="fixed bottom-[-10%] right-[-5%] w-[500px] h-[500px] bg-[#343F56]/5 rounded-full blur-[120px] pointer-events-none z-0">
    </div>

    <div class="flex h-full relative z-10">

        <div x-show="sidebarMobile" x-cloak x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click="sidebarMobile = false"
            class="fixed inset-0 bg-[#343F56]/60 z-40 lg:hidden backdrop-blur-sm">
        </div>

        @include('layouts.sidebar')

        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

            @include('layouts.header')

            <main class="flex-1 overflow-y-auto custom-scrollbar">
                <div class="py-6">
                    @yield('content')
                </div>
            </main>

            @include('layouts.footer')
        </div>
    </div>

    @yield('extra_js')

    <x-toast />

</body>

</html>