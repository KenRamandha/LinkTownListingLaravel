$(document).ready(function () {
    const tableElement = $("#userTable");
    if (!tableElement.length) return;

    const routeList = tableElement.data("route-list");

    const table = tableElement.DataTable({
        processing: true,
        responsive: {
            details: {
                type: "column",
                target: "tr",
            },
        },
        ajax: {
            url: routeList,
            dataSrc: "data",
        },
        initComplete: function (settings, json) {
            if (window.hideLoading) {
                window.hideLoading("userLoading");
            }
        },
        columns: [
            {
                data: null,
                render: function (data) {
                    const name = data.name || "Unknown";
                    const initials = name.substring(0, 2).toUpperCase();
                    let avatarHtml = "";
                    if (data.avatar_url) {
                        avatarHtml = `
                            <img src="${data.avatar_url}" 
                                class="h-11 w-11 rounded-2xl object-cover shadow-lg shadow-orange-100 border-2 border-white" 
                                onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'h-11 w-11 rounded-2xl bg-gradient-to-br from-[#FB9300] to-[#343F56] flex items-center justify-center text-white font-bold\'>${initials}</div>';">
                        `;
                    } else {
                        avatarHtml = `
                            <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-[#FB9300] to-[#343F56] flex items-center justify-center text-white font-bold shadow-lg shadow-orange-100 uppercase">
                                ${initials}
                            </div>
                        `;
                    }

                    return `
                        <div class="flex items-center gap-4">
                            ${avatarHtml}
                            <div>
                                <h4 class="text-sm font-bold text-[#343F56] leading-tight group-hover:text-[#FB9300] transition-colors">${name}</h4>
                                <p class="text-xs text-gray-500 mt-0.5 font-medium">${data.email}</p>
                            </div>
                        </div>
                    `;
                },
            },
            {
                data: "position",
                render: function (data) {
                    return `<span class="px-3 py-1 rounded-lg text-[10px] font-extrabold border bg-gray-50 text-[#343F56] border-gray-100 uppercase tracking-wider">${
                        data || "CLIENT"
                    }</span>`;
                },
            },
            {
                data: "status",
                render: function (data) {
                    const isActive = data === "active";
                    return `
                    <div class="flex items-center gap-2">
                        <div class="h-1.5 w-1.5 rounded-full ${
                            isActive
                                ? "bg-[#FB9300] animate-pulse"
                                : "bg-gray-300"
                        }"></div>
                        <span class="text-xs font-bold ${
                            isActive ? "text-[#FB9300]" : "text-gray-500"
                        } capitalize">${data}</span>
                    </div>
                `;
                },
            },
            {
                data: null,
                // responsivePriority: 1,
                // className: "dtr-control",
                render: function (data) {
                    const joined = moment(data.created_at).format(
                        "DD MMM YYYY",
                    );
                    const lastLogin = data.last_login_at
                        ? moment(data.last_login_at).fromNow()
                        : "Never";
                    return `
                    <div class="flex flex-col">
                        <span class="text-[11px] text-[#343F56] font-bold italic">Joined ${joined}</span>
                        <span class="text-[10px] text-gray-400 mt-0.5 tracking-tighter uppercase font-semibold">Last login: ${lastLogin}</span>
                    </div>
                `;
                },
            },
            {
                data: null,
                orderable: false,
                responsivePriority: 2,
                className: "text-right",
                render: function (data) {
                    return `
                    <div class="flex items-center justify-end gap-1 md:opacity-0 group-hover:opacity-100 transition-all duration-300">
                        <button onclick="openRolesModal('${
                            data.id
                        }')" class="p-2 text-gray-400 hover:text-[#343F56] hover:bg-gray-100 rounded-xl transition-all" title="Permissions">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        </button>
                        <button onclick='window.openEditModal(${JSON.stringify(
                            data,
                        )})' class="p-2 text-gray-400 hover:text-[#FB9300] hover:bg-orange-50 rounded-xl transition-all" title="Edit User">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                        </button>
                        <button onclick="window.deleteUser('${
                            data.id
                        }')" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all" title="Delete">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                `;
                },
            },
        ],
        columnDefs: [
            { targets: [0, 4], className: "px-8 py-5" },
            { targets: [1, 2, 3], className: "px-6 py-5" },
        ],
        createdRow: function (row) {
            $(row).addClass(
                "group hover:bg-orange-50/30 transition-all duration-200 cursor-pointer",
            );
        },
        language: {
            paginate: {
                next: '<span class="text-[#FB9300]">→</span>',
                previous: '<span class="text-[#FB9300]">←</span>',
            },
            info: "Showing <span class='font-bold text-[#343F56]'>_START_</span> to <span class='font-bold text-[#343F56]'>_END_</span> of _TOTAL_ users",
        },
    });

    $("#customSearch").on("keyup", function () {
        table.search(this.value).draw();
    });
});

window.openEditModal = function (user) {
    window.location.href = `/users/${user.id}/edit`;
};

window.deleteUser = function (id) {
    Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to revert this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#FB9300",
        cancelButtonColor: "#343F56",
        confirmButtonText: "Yes, delete it!",
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/users/${id}`,
                type: "DELETE",
                data: {
                    _token: $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    window.toast("success", response.message);
                    $("#userTable").DataTable().ajax.reload();
                },
                error: function (xhr) {
                    let message = xhr.responseJSON
                        ? xhr.responseJSON.message
                        : "Something went wrong";
                    window.toast("error", message);
                },
            });
        }
    });
};
