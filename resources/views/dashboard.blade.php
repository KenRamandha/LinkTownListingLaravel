@extends('layouts.app')

@section('title', 'Dashboard')
@section('header', 'Overview')

@section('content')
    <div id="dashboard-container" class="p-8 lg:p-12" x-data="{ 
        showAttendanceModal: false, 
        showVisitModal: false,
        loadingAttendance: false,
        loadingVisit: false,
        openAttendanceModal(userId) {
            this.showAttendanceModal = true;
            this.$nextTick(() => {
                $('#at-user-select').val(userId ? [userId] : ['all']).trigger('change');
                atTable.ajax.reload();
            });
        },
        openVisitModal(userId) {
            this.showVisitModal = true;
            this.$nextTick(() => {
                $('#vs-user-select').val(userId ? [userId] : ['all']).trigger('change');
                vsTable.ajax.reload();
            });
        }
    }">
        <div class="mb-10">
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Welcome back, {{ Auth::user()->name ?? 'User' }}!
            </h1>
            <p class="text-gray-500 mt-2 text-sm">Here's what's happening in your workspace today.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Total Users -->
            <a href="{{ route('users.index') }}"
                class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition-shadow group">
                <div class="flex items-center gap-4 mb-4">
                    <div
                        class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors border border-blue-100">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-gray-900">{{ number_format($totalUser) }}</h3>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mt-1">Total Users</p>
                </div>
            </a>

            <!-- Total Transaksi -->
            <a href="{{ route('transaction.daily.index') }}"
                class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition-shadow group">
                <div class="flex items-center gap-4 mb-4">
                    <div
                        class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-colors border border-indigo-100">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-gray-900">{{ number_format($totalTransaksi) }}</h3>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mt-1">Transaksi Hari Ini</p>
                </div>
            </a>

            <!-- Total Absensi -->
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition-shadow group cursor-pointer"
                @click="openAttendanceModal('')">
                <div class="flex items-center gap-4 mb-4">
                    <div
                        class="w-12 h-12 rounded-2xl bg-green-50 flex items-center justify-center text-green-600 group-hover:bg-green-600 group-hover:text-white transition-colors border border-green-100">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-gray-900">{{ number_format($totalAbsensi) }}</h3>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mt-1">Absensi Hari Ini</p>
                </div>
            </div>

            <!-- Total Visit -->
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition-shadow group cursor-pointer"
                @click="openVisitModal('')">
                <div class="flex items-center gap-4 mb-4">
                    <div
                        class="w-12 h-12 rounded-2xl bg-orange-50 flex items-center justify-center text-orange-600 group-hover:bg-orange-600 group-hover:text-white transition-colors border border-orange-100">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.244a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-gray-900">{{ number_format($totalVisit) }}</h3>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mt-1">Visit Hari Ini</p>
                </div>
            </div>
        </div>

        <div class="mt-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Daily User Status</h2>
                <span
                    class="px-4 py-1.5 bg-gray-100 text-gray-600 text-xs font-bold rounded-full uppercase tracking-widest">
                    {{ now()->format('d M Y') }}
                </span>
            </div>

            <div id="activity-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @include('partials.activity-cards', ['userActivities' => $userActivities])
            </div>

            @if ($userActivities->hasMorePages())
                <div class="mt-12 text-center">
                    <button id="load-more" data-page="2"
                        class="px-8 py-3 bg-white border border-gray-200 text-gray-900 font-bold rounded-2xl shadow-sm hover:shadow-md hover:border-blue-200 hover:text-blue-600 transition-all active:scale-95">
                        Load More Activities
                    </button>
                    <p id="loading-text" class="hidden text-gray-400 font-medium mt-4">Loading more activities...</p>
                </div>
            @endif
        </div>

        @include('partials.dashboard-modals', ['users' => $users])
    </div>

    <script>
        window.DashboardConfig = {
            homeUrl: "{{ route('home') }}",
            attendanceDataUrl: "{{ route('dashboard.attendance-data') }}",
            visitDataUrl: "{{ route('dashboard.visit-data') }}"
        };
    </script>
    <script src="{{ asset('assets/js/custom/dashboard/dashboard.js') }}"></script>
@endsection