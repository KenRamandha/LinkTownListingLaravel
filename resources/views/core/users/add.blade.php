@extends('layouts.app')

@section('title', 'Users Management')
@section('header', 'User Management')

@section('content')
    <div class="p-8 lg:p-12">
        <form action="" method="POST" enctype="multipart/form-data">
            @csrf

            <!-- HEADER -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Tambah User</h1>
                    <p class="text-gray-500 mt-2 text-sm">Tambahkan user baru dan atur hak akses menu.</p>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit"
                        class="inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-blue-200 active:scale-95">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Simpan
                    </button>

                    <a href="{{ route('users.index') }}"
                        class="inline-flex items-center justify-center px-5 py-2.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-bold rounded-xl transition-all">
                        Batal
                    </a>
                </div>
            </div>

            <!-- GRID -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div>
                    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-gray-50">
                            <h2 class="text-lg font-bold text-gray-900">Profile User</h2>
                            <p class="text-sm text-gray-400">Foto profile pengguna.</p>
                        </div>

                        <div class="p-6 flex flex-col items-center gap-5">
                            <div class="relative">
                                <img id="preview-image"
                                    src="https://ui-avatars.com/api/?name=User&background=2563eb&color=fff"
                                    class="w-32 h-32 rounded-full object-cover border-4 border-white shadow">

                                <label
                                    class="absolute bottom-0 right-0 bg-blue-600 hover:bg-blue-700 text-white p-2 rounded-full cursor-pointer shadow">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.232 5.232l3.536 3.536M9 11l6-6m2 8v6a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2h6" />
                                    </svg>
                                    <input type="file" name="profile_photo" class="hidden" accept="image/*"
                                        onchange="previewImage(event)">
                                </label>
                            </div>

                            <p class="text-sm text-gray-500 text-center">
                                Upload foto profile <br>
                                <span class="text-xs">(JPG, PNG – max 2MB)</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2">
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
                                <select id="company_id" name="company_id" class="custom-input select2">
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
                    </div>
                </div>

            </div>
        </form>
    </div>
@endsection

@section('extra_js')
    <script>
        $(document).ready(function () {
            $('.select2').select2({
                width: '100%',
                allowClear: true
            });

            $('#company_id').on('change', function () {
                let companyId = $(this).val();
                let departementSelect = $('#departement_id');

                departementSelect.html('<option>Loading...</option>').trigger('change');

                if (!companyId) {
                    departementSelect.html('<option value="">Pilih Departement...</option>');
                    return;
                }

                $.ajax({
                    url: "{{ route('user.departements', ':id') }}".replace(':id', companyId),
                    type: 'GET',
                    success: function (data) {
                        departementSelect.empty().append('<option value="">Pilih Departement...</option>');
                        $.each(data, function (i, dep) {
                            departementSelect.append(`<option value="${dep.id}">${dep.name}</option>`);
                        });
                        departementSelect.trigger('change');
                    },
                    error: function () {
                        departementSelect.html('<option>Gagal memuat departement</option>');
                    }
                });
            });
        });

        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function () {
                document.getElementById('preview-image').src = reader.result;
            };
            reader.readAsDataURL(event.target.files[0]);
        }
    </script>
@endsection