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
                <input type="text" name="name" value="{{ $user->name ?? '' }}" class="custom-input"
                    placeholder="Contoh: John Doe">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Email
                </label>
                <input type="email" name="email" value="{{ $user->email ?? '' }}" class="custom-input"
                    placeholder="user@email.com">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    No. Handphone
                </label>
                <input type="text" name="phone" value="{{ $user->phone ?? '' }}" class="custom-input"
                    placeholder="08123456789">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Password {{ isset($user) ? '(Kosongkan jika tidak ingin diubah)' : '' }}
                </label>
                <input type="password" name="password" class="custom-input" placeholder="••••••••">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Company
                </label>
                <select id="company_id" name="company_id" class="custom-input select2"
                    data-route-departments="{{ route('user.departments', ':id') }}">
                    <option value="">Pilih Company...</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" {{ (isset($user) && $user->company_id == $company->id) ? 'selected' : '' }}>
                            {{ $company->code }} - {{ $company->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Departemen
                </label>
                <select id="department_id" name="department_id" class="custom-input select2"
                    data-selected="{{ $user->department_id ?? '' }}">
                    <option value="">Pilih Departemen...</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Employee Code
                </label>
                <input type="text" name="employee_code" value="{{ $user->employee_code ?? '' }}" class="custom-input"
                    placeholder="EMP001">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Position
                </label>
                <input type="text" name="position" value="{{ $user->position ?? '' }}" class="custom-input"
                    placeholder="Staff">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Join Date
                </label>
                <input type="date" name="join_date"
                    value="{{ isset($user->join_date) ? \Carbon\Carbon::parse($user->join_date)->format('Y-m-d') : '' }}"
                    class="custom-input">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Resign Date
                </label>
                <input type="date" name="resign_date"
                    value="{{ isset($user->resign_date) ? \Carbon\Carbon::parse($user->resign_date)->format('Y-m-d') : '' }}"
                    class="custom-input">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Gender
                </label>
                <select name="gender" class="custom-input select2">
                    <option value="">Pilih Gender...</option>
                    <option value="PRIA" {{ (isset($user) && $user->gender == 'PRIA') ? 'selected' : '' }}>PRIA</option>
                    <option value="WANITA" {{ (isset($user) && $user->gender == 'WANITA') ? 'selected' : '' }}>WANITA
                    </option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Tanggal Lahir
                </label>
                <input type="date" name="tanggal_lahir"
                    value="{{ isset($user->tanggal_lahir) ? \Carbon\Carbon::parse($user->tanggal_lahir)->format('Y-m-d') : '' }}"
                    class="custom-input">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Status
                </label>
                <select name="status" class="custom-input select2">
                    <option value="active" {{ (isset($user) && $user->status == 'active') ? 'selected' : '' }}>Active
                    </option>
                    <option value="suspended" {{ (isset($user) && $user->status == 'suspended') ? 'selected' : '' }}>
                        Suspended</option>
                    <option value="archived" {{ (isset($user) && $user->status == 'archived') ? 'selected' : '' }}>
                        Archived</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Akses Web
                </label>
                <select name="akses_web" class="custom-input select2">
                    <option value="YES" {{ (isset($user) && $user->akses_web == 'YES') ? 'selected' : '' }}>YES</option>
                    <option value="NO" {{ (isset($user) && $user->akses_web == 'NO') ? 'selected' : '' }}>NO</option>
                </select>
            </div>

            <!-- <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Role
                </label>
                <select name="role_id" class="custom-input select2">
                    <option value="">Pilih Role...</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ (isset($user) && $user->role_id == $role->id) ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div> -->

            <div class="flex items-center gap-2 mt-2">
                <input type="checkbox" name="is_employee" id="is_employee" value="1" {{ (isset($user) && $user->is_employee) ? 'checked' : '' }}
                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="is_employee" class="text-sm font-semibold text-gray-700">Is Employee</label>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Alamat KTP
                </label>
                <textarea name="alamat_ktp" rows="3" class="custom-textarea"
                    placeholder="Masukkan alamat lengkap KTP...">{{ $user->alamat_ktp ?? '' }}</textarea>
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