<div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-gray-50">
        <h2 class="text-lg font-bold text-gray-900">Attachment & Status</h2>
        <p class="text-sm text-gray-400">Informasi dokumen dan status kekaryawanan.</p>
    </div>
    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. KTP
            </label>
            <input type="text" name="no_ktp" value="{{ $user->no_ktp ?? '' }}" class="custom-input" placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. NPWP
            </label>
            <input type="text" name="no_npwp" value="{{ $user->no_npwp ?? '' }}" class="custom-input" placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. BPJS Sehat
            </label>
            <input type="text" name="no_bpjs_sehat" value="{{ $user->no_bpjs_sehat ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. BPJS Kerja
            </label>
            <input type="text" name="no_bpjs_kerja" value="{{ $user->no_bpjs_kerja ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. Kontrak
            </label>
            <input type="text" name="no_kontrak" value="{{ $user->no_kontrak ?? '' }}" class="custom-input"
                placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                No. PKWT
            </label>
            <input type="text" name="no_pkwt" value="{{ $user->no_pkwt ?? '' }}" class="custom-input" placeholder="0">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Mulai PKWT
            </label>
            <input type="date" name="mulai_pkwt"
                value="{{ isset($user->mulai_pkwt) ? \Carbon\Carbon::parse($user->mulai_pkwt)->format('Y-m-d') : '' }}"
                class="custom-input">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Akhir PKWT
            </label>
            <input type="date" name="akhir_pkwt"
                value="{{ isset($user->akhir_pkwt) ? \Carbon\Carbon::parse($user->akhir_pkwt)->format('Y-m-d') : '' }}"
                class="custom-input">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Status Nikah
            </label>
            <select name="stat_nikah" class="custom-input select2">
                <option value="">Pilih Status...</option>
                @foreach($marriage_stats as $stat)
                    <option value="{{ $stat->stat_name }}" {{ (isset($user) && $user->stat_nikah == $stat->stat_name) ? 'selected' : '' }}>
                        {{ $stat->stat_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Status Pajak
            </label>
            <select name="stat_pajak" class="custom-input select2">
                <option value="">Pilih Status...</option>
                @foreach($tax_stats as $stat)
                    <option value="{{ $stat->stat_name }}" {{ (isset($user) && $user->stat_pajak == $stat->stat_name) ? 'selected' : '' }}>
                        {{ $stat->stat_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Status Karyawan
            </label>
            <select name="stat_karyawan" class="custom-input select2">
                <option value="">Pilih Status...</option>
                @foreach($employee_stats as $stat)
                    <option value="{{ $stat->stat_name }}" {{ (isset($user) && $user->stat_karyawan == $stat->stat_name) ? 'selected' : '' }}>
                        {{ $stat->stat_name }}
                    </option>
                @endforeach
            </select>
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

<div class="mt-8 bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-gray-50 flex justify-between items-center">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Dokumen Pendukung</h2>
            <p class="text-sm text-gray-400">Unggah dokumen pendukung dalam format PDF, JPG, atau PNG.</p>
        </div>
        <button type="button" onclick="addAttachmentRow()"
            class="inline-flex items-center px-4 py-2 bg-blue-50 text-blue-600 text-sm font-bold rounded-xl hover:bg-blue-100 transition-all active:scale-95">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Tambah Document
        </button>
    </div>
    <div class="p-6">
        <div id="attachmentArea" class="space-y-4">
            <!-- Attachment rows will be added here -->
        </div>

        <div class="mt-8 border-t border-gray-50 pt-8" id="uploadedListSection" style="display: none;">
            <h3 class="text-md font-bold text-gray-900 mb-4">Daftar Dokumen Terunggah</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="attachmentTable">
                    <thead>
                        <tr
                            class="border-b border-gray-50 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                            <th class="py-4 px-2">Tipe Dokumen</th>
                            <th class="py-4 px-2">Nama File</th>
                            <th class="py-4 px-2">Tanggal</th>
                            <th class="py-4 px-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <!-- Uploaded attachments will be listed here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<template id="attachmentRowTemplate">
    <div
        class="attachment-row bg-gray-50 rounded-2xl p-4 border border-dashed border-gray-200 relative group transition-all hover:border-blue-300">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
            <div class="flex-1 w-full">
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Tipe Dokumen</label>
                <select class="custom-input select2 attachment-doc-type">
                    <option value="">Pilih Tipe...</option>
                    @foreach($doc_types as $doc)
                        <option value="{{ $doc->stat_name }}">{{ $doc->stat_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-[2] w-full">
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Pilih File</label>
                <div
                    class="drop-zone relative h-12 border-2 border-dashed border-gray-200 rounded-xl flex items-center justify-center bg-white transition-all hover:bg-gray-50 cursor-pointer overflow-hidden">
                    <input type="file" class="absolute inset-0 opacity-0 cursor-pointer attachment-file"
                        onchange="handleFileSelect(this)">
                    <div class="flex items-center text-gray-400 pointer-events-none px-4 truncate w-full">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span class="text-xs truncate fileName">Drag & drop or click to upload</span>
                    </div>
                </div>
            </div>
            <div class="mt-6 md:mt-0 flex-shrink-0 flex items-center gap-2">
                <button type="button" onclick="uploadAttachment(this)"
                    class="px-4 py-2 bg-[#FB9300] text-white text-xs font-bold rounded-xl hover:bg-[#e68600] transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed upload-btn">
                    Upload
                </button>
                <button type="button" onclick="removeRow(this)"
                    class="p-2 text-red-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </div>
        <div class="progress-bar-container hidden mt-3 h-1 w-full bg-gray-100 rounded-full overflow-hidden">
            <div class="progress-bar h-full bg-[#FB9300] transition-all duration-300" style="width: 0%"></div>
        </div>
    </div>
</template>