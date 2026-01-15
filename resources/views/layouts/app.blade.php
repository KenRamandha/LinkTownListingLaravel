<!DOCTYPE html>
<html lang="en" class="h-full">

@include('layouts.head')

<body class="bg-[#F9FAFB] text-gray-900 h-full overflow-hidden" x-data="{ sidebarMobile: false, sidebarDesktop: true }">

    <div class="flex h-full">
        <div x-show="sidebarMobile" x-cloak @click="sidebarMobile = false"
            class="fixed inset-0 bg-gray-900/40 z-40 lg:hidden transition-opacity backdrop-blur-sm"></div>

        @include('layouts.sidebar')

        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            @include('layouts.header')

            <main class="flex-1 overflow-y-auto">
                @yield('content')
            </main>
        </div>
    </div>

    @yield('extra_js')
</body>

</html>