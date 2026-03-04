<div x-show="showAttendanceModal" x-cloak
    class="fixed inset-0 z-[60] flex items-center justify-center p-4 overflow-hidden">
    <div @click="showAttendanceModal = false" class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>

    <div x-show="showAttendanceModal" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-[1400px] max-h-[90vh] flex flex-col overflow-hidden border border-gray-100">

        <!-- Loading Overlay -->
        <div x-show="loadingAttendance" class="loading-overlay" x-cloak>
            <div class="flex flex-col items-center gap-4">
                <div class="spinner"></div>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-widest">Updating Attendance...</p>
            </div>
        </div>
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between bg-white shrink-0">
            <div>
                <h3 class="text-xl font-bold text-gray-900">Attendance Monitoring</h3>
                <p class="text-xs text-gray-500 font-medium mt-1">View user attendance records and logs.</p>
            </div>
            <button @click="showAttendanceModal = false"
                class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="px-8 py-6 bg-gray-50/50 border-b border-gray-100 shrink-0">
            <div class="flex flex-col lg:flex-row items-start gap-4">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1 w-full">
                    <div class="flex flex-col">
                        <label
                            class="filter-label block text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Select
                            Users</label>
                        <select id="at-user-select" class="select2-filter" multiple>
                            <option value="all">All Users</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <label
                            class="filter-label block text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Date
                            Range</label>
                        <div class="relative">
                            <input type="text" id="at-date-range" class="custom-input bg-white w-full pr-10"
                                placeholder="Select date range">
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 w-full lg:w-auto pt-[26px] shrink-0">
                    <button id="at-filter-btn"
                        class="flex-1 lg:flex-none h-[45px] px-6 bg-[#FB9300] text-white font-bold rounded-xl shadow-lg shadow-orange-100 hover:shadow-orange-200 hover:scale-[1.02] active:scale-95 transition-all text-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Apply Filter
                    </button>
                    <button id="at-export-btn"
                        class="flex-1 lg:flex-none h-[45px] px-6 bg-[#107C41] text-white font-bold rounded-xl shadow-lg shadow-green-100 hover:shadow-green-200 hover:scale-[1.02] active:scale-95 transition-all text-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export CSV
                    </button>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-8 bg-white custom-scrollbar">
            <div class="rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                <table id="at-datatable" class="w-full text-left border-collapse">
                    <thead class="bg-gray-50/80">
                        <tr>
                            <th class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider">User Name
                            </th>
                            <th class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider">Date</th>
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider text-center">
                                In</th>
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider text-center">
                                Out</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider">In
                                Address</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider">Out
                                Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 text-sm text-gray-600"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div x-show="showVisitModal" x-cloak
    class="fixed inset-0 z-[60] flex items-center justify-center p-4 sm:p-6 overflow-hidden">
    <div x-show="showVisitModal" @click="showVisitModal = false"
        class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>

    <div x-show="showVisitModal" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-[1400px] max-h-[90vh] flex flex-col overflow-hidden border border-gray-100">

        <!-- Loading Overlay -->
        <div x-show="loadingVisit" class="loading-overlay" x-cloak>
            <div class="flex flex-col items-center gap-4">
                <div class="spinner"></div>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-widest">Updating Visit Logs...</p>
            </div>
        </div>
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between shrink-0 bg-white">
            <div>
                <h3 class="text-xl font-bold text-gray-900">Visit Logs</h3>
                <p class="text-xs text-gray-500 font-medium mt-1">Detailed view of user location visits.</p>
            </div>
            <button @click="showVisitModal = false"
                class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="px-8 py-5 bg-gray-50/50 border-b border-gray-100 shrink-0">
            <div class="flex flex-col lg:flex-row items-start gap-4">
                <div class="w-full lg:flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col">
                        <label
                            class="filter-label block text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Select
                            Users</label>
                        <select id="vs-user-select" class="select2-filter" multiple>
                            <option value="all">All Users</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <label
                            class="filter-label block text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Date
                            Range</label>
                        <input type="text" id="vs-date-range" class="custom-input bg-white w-full"
                            placeholder="Select date range">
                    </div>
                </div>

                <div class="flex items-center gap-2 w-full lg:w-auto pt-[26px] shrink-0">
                    <button id="vs-filter-btn"
                        class="flex-1 lg:flex-none h-[45px] px-6 bg-[#FB9300] text-white font-bold rounded-xl shadow-lg shadow-orange-100 hover:shadow-orange-200 hover:scale-[1.02] active:scale-95 transition-all text-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Apply Filter
                    </button>
                    <button id="vs-export-btn"
                        class="flex-1 lg:flex-none h-[45px] px-6 bg-[#107C41] text-white font-bold rounded-xl shadow-lg shadow-green-100 hover:shadow-green-200 hover:scale-[1.02] active:scale-95 transition-all text-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export CSV
                    </button>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-8 custom-scrollbar bg-white">
            <div class="rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                <table id="vs-datatable" class="w-full text-left border-collapse">
                    <thead class="bg-gray-50/80">
                        <tr>
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider text-left">
                                User Name</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider">Date</th>
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider text-left">
                                Visit In</th>
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider text-left">
                                Visit Out</th>
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider text-left">
                                Address In</th>
                            <th
                                class="px-6 py-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider text-left">
                                Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 text-sm text-gray-600"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>