<div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-gray-50">
        <h2 class="text-lg font-bold text-gray-900">Gaji</h2>
        <p class="text-sm text-gray-400">Informasi gaji pengguna.</p>
    </div>
    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Gaji Pokok
            </label>
            <input type="number" name="gaji_pokok" value="{{ $user->gaji_pokok ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Lembur
            </label>
            <input type="number" name="lembur" value="{{ $user->lembur ?? '' }}" class="custom-input" placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Transport
            </label>
            <input type="number" name="transport" value="{{ $user->transport ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                THR
            </label>
            <input type="number" name="thr" value="{{ $user->thr ?? '' }}" class="custom-input" placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Bank
            </label>
            <select name="bank" class="custom-input select2">
                <option value="">Pilih Bank...</option>
                @foreach($banks as $bank)
                    <option value="{{ $bank->stat_name }}" {{ (isset($user) && $user->bank == $bank->stat_name) ? 'selected' : '' }}>
                        {{ $bank->stat_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. Rekening
            </label>
            <input type="number" name="no_rekening" value="{{ $user->no_rekening ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Nama Rekening
            </label>
            <input type="text" name="nama_rekening" value="{{ $user->nama_rekening ?? '' }}" class="custom-input"
                placeholder="Contoh: John Doe">
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