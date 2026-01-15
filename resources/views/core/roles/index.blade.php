@extends('layouts.app')

@section('title', 'Roles Management')
@section('header', 'Roles & Permissions')

@section('content')
    <div class="p-8 lg:p-12">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Roles</h1>
                <p class="text-gray-500 mt-2 text-sm">Manage user roles and their associated permissions.</p>
            </div>

            <div class="flex items-center gap-3">
                <div class="relative group">
                    <span
                        class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 group-focus-within:text-blue-500 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input type="text" id="customSearch"
                        class="pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:ring-4 focus:ring-blue-500/5 focus:border-blue-500 transition-all w-64 shadow-sm"
                        placeholder="Search roles...">
                </div>

                <button onclick="openCreateModal()"
                    class="inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-blue-200 active:scale-95">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Role
                </button>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <table id="roleTable" class="w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="px-8">Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Created At</th>
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
        $(document).ready(function () {
            const table = $('#roleTable').DataTable({
                processing: true,
                ajax: {
                    url: "{{ route('roles.list') }}",
                    dataSrc: "data"
                },
                columns: [
                    {
                        data: 'name',
                        render: function (data) {
                            return `<span class="font-bold text-gray-900">${data}</span>`;
                        }
                    },
                    {
                        data: 'slug',
                        render: function (data) {
                            return `<code class="px-2 py-1 rounded-md bg-gray-50 text-xs font-mono text-gray-600">${data}</code>`;
                        }
                    },
                    {
                        data: 'description',
                        render: function (data) {
                            return `<span class="text-sm text-gray-500">${data || '-'}</span>`;
                        }
                    },
                    {
                        data: 'created_at',
                        render: function (data) {
                            return data ? moment(data).format('DD MMM YYYY') : '-';
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-right',
                        render: function (data) {
                            return `
                                <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button onclick="openPermissions('${data.id}')" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all" title="Permissions">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                                    </button>
                                    <button onclick='openEditModal(${JSON.stringify(data)})' class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </button>
                                    <button onclick="deleteRole('${data.id}')" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all">
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
                createdRow: function (row) {
                    $(row).addClass('group hover:bg-blue-50/30 transition-all duration-200');
                },
                language: {
                    paginate: { next: '→', previous: '←' },
                    info: "Showing _START_ to _END_ of _TOTAL_ roles"
                }
            });

            $('#customSearch').on('keyup', function () {
                table.search(this.value).draw();
            });
        });

        function openCreateModal() { /* Implement Create Logic */ }
        function openEditModal(role) { /* Implement Edit Logic */ }
        function deleteRole(id) { /* Implement Delete Logic */ }
        function openPermissions(id) { /* Implement Permissions Logic */ }
    </script>
@endsection