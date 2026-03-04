@extends('layouts.app')

@section('title', isset($shift) ? 'Edit Shift' : 'Tambah Shift')
@section('header', 'Shift Management')

@section('content')
    <div class="p-8 lg:p-12" x-data="{ loaded: false }" x-init="setTimeout(() => { loaded = true; }, 500)">

        <div x-show="!loaded" class="animate-pulse space-y-10">
            <div class="flex justify-between items-end">
                <div class="space-y-3">
                    <div class="h-8 w-64 bg-gray-200 rounded-lg"></div>
                    <div class="h-4 w-96 bg-gray-100 rounded-lg"></div>
                </div>
                <div class="h-10 w-32 bg-gray-200 rounded-xl"></div>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 p-8 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <div class="h-5 w-32 bg-gray-200 rounded"></div>
                        <div class="h-12 w-full bg-gray-50 rounded-xl"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="h-5 w-32 bg-gray-200 rounded"></div>
                        <div class="h-12 w-full bg-gray-50 rounded-xl"></div>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="loaded" style="display: none;" x-transition.opacity.duration.500ms>
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
                <div>
                    <h1 class="text-3xl font-extrabold text-[#343F56] tracking-tight">
                        {{ isset($shift) ? 'Edit Shift' : 'Tambah Shift' }}
                    </h1>
                    <p class="text-gray-500 mt-2 text-sm font-medium">
                        {{ isset($shift) ? 'Perbarui detail data shift.' : 'Tambahkan shift kerja baru.' }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('shift.index') }}"
                        class="inline-flex items-center justify-center px-6 py-2.5 bg-white border border-gray-200 hover:bg-gray-50 text-[#343F56] text-sm font-bold rounded-xl transition-all shadow-sm">
                        Kembali
                    </a>
                </div>
            </div>

            <form id="shiftForm" action="{{ isset($shift) ? route('shift.update', $shift->id) : route('shift.store') }}"
                method="POST">
                @csrf
                @if(isset($shift))
                    @method('PUT')
                @endif

                <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-50">
                        <h2 class="text-lg font-bold text-gray-900">Data Shift</h2>
                        <p class="text-sm text-gray-400">Informasi detail shift kerja.</p>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        {{-- Company --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Company
                            </label>
                            <select id="company_id" name="company_id" class="custom-input select2" required>
                                <option value="">Pilih Company...</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ (isset($shift) && $shift->company_id == $company->id) ? 'selected' : '' }}>
                                        {{ $company->code }} - {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Shift Name --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Nama Shift
                            </label>
                            <input type="text" name="name" value="{{ $shift->name ?? '' }}" class="custom-input"
                                placeholder="Contoh: Shift Pagi" required>
                        </div>

                        {{-- Start Time --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Jam Mulai
                            </label>
                            <input type="time" name="start_time"
                                value="{{ isset($shift) ? (is_string($shift->start_time) ? substr($shift->start_time, 0, 5) : $shift->start_time->format('H:i')) : '' }}"
                                class="custom-input" required>
                        </div>

                        {{-- End Time --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Jam Selesai
                            </label>
                            <input type="time" name="end_time"
                                value="{{ isset($shift) ? (is_string($shift->end_time) ? substr($shift->end_time, 0, 5) : $shift->end_time->format('H:i')) : '' }}"
                                class="custom-input" required>
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
            </form>
        </div>
    </div>
@endsection

@section('extra_js')
    <script>
        $(document).ready(function () {
            $('.select2').select2({
                placeholder: "Pilih Company",
                allowClear: true,
                width: '100%'
            });

            $('#shiftForm').on('submit', function (e) {
                e.preventDefault();
                const form = $(this);
                const url = form.attr('action');
                const method = form.find('input[name="_method"]').val() || 'POST';
                const data = form.serialize();

                $.ajax({
                    url: url,
                    type: method,
                    data: data,
                    success: function (response) {
                        window.toast('success', response.message);
                        setTimeout(() => {
                            window.location.href = "{{ route('shift.index') }}";
                        }, 1000);
                    },
                    error: function (xhr) {
                        let message = xhr.responseJSON ? xhr.responseJSON.message : 'Something went wrong';
                        window.toast('error', message);
                    }
                });
            });
        });
    </script>
@endsection