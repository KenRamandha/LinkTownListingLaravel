<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h3 class="text-xl font-bold text-[#343F56]">Shift Mapping</h3>
            <p class="text-gray-500 text-sm font-medium mt-1">Atur jadwal shift kerja untuk pegawai ini.</p>
        </div>
    </div>

    @if(isset($user))
        <div id="mappingFormContainer" class="bg-gray-50/50 rounded-3xl p-6 border border-gray-100">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">

                <div class="md:col-span-12 space-y-2">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Pilih
                        Shift</label>
                    <div class="relative">
                        <select id="mapping_shift_id" name="shift_id"
                            class="w-full h-11 px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 transition-all outline-none appearance-none select2-no-search">
                            <option value="">Pilih Shift</option>
                            @foreach($shifts as $shift)
                                <option value="{{ $shift->id }}">{{ $shift->name }}
                                    ({{ \Carbon\Carbon::parse($shift->start_time)->format('H:i') }} -
                                    {{ \Carbon\Carbon::parse($shift->end_time)->format('H:i') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="md:col-span-10 space-y-2">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Rentang
                        Tanggal</label>
                    <div class="flex flex-col md:flex-row md:items-center gap-4">
                        <div class="flex items-center gap-3 flex-1">
                            <div class="relative flex-1">
                                <input type="date" id="mapping_start_date" name="start_date"
                                    class="w-full h-11 px-4 py-2 bg-white border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none"
                                    min="{{ Carbon\Carbon::tomorrow()->format('Y-m-d') }}">
                            </div>

                            <span class="text-gray-400 font-bold text-xs shrink-0">s/d</span>

                            <div class="relative flex-1">
                                <input type="date" id="mapping_end_date" name="end_date"
                                    class="w-full h-11 px-4 py-2 bg-white border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none"
                                    min="{{ Carbon\Carbon::tomorrow()->format('Y-m-d') }}">
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-xl h-11 shrink-0">
                            <input type="checkbox" id="mapping_lock_location" name="lock_location" value="1"
                                class="w-4 h-4 text-orange-600 bg-gray-100 border-gray-300 rounded focus:ring-orange-500 focus:ring-2">
                            <label for="mapping_lock_location"
                                class="text-xs font-bold text-[#343F56] whitespace-nowrap">Lock Location</label>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <button type="button" onclick="saveMapping()"
                        class="w-full h-11 flex items-center justify-center px-6 bg-[#FB9300] hover:bg-[#e68600] text-white text-sm font-bold rounded-xl transition-all shadow-md shadow-orange-200 active:scale-[0.98]">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        Simpan
                    </button>
                </div>

            </div>
        </div>

        <div class="mt-8">
            <div class="overflow-x-auto">
                <table id="mappingTable" class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th
                                class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100">
                                Tanggal Kerja</th>
                            <th
                                class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100">
                                Nama Shift</th>
                            <th
                                class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100">
                                Location</th>
                            <th
                                class="px-6 py-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100 text-right">
                                Opsi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <!-- Content loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <div class="h-20 w-20 bg-orange-50 rounded-3xl flex items-center justify-center text-[#FB9300] mb-4">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h4 class="text-lg font-bold text-[#343F56]">Simpan User Terlebih Dahulu</h4>
            <p class="text-gray-400 text-sm font-medium mt-1">Anda harus menyimpan data user sebelum dapat melakukan mapping
                shift.</p>
        </div>
    @endif
</div>