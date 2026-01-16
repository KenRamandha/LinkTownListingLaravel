<form action="" method="POST">
    @csrf
    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-50">
            <h2 class="text-lg font-bold text-gray-900">Data Pengguna</h2>
            <p class="text-sm text-gray-400">Informasi dasar pengguna aplikasi.</p>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Nama Lengkap
                </label>
                <input type="text" name="name" class="custom-input" placeholder="Contoh: John Doe">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Email
                </label>
                <input type="email" name="email" class="custom-input" placeholder="user@email.com">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    No. Handphone
                </label>
                <input type="text" name="phone" class="custom-input" placeholder="08123456789">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Password
                </label>
                <input type="password" name="password" class="custom-input" placeholder="••••••••">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Company
                </label>
                <select id="company_id" name="company_id" class="custom-input select2"
                    data-route-departments="{{ route('user.departements', ':id') }}">
                    <option value="">Pilih Company...</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}">
                            {{ $company->code }} - {{ $company->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Departement
                </label>
                <select id="departement_id" name="departement_id" class="custom-input select2">
                    <option value="">Pilih Departement...</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Alamat
                </label>
                <textarea name="address" rows="3" class="custom-textarea"
                    placeholder="Masukkan alamat lengkap..."></textarea>
            </div>
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