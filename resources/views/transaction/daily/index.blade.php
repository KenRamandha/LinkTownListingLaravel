@extends('layouts.app')

@section('title', 'Daily Transactions')
@section('header', 'Daily Transactions')

@section('content')
    <div class="px-8 lg:px-12 pb-8"
        x-data="{ activeTab: '{{ $companies->first()->id ?? '' }}', startDate: '{{ Carbon\Carbon::now()->startOfMonth()->format('Y-m-d') }}', endDate: '{{ Carbon\Carbon::now()->format('Y-m-d') }}'}">
        <input type="hidden" id="activeTabValue" :value="activeTab">
        <input type="hidden" id="startDateValue" :value="startDate">
        <input type="hidden" id="endDateValue" :value="endDate">

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-[#343F56] tracking-tight">Daily Transactions</h1>
                <p class="text-gray-500 mt-2 text-sm font-medium">Monitor daily and monthly transaction performance per
                    company.</p>
            </div>

            <div class="flex items-center gap-4 bg-white p-2 rounded-2xl border border-gray-100 shadow-sm">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-gray-400 uppercase px-2 mb-1">Date Range</span>
                    <div class="flex items-center gap-2 px-2 pb-1">
                        <input type="date" x-model="startDate"
                            class="text-sm font-bold text-[#343F56] border-none focus:ring-0 p-0 w-32 bg-transparent">
                        <span class="text-gray-300">to</span>
                        <input type="date" x-model="endDate"
                            class="text-sm font-bold text-[#343F56] border-none focus:ring-0 p-0 w-32 bg-transparent">
                    </div>
                </div>

                <button @click="$dispatch('filter-changed')"
                    class="p-3 bg-[#FB9300] hover:bg-[#e68600] text-white rounded-xl transition-all shadow-lg shadow-orange-100 active:scale-95">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2 scrollbar-hide">
            @foreach($companies as $company)
                <button @click="activeTab = '{{ $company->id }}'; $dispatch('tab-changed')"
                    :class="activeTab === '{{ $company->id }}' ? 'bg-[#343F56] text-white shadow-lg shadow-[#343F56]/20' : 'bg-white text-gray-500 border border-gray-100 hover:bg-gray-50'"
                    class="px-6 py-2.5 rounded-xl text-sm font-bold transition-all whitespace-nowrap">
                    {{ $company->name }}
                </button>
            @endforeach
        </div>

        @foreach($companies as $company)
            <div x-show="activeTab === '{{ $company->id }}'" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-[#343F56] flex items-center gap-2">
                                <span class="w-2 h-6 bg-[#FB9300] rounded-full"></span>
                                Today's Transactions
                            </h3>
                            <span
                                class="text-xs font-bold text-gray-400 uppercase tracking-widest">{{ Carbon\Carbon::today()->format('d M Y') }}</span>
                        </div>
                        <div
                            class="bg-white rounded-[2rem] border border-gray-100 shadow-xl shadow-[#343F56]/5 overflow-hidden">
                            <table class="table-daily w-full text-left border-collapse" data-company-id="{{ $company->id }}"
                                data-type="daily">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th class="px-6">Note</th>
                                        <th>Total</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-[#343F56] flex items-center gap-2">
                                <span class="w-2 h-6 bg-[#343F56] rounded-full"></span>
                                Accumulated Transactions
                            </h3>
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest"
                                x-text="moment(startDate).format('DD MMM') + ' - ' + moment(endDate).format('DD MMM YYYY')"></span>
                        </div>
                        <div
                            class="bg-white rounded-[2rem] border border-gray-100 shadow-xl shadow-[#343F56]/5 overflow-hidden">
                            <table class="table-monthly w-full text-left border-collapse" data-company-id="{{ $company->id }}"
                                data-type="monthly">
                                <thead>
                                    <tr>
                                        <th class="px-6">Date</th>
                                        <th>User</th>
                                        <th>Note</th>
                                        <th>Total</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Modal Detail -->
    <div id="modalDetail" class="fixed inset-0 z-[60] hidden opacity-0 transition-opacity duration-300"
        x-data="{ open: false }" @open-detail.window="open = true" @close-detail.window="open = false"
        :class="{ 'hidden': !open, 'opacity-100': open }">
        <div class="absolute inset-0 bg-[#343F56]/60 backdrop-blur-sm" @click="open = false"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-2xl bg-[#F8FAFC] shadow-2xl transition-transform duration-500 transform"
            :class="open ? 'translate-x-0' : 'translate-x-full'">
            <div class="h-full flex flex-col">
                <div class="px-8 py-6 bg-white border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-extrabold text-[#343F56]" id="detailTitle">Transaction Detail</h2>
                        <p class="text-sm text-gray-500 font-medium" id="detailSubtitle">Daily ID Detail</p>
                    </div>
                    <button @click="open = false"
                        class="p-2.5 rounded-xl bg-gray-50 text-gray-400 hover:text-red-500 hover:bg-red-50 transition-all">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
                    <div class="space-y-6">
                        <div class="grid gap-4">
                            <div class="bg-white p-4 rounded-2xl border border-gray-50 shadow-sm ">
                                <span class="text-left text-[10px] font-bold text-gray-400 uppercase">Total
                                    Transaction</span>
                                <p id="detailTotal" class="text-right text-xl font-black text-[#FB9300] mt-1"></p>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
                            <span class="text-[10px] font-bold text-gray-400 uppercase">Notes</span>
                            <p id="detailNotes" class="mt-2 text-sm text-[#343F56] font-medium leading-relaxed"></p>
                        </div>

                        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                            <table id="tableDetail" class="w-full text-left border-collapse">
                                <thead class="bg-gray-50/50">
                                    <tr>
                                        <th class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase">Product</th>
                                        <th class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase text-center">Qty
                                        </th>
                                        <th class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase text-right">Price
                                        </th>
                                        <th class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase text-right">
                                            Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-loading id="dailyLoading" :show="true" />
@endsection

@section('extra_js')
    <script src="{{ asset('assets/js/custom/transaction/daily.js') }}"></script>
@endsection