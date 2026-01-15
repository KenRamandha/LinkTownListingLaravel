<form action="" method="POST">
    @csrf
    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-50">
            <h2 class="text-lg font-bold text-gray-900">Lokasi</h2>
            <p class="text-sm text-gray-400">Pengaturan lokasi pengguna.</p>
        </div>
        <div class="p-6">
            <p class="text-gray-500 italic text-center py-8">Belum ada fitur lokasi.</p>
        </div>
        <div class="p-6 border-t border-gray-50 flex justify-end">
            <button type="submit"
                class="inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-blue-200 active:scale-95">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Simpan
            </button>
        </div>
    </div>
</form>