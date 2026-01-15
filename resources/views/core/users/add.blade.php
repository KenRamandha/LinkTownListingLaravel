@extends('layouts.app')

@section('title', 'Users Management')
@section('header', 'User Management')

@section('content')
    <div class="p-8 lg:p-12">
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Tambah User</h1>
                <p class="text-gray-500 mt-2 text-sm">Tambahkan user baru dan atur hak akses menu.</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('users.index') }}"
                    class="inline-flex items-center justify-center px-5 py-2.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-bold rounded-xl transition-all">
                    Kembali
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
                            <img id="preview-image" src="https://ui-avatars.com/api/?name=User&background=2563eb&color=fff"
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

                <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden mt-8">
                    <div class="p-6 border-b border-gray-50">
                        <h2 class="text-lg font-bold text-gray-900">Menu Lainnya</h2>
                        <p class="text-sm text-gray-400">Informasi tambahan pengguna.</p>
                    </div>
                    <div class="p-4">
                        <nav class="flex flex-col space-y-1">
                            <a href="javascript:void(0)" data-target="general-info"
                                class="tab-link flex items-center justify-between px-4 py-3 text-blue-600 bg-blue-50 rounded-xl transition-all group">
                                <span class="font-semibold text-sm">Informasi Umum</span>
                                <svg class="w-4 h-4 text-blue-600 transition-colors" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                            <a href="javascript:void(0)" data-target="attachment"
                                class="tab-link flex items-center justify-between px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-blue-600 rounded-xl transition-all group">
                                <span class="font-semibold text-sm">Attachment</span>
                                <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-colors" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                            <a href="javascript:void(0)" data-target="locations"
                                class="tab-link flex items-center justify-between px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-blue-600 rounded-xl transition-all group">
                                <span class="font-semibold text-sm">Lokasi</span>
                                <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-colors" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                            <a href="javascript:void(0)" data-target="salary"
                                class="tab-link flex items-center justify-between px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-blue-600 rounded-xl transition-all group">
                                <span class="font-semibold text-sm">Gaji</span>
                                <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-colors" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                            <a href="javascript:void(0)" data-target="allowances"
                                class="tab-link flex items-center justify-between px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-blue-600 rounded-xl transition-all group">
                                <span class="font-semibold text-sm">Tunjangan</span>
                                <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-colors" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                            <a href="javascript:void(0)" data-target="permissions"
                                class="tab-link flex items-center justify-between px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-blue-600 rounded-xl transition-all group">
                                <span class="font-semibold text-sm">Izin/Cuti/Sakit</span>
                                <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-colors" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </nav>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <!-- Tab Content: Informasi Umum -->
                <div id="general-info" class="tab-content">
                    @include('core.users.partials.general-info')
                </div>

                <!-- Tab Content: Attachment -->
                <div id="attachment" class="tab-content hidden">
                    @include('core.users.partials.attachment')
                </div>

                <!-- Tab Content: Lokasi -->
                <div id="locations" class="tab-content hidden">
                    @include('core.users.partials.locations')
                </div>

                <!-- Tab Content: Gaji -->
                <div id="salary" class="tab-content hidden">
                    @include('core.users.partials.salary')
                </div>

                <!-- Tab Content: Tunjangan -->
                <div id="allowances" class="tab-content hidden">
                    @include('core.users.partials.allowances')
                </div>

                <!-- Tab Content: Izin/Cuti/Sakit -->
                <div id="permissions" class="tab-content hidden">
                    @include('core.users.partials.permissions')
                </div>
            </div>

        </div>

    </div>
@endsection

@section('extra_js')
    <script>
        $(document).ready(function () {
            $('.tab-link').on('click', function (e) {
                e.preventDefault();

                let targetId = $(this).data('target');

                $('.tab-link').removeClass('text-blue-600 bg-blue-50').addClass('text-gray-600 hover:bg-gray-50 hover:text-blue-600');

                $('.tab-link svg').removeClass('text-blue-600').addClass('text-gray-400 group-hover:text-blue-600');

                $(this).removeClass('text-gray-600 hover:bg-gray-50 hover:text-blue-600').addClass('text-blue-600 bg-blue-50');

                $(this).find('svg').removeClass('text-gray-400 group-hover:text-blue-600').addClass('text-blue-600');

                $('.tab-content').addClass('hidden');

                $('#' + targetId).removeClass('hidden');
            });

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