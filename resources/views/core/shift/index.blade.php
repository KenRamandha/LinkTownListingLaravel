@extends('layouts.app')

@section('title', 'Shift Management')
@section('header', 'Shift Management')

@section('content')
    <div class="p-8 lg:p-12">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
            <div>
                <h1 class="text-3xl font-extrabold text-[#343F56] tracking-tight">Shift LIST</h1>
                <p class="text-gray-500 mt-2 text-sm font-medium">Create and manage working shifts for your organization.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <div class="relative group">
                    <span
                        class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 group-focus-within:text-[#FB9300] transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input type="text" id="customSearch"
                        class="pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:ring-4 focus:ring-[#FB9300]/5 focus:border-[#FB9300] transition-all w-64 shadow-sm font-medium text-[#343F56]"
                        placeholder="Search shifts...">
                </div>

                <a href="{{ route('shift.add') }}"
                    class="inline-flex items-center justify-center px-5 py-2.5 bg-[#FB9300] hover:bg-[#e68600] text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-orange-100 active:scale-95">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Shift
                </a>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] border border-gray-100 shadow-xl shadow-[#343F56]/5 overflow-hidden">
            <table id="shiftTable" class="table w-full text-left border-collapse"
                data-route-list="{{ route('shift.list') }}">
                <thead>
                    <tr>
                        <th class="px-8">Shift Name</th>
                        <th>Company</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Options</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                </tbody>
            </table>
        </div>
    </div>

    <x-loading id="shiftLoading" :show="true" />
@endsection

@section('extra_js')
    <script src="{{ asset('assets/js/custom/shift/index.js') }}"></script>
@endsection