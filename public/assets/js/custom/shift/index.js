$(document).ready(function () {
    const tableElement = $("#shiftTable");
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
                window.hideLoading("shiftLoading");
            }
        },
        columns: [
            {
                data: "name",
                render: function (data) {
                    return `
                        <div class="flex items-center gap-4">
                            <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-[#FB9300] to-[#343F56] flex items-center justify-center text-white font-bold shadow-lg shadow-orange-100 uppercase">
                                ${data.substring(0, 2).toUpperCase()}
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-[#343F56] leading-tight group-hover:text-[#FB9300] transition-colors">${data}</h4>
                            </div>
                        </div>
                    `;
                },
            },
            {
                data: "company_name",
                render: function (data) {
                    return `<span class="text-sm font-medium text-[#343F56]">${data}</span>`;
                },
            },
            {
                data: "start_time",
                render: function (data) {
                    return `<span class="px-3 py-1 rounded-lg text-[10px] font-extrabold border bg-gray-50 text-[#343F56] border-gray-100 uppercase tracking-wider">${data}</span>`;
                },
            },
            {
                data: "end_time",
                render: function (data) {
                    return `<span class="px-3 py-1 rounded-lg text-[10px] font-extrabold border bg-gray-50 text-[#343F56] border-gray-100 uppercase tracking-wider">${data}</span>`;
                },
            },
            {
                data: null,
                orderable: false,
                className: "text-right",
                render: function (data) {
                    return `
                    <div class="flex items-center justify-end gap-1 md:opacity-0 group-hover:opacity-100 transition-all duration-300">
                        <button onclick="window.openEditShift('${data.id}')" class="p-2 text-gray-400 hover:text-[#FB9300] hover:bg-orange-50 rounded-xl transition-all" title="Edit Shift">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                        </button>
                        <button onclick="window.deleteShift('${data.id}')" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all" title="Delete">
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
            info: "Showing <span class='font-bold text-[#343F56]'>_START_</span> to <span class='font-bold text-[#343F56]'>_END_</span> of _TOTAL_ shifts",
        },
    });

    $("#customSearch").on("keyup", function () {
        table.search(this.value).draw();
    });
});

window.openEditShift = function (id) {
    window.location.href = `/shift/${id}/edit`;
};

window.deleteShift = function (id) {
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
                url: `/shift/${id}`,
                type: "DELETE",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr(
                        "content",
                    ),
                },
                success: function (response) {
                    window.toast("success", response.message);
                    $("#shiftTable").DataTable().ajax.reload();
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
