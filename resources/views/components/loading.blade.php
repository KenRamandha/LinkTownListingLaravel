@props(['id' => 'loadingOverlay', 'show' => false])

<div id="{{ $id }}"
    class="fixed inset-0 bg-[#343F56]/20 backdrop-blur-sm z-50 flex items-center justify-center {{ $show ? '' : 'hidden opacity-0' }} transition-opacity duration-300">

    <div
        class="bg-white/90 backdrop-blur-md p-10 rounded-[2.5rem] flex flex-col items-center justify-center shadow-2xl shadow-[#343F56]/20 border border-white">

        <div class="relative w-16 h-16">
            <div class="absolute inset-0 border-4 border-[#FB9300]/20 rounded-full animate-ping"></div>

            <div class="absolute inset-0 border-4 border-[#FB9300] rounded-full border-t-transparent animate-spin">
            </div>
        </div>

        <p class="mt-6 text-[#343F56] font-extrabold tracking-widest uppercase text-xs animate-pulse">
            Loading....
        </p>
    </div>
</div>

<script>
    window.showLoading = (id = 'loadingOverlay') => {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('hidden');
            el.offsetHeight;
            requestAnimationFrame(() => {
                el.classList.remove('opacity-0');
            });
        }
    }

    window.hideLoading = (id = 'loadingOverlay') => {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('opacity-0');
            setTimeout(() => {
                el.classList.add('hidden');
            }, 300);
        }
    }
</script>