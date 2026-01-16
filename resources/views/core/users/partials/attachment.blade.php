<div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-gray-50">
        <h2 class="text-lg font-bold text-gray-900">Attachment & Status</h2>
        <p class="text-sm text-gray-400">Informasi dokumen dan status kekaryawanan.</p>
    </div>
    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. KTP
            </label>
            <input type="text" name="no_ktp" value="{{ $user->no_ktp ?? '' }}" class="custom-input" placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. NPWP
            </label>
            <input type="text" name="no_npwp" value="{{ $user->no_npwp ?? '' }}" class="custom-input" placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. BPJS Sehat
            </label>
            <input type="text" name="no_bpjs_sehat" value="{{ $user->no_bpjs_sehat ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. BPJS Kerja
            </label>
            <input type="text" name="no_bpjs_kerja" value="{{ $user->no_bpjs_kerja ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. Kontrak
            </label>
            <input type="text" name="no_kontrak" value="{{ $user->no_kontrak ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. PKWT
            </label>
            <input type="text" name="no_pkwt" value="{{ $user->no_pkwt ?? '' }}" class="custom-input" placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Mulai PKWT
            </label>
            <input type="date" name="mulai_pkwt"
                value="{{ isset($user->mulai_pkwt) ? \Carbon\Carbon::parse($user->mulai_pkwt)->format('Y-m-d') : '' }}"
                class="custom-input">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Akhir PKWT
            </label>
            <input type="date" name="akhir_pkwt"
                value="{{ isset($user->akhir_pkwt) ? \Carbon\Carbon::parse($user->akhir_pkwt)->format('Y-m-d') : '' }}"
                class="custom-input">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Status Nikah
            </label>
            <select name="stat_nikah" class="custom-input select2">
                <option value="">Pilih Status...</option>
                @foreach($marriage_stats as $stat)
                    <option value="{{ $stat->stat_name }}" {{ (isset($user) && $user->stat_nikah == $stat->stat_name) ? 'selected' : '' }}>
                        {{ $stat->stat_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Status Pajak
            </label>
            <select name="stat_pajak" class="custom-input select2">
                <option value="">Pilih Status...</option>
                @foreach($tax_stats as $stat)
                    <option value="{{ $stat->stat_name }}" {{ (isset($user) && $user->stat_pajak == $stat->stat_name) ? 'selected' : '' }}>
                        {{ $stat->stat_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Status Karyawan
            </label>
            <select name="stat_karyawan" class="custom-input select2">
                <option value="">Pilih Status...</option>
                @foreach($employee_stats as $stat)
                    <option value="{{ $stat->stat_name }}" {{ (isset($user) && $user->stat_karyawan == $stat->stat_name) ? 'selected' : '' }}>
                        {{ $stat->stat_name }}
                    </option>
                @endforeach
            </select>
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