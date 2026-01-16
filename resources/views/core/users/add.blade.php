@extends('layouts.app')

@section('title', 'Tambah User')
@section('header', 'User Management')

@section('content')
    <div class="p-8 lg:p-12" x-data="{ loaded: false }"
        x-init="setTimeout(() => { loaded = true; window.toast('success', 'Form loaded successfully'); }, 1000)">

        <div x-show="!loaded" class="animate-pulse space-y-10">
            <div class="flex justify-between items-end">
                <div class="space-y-3">
                    <div class="h-8 w-64 bg-gray-200 rounded-lg"></div>
                    <div class="h-4 w-96 bg-gray-100 rounded-lg"></div>
                </div>
                <div class="h-10 w-32 bg-gray-200 rounded-xl"></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div>
                    <div class="bg-white rounded-[2rem] border border-gray-100 p-6 space-y-6">
                        <div class="space-y-2">
                            <div class="h-5 w-32 bg-gray-200 rounded"></div>
                            <div class="h-3 w-48 bg-gray-100 rounded"></div>
                        </div>
                        <div class="flex flex-col items-center space-y-4">
                            <div class="h-32 w-32 rounded-full bg-gray-200"></div>
                            <div class="h-3 w-40 bg-gray-100 rounded"></div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-8">
                    <div class="bg-white rounded-[2rem] border border-gray-100 p-6 space-y-4">
                        <div class="space-y-2">
                            <div class="h-5 w-32 bg-gray-200 rounded"></div>
                            <div class="h-3 w-48 bg-gray-100 rounded"></div>
                        </div>
                        <div class="space-y-3">
                            <div class="h-12 w-full bg-gray-50 rounded-xl"></div>
                            <div class="h-12 w-full bg-gray-50 rounded-xl"></div>
                            <div class="h-12 w-full bg-gray-50 rounded-xl"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="loaded" style="display: none;" x-transition.opacity.duration.500ms>
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
                <div>
                    <h1 class="text-3xl font-extrabold text-[#343F56] tracking-tight">Tambah User</h1>
                    <p class="text-gray-500 mt-2 text-sm font-medium">Tambahkan user baru</p>
                </div>

                <div class="flex items-center gap-3">
                    <!-- <button type="submit"
                                class="w-full inline-flex items-center justify-center px-5 py-2.5 bg-[#FB9300] hover:bg-[#e68600] text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-orange-100 active:scale-95">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Simpan Semua Data
                            </button> -->
                    <a href="{{ route('users.index') }}"
                        class="inline-flex items-center justify-center px-6 py-2.5 bg-white border border-gray-200 hover:bg-gray-50 text-[#343F56] text-sm font-bold rounded-xl transition-all shadow-sm">
                        Kembali
                    </a>
                </div>
            </div>

            <form id="userForm" action="{{ isset($user) ? route('users.update', $user->id) : route('users.store') }}"
                method="POST" enctype="multipart/form-data">
                @csrf
                @if(isset($user))
                    @method('PUT')
                @endif
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="space-y-8">
                        <div
                            class="bg-white rounded-[2rem] border border-gray-100 shadow-xl shadow-[#343F56]/5 overflow-hidden">
                            <div class="p-6 border-b border-gray-50">
                                <h2 class="text-lg font-bold text-[#343F56]">Profile User</h2>
                                <p class="text-xs text-gray-400 font-medium">Foto profile pengguna.</p>
                            </div>

                            <div class="p-8 flex flex-col items-center gap-6">
                                <div class="relative group">
                                    <img id="preview-image"
                                        src="{{ isset($user->avatar_url) ? $user->avatar_url : 'https://ui-avatars.com/api/?name=User&background=FB9300&color=fff' }}"
                                        class="w-36 h-36 rounded-full object-cover border-4 border-white shadow-xl shadow-orange-100 transition-transform group-hover:scale-105">

                                    <label
                                        class="absolute bottom-1 right-1 bg-[#FB9300] hover:bg-[#e68600] text-white p-2.5 rounded-full cursor-pointer shadow-lg transition-all active:scale-90">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15.232 5.232l3.536 3.536M9 11l6-6m2 8v6a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2h6" />
                                        </svg>
                                        <input type="file" name="avatar" class="hidden" accept="image/*"
                                            onchange="previewImage(event)">
                                    </label>
                                </div>

                                <p class="text-xs text-gray-500 text-center leading-relaxed">
                                    <span class="font-bold text-[#343F56]">Upload foto profile</span> <br>
                                    <span class="text-[10px] text-gray-400 uppercase tracking-tighter">(JPG, PNG – MAX
                                        2MB)</span>
                                </p>
                            </div>
                        </div>

                        <div
                            class="bg-white rounded-[2rem] border border-gray-100 shadow-xl shadow-[#343F56]/5 overflow-hidden">
                            <div class="p-6 border-b border-gray-50 bg-gray-50/50">
                                <h2 class="text-lg font-bold text-[#343F56]">Konfigurasi</h2>
                                <p class="text-xs text-gray-400 font-medium">Lengkapi detail informasi user.</p>
                            </div>
                            <div class="p-4">
                                <nav class="flex flex-col space-y-1">
                                    <a href="javascript:void(0)" data-target="general-info"
                                        class="tab-link flex items-center justify-between px-5 py-3.5 text-[#FB9300] bg-orange-50 rounded-2xl transition-all group active-tab">
                                        <span class="font-bold text-sm">Informasi Umum</span>
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>

                                    @php
                                        $menus = [
                                            ['target' => 'attachment', 'label' => 'Attachment & Status'],
                                            ['target' => 'locations', 'label' => 'Lokasi'],
                                            ['target' => 'salary', 'label' => 'Gaji'],
                                            ['target' => 'allowances', 'label' => 'Tunjangan'],
                                            ['target' => 'permissions', 'label' => 'Izin/Cuti/Sakit'],
                                        ];
                                    @endphp

                                    @foreach($menus as $menu)
                                        <a href="javascript:void(0)" data-target="{{ $menu['target'] }}"
                                            class="tab-link flex items-center justify-between px-5 py-3.5 text-gray-500 hover:bg-gray-50 hover:text-[#FB9300] rounded-2xl transition-all group">
                                            <span class="font-bold text-sm">{{ $menu['label'] }}</span>
                                            <svg class="w-4 h-4 text-gray-300 group-hover:text-[#FB9300] transition-colors"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </a>
                                    @endforeach
                                </nav>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <div
                            class="bg-white rounded-[2rem] border border-gray-100 shadow-xl shadow-[#343F56]/5 p-8 min-h-[500px]">
                            <div id="general-info" class="tab-content">
                                @include('core.users.partials.general-info')
                            </div>

                            <div id="attachment" class="tab-content hidden">
                                @include('core.users.partials.attachment')
                            </div>

                            <div id="locations" class="tab-content hidden">
                                @include('core.users.partials.locations')
                            </div>

                            <div id="salary" class="tab-content hidden">
                                @include('core.users.partials.salary')
                            </div>

                            <div id="allowances" class="tab-content hidden">
                                @include('core.users.partials.allowances')
                            </div>

                            <div id="permissions" class="tab-content hidden">
                                @include('core.users.partials.permissions')
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('extra_js')
    <script src="{{ asset('assets/js/custom/users/add.js') }}"></script>
@endsection