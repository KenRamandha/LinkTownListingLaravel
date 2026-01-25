<div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-gray-50 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Web Menu Mapping</h2>
            <p class="text-sm text-gray-400">Hubungkan user dengan company untuk akses menu website.</p>
        </div>
        <div class="px-3 py-1 bg-orange-50 rounded-lg border border-orange-100">
            <span class="text-[10px] font-extrabold text-[#FB9300] uppercase tracking-wider">Web Access Enabled</span>
        </div>
    </div>

    <div class="p-6 space-y-8">
        {{-- Role Selection (Read-only reference or active selection) --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                User Role
            </label>
            <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 flex items-center gap-3">
                <div
                    class="h-10 w-10 rounded-xl bg-blue-500 flex items-center justify-center text-white shadow-lg shadow-blue-100">
                    <i class="fa fa-shield-alt"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-900">
                        {{ $roles->firstWhere('id', $user->role_id ?? '')->name ?? 'Belum ada Role' }}
                    </p>
                    <p class="text-[11px] text-gray-400 font-medium lowercase">Permissions are inherited from this role.
                    </p>
                </div>
            </div>
        </div>

        {{-- Company Mapping --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                Company Access <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-gray-400 mb-4 font-medium">Pilih satu atau lebih company yang dapat diakses oleh user
                ini melalui dashboard web.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($companies as $company)
                    <label
                        class="relative flex items-center p-4 rounded-2xl border border-gray-100 hover:border-orange-200 hover:bg-orange-50/30 transition-all cursor-pointer group">
                        <div class="flex-1 flex items-center gap-3">
                            <div
                                class="flex items-center justify-center h-5 w-5 rounded border border-gray-300 group-hover:border-[#FB9300] transition-colors bg-white">
                                <input type="checkbox" name="user_companies[]" value="{{ $company->id }}"
                                    class="hidden peer" {{ (isset($userCompanies) && is_array($userCompanies) && in_array($company->id, $userCompanies)) ? 'checked' : '' }}>
                                <svg class="w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <div
                                    class="absolute inset-0 rounded-2xl border-2 border-transparent peer-checked:border-[#FB9300] pointer-events-none transition-all">
                                </div>
                                <div
                                    class="absolute inset-x-0 inset-y-0 rounded-2xl peer-checked:bg-orange-50/50 -z-10 transition-all">
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-700 group-hover:text-[#FB9300] transition-colors">
                                    {{ $company->name }}
                                </p>
                                <p class="text-[10px] text-gray-400 font-medium">{{ $company->code }}</p>
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Menu Permissions Matrix --}}
        <div>
            <div class="flex items-center justify-between mb-4">
                <label class="text-sm font-semibold text-gray-700">Menu Permissions</label>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Granular Access Control</p>
            </div>

            <div class="bg-gray-50 rounded-3xl border border-gray-100 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100/50">
                            <th
                                class="px-6 py-4 text-[11px] font-extrabold text-gray-500 uppercase tracking-wider w-1/2">
                                Menu Name</th>
                            <th
                                class="px-4 py-4 text-[11px] font-extrabold text-gray-500 uppercase tracking-wider text-center">
                                View</th>
                            <th
                                class="px-4 py-4 text-[11px] font-extrabold text-gray-500 uppercase tracking-wider text-center">
                                Create</th>
                            <th
                                class="px-4 py-4 text-[11px] font-extrabold text-gray-500 uppercase tracking-wider text-center">
                                Update</th>
                            <th
                                class="px-4 py-4 text-[11px] font-extrabold text-gray-500 uppercase tracking-wider text-center">
                                Delete</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($menus as $main)
                            <tr class="bg-white">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <i class="{{ $main->menu_icon }} w-4 text-center text-[#FB9300]"></i>
                                        <span class="text-sm font-bold text-gray-900">{{ $main->menu_name }}</span>
                                    </div>
                                </td>
                                @php
                                    $mainPerm = $main->permission ?? null;
                                @endphp
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox" name="menu_permissions[main][{{ $main->id }}][view]" value="1" {{ $mainPerm && $mainPerm->can_view ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-[#FB9300] focus:ring-[#FB9300]">
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox" name="menu_permissions[main][{{ $main->id }}][create]" value="1"
                                        {{ $mainPerm && $mainPerm->can_create ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-[#FB9300] focus:ring-[#FB9300]">
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox" name="menu_permissions[main][{{ $main->id }}][update]" value="1"
                                        {{ $mainPerm && $mainPerm->can_update ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-[#FB9300] focus:ring-[#FB9300]">
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox" name="menu_permissions[main][{{ $main->id }}][delete]" value="1"
                                        {{ $mainPerm && $mainPerm->can_delete ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-[#FB9300] focus:ring-[#FB9300]">
                                </td>
                            </tr>
                            @if(!empty($main->tree))
                                @foreach($main->tree as $sub)
                                    <tr class="bg-gray-50/30">
                                        <td class="px-6 py-3">
                                            <div class="flex items-center gap-3 pl-8">
                                                <div class="w-1.5 h-1.5 rounded-full bg-gray-300"></div>
                                                <span class="text-xs font-semibold text-gray-600">{{ $sub->menu_name }}</span>
                                            </div>
                                        </td>
                                        @php
                                            $subPerm = $sub->permission ?? null;
                                        @endphp
                                        <td class="px-4 py-3 text-center">
                                            <input type="checkbox" name="menu_permissions[sub][{{ $sub->id }}][view]" value="1" {{ $subPerm && $subPerm->can_view ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-[#FB9300] focus:ring-[#FB9300]">
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="checkbox" name="menu_permissions[sub][{{ $sub->id }}][create]" value="1" {{ $subPerm && $subPerm->can_create ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-[#FB9300] focus:ring-[#FB9300]">
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="checkbox" name="menu_permissions[sub][{{ $sub->id }}][update]" value="1" {{ $subPerm && $subPerm->can_update ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-[#FB9300] focus:ring-[#FB9300]">
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="checkbox" name="menu_permissions[sub][{{ $sub->id }}][delete]" value="1" {{ $subPerm && $subPerm->can_delete ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-[#FB9300] focus:ring-[#FB9300]">
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center">
                                    <p class="text-xs text-gray-400 italic">Belum ada menu yang dikonfigurasi.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="p-6 border-t border-gray-50 flex justify-end">
        <button type="submit"
            class="inline-flex items-center justify-center px-6 py-2.5 bg-[#FB9300] hover:bg-[#e68600] text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-orange-100 active:scale-95">
            <i class="fa fa-save mr-2"></i>
            Simpan Mapping & Permissions
        </button>
    </div>
</div>