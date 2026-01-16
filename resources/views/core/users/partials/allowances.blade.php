<div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-gray-50">
        <h2 class="text-lg font-bold text-gray-900">Tunjangan</h2>
        <p class="text-sm text-gray-400">Informasi tunjangan pengguna.</p>
    </div>
    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Kehadiran
            </label>
            <input type="number" name="kehadiran" value="{{ $user->kehadiran ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Bonus Pribadi
            </label>
            <input type="number" name="bonus_pribadi" value="{{ $user->bonus_pribadi ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Bonus Team
            </label>
            <input type="number" name="bonus_team" value="{{ $user->bonus_team ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Tunjangan BPJS Sehat
            </label>
            <input type="number" name="tunjangan_bpjs_sehat" value="{{ $user->tunjangan_bpjs_sehat ?? '' }}"
                class="custom-input" placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Tunjangan BPJS Kerja
            </label>
            <input type="number" name="tunjangan_bpjs_kerja" value="{{ $user->tunjangan_bpjs_kerja ?? '' }}"
                class="custom-input" placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Tunjangan Pajak
            </label>
            <input type="number" name="tunjangan_pajak" value="{{ $user->tunjangan_pajak ?? '' }}" class="custom-input"
                placeholder="0">
        </div>
    </div>
    <div class="p-6 border-t border-gray-50 flex justify-end">
        <button type="submit"
            class="inline-flex items-center justify-center px-5 py-2.5 bg-[#FB9300] hover:bg-[#e68600] text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-orange-100 active:scale-95">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Simpan
        </button>
    </div>
</div>