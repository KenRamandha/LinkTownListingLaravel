<div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-gray-50">
        <h2 class="text-lg font-bold text-gray-900">Izin / Cuti / Sakit</h2>
        <p class="text-sm text-gray-400">Informasi kuota izin dan potongan.</p>
    </div>
    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Kuota Izin Cuti
            </label>
            <input type="number" name="izin_cuti" value="{{ $user->izin_cuti ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Izin Telat (Kali)
            </label>
            <input type="number" name="izin_telat" value="{{ $user->izin_telat ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Izin Masuk (Kali)
            </label>
            <input type="number" name="izin_masuk" value="{{ $user->izin_masuk ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Izin Pulang (Kali)
            </label>
            <input type="number" name="izin_pulang" value="{{ $user->izin_pulang ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Potongan Izin
            </label>
            <input type="number" name="pot_izin" value="{{ $user->pot_izin ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Potongan Mangkir
            </label>
            <input type="number" name="pot_mangkir" value="{{ $user->pot_mangkir ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Potongan Telat
            </label>
            <input type="number" name="pot_telat" value="{{ $user->pot_telat ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Potongan Kasbon
            </label>
            <input type="number" name="pot_kasbon" value="{{ $user->pot_kasbon ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Potongan BPJS Sehat
            </label>
            <input type="number" name="pot_bpjs_sehat" value="{{ $user->pot_bpjs_sehat ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Potongan BPJS Kerja
            </label>
            <input type="number" name="pot_bpjs_kerja" value="{{ $user->pot_bpjs_kerja ?? '' }}" class="custom-input"
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