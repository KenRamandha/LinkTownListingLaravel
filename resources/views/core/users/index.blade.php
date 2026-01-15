@extends('layouts.app')

@section('title', 'Users Management')
@section('header', 'User Management')

@section('content')
    <div class="p-8 lg:p-12">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Users</h1>
                <p class="text-gray-500 mt-2 text-sm">Create, manage and audit all team members within your organization.</p>
            </div>
            
            <div class="flex items-center gap-3">
                <div class="relative group">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 group-focus-within:text-blue-500 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input type="text" id="customSearch" 
                        class="pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:ring-4 focus:ring-blue-500/5 focus:border-blue-500 transition-all w-64 shadow-sm"
                        placeholder="Search users...">
                </div>

                <a href="{{ route('users.add') }}"
                    class="inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-blue-200 active:scale-95">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add User
                </a>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <table id="userTable" class="w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="px-8">Profile</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Activity</th>
                        <th class="text-right px-8">Options</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    </tbody>
            </table>
        </div>
    </div>
@endsection

@section('extra_js')
<script>
    $(document).ready(function() {
        const table = $('#userTable').DataTable({
            processing: true,
            ajax: {
                url: "{{ route('users.list') }}",
                dataSrc: "data"
            },
            columns: [
                { 
                    data: null,
                    render: function(data) {
                        const name = data.name || 'Unknown';
                        const initials = name.substring(0, 2).toUpperCase();
                        return `
                            <div class="flex items-center gap-4">
                                <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold shadow-lg shadow-blue-100">
                                    ${initials}
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 leading-tight group-hover:text-blue-600 transition-colors">${name}</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">${data.email}</p>
                                </div>
                            </div>`;
                    }
                },
                {
                    data: 'role_name',
                    render: function(data) {
                        return `<span class="px-3 py-1 rounded-lg text-[11px] font-bold border bg-gray-50 text-gray-500 border-gray-100 uppercase">${data || 'CLIENT'}</span>`;
                    }
                },
                {
                    data: 'status',
                    render: function(data) {
                        const isActive = data === 'active';
                        return `
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-1.5 rounded-full ${isActive ? 'bg-green-500 animate-pulse' : 'bg-gray-300'}"></div>
                                <span class="text-xs font-bold ${isActive ? 'text-green-700' : 'text-gray-500'} capitalize">${data}</span>
                            </div>`;
                    }
                },
                {
                    data: null,
                    render: function(data) {
                        const joined = moment(data.created_at).format('DD MMM YYYY');
                        const lastLogin = data.last_login_at ? moment(data.last_login_at).fromNow() : 'Never';
                        return `
                            <div class="flex flex-col">
                                <span class="text-[11px] text-gray-900 font-semibold italic">Joined ${joined}</span>
                                <span class="text-[10px] text-gray-400 mt-0.5 tracking-tighter uppercase">Last login: ${lastLogin}</span>
                            </div>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-right',
                    render: function(data) {
                        return `
                            <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick="openRolesModal('${data.id}')" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                </button>
                                <button onclick='openEditModal(${JSON.stringify(data)})' class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                </button>
                                <button onclick="deleteUser('${data.id}')" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>`;
                    }
                }
            ],
            columnDefs: [
                { targets: [0, 4], className: 'px-8 py-5' },
                { targets: [1, 2, 3], className: 'px-6 py-5' }
            ],
            createdRow: function(row) {
                $(row).addClass('group hover:bg-blue-50/30 transition-all duration-200');
            },
            language: {
                paginate: { next: '→', previous: '←' },
                info: "Showing _START_ to _END_ of _TOTAL_ users"
            }
        });

        $('#customSearch').on('keyup', function() {
            table.search(this.value).draw();
        });
    });
    
    function openEditModal(user) { console.log(user); }
    function deleteUser(id) { alert('Delete ID: ' + id); }
</script>
@endsection