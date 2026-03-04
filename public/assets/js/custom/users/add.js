$(document).ready(function () {
    $(".tab-link").on("click", function (e) {
        e.preventDefault();
        let targetId = $(this).data("target");

        $(".tab-link")
            .removeClass("text-[#FB9300] bg-orange-50 active-tab")
            .addClass("text-gray-500 hover:bg-gray-50 hover:text-[#FB9300]");

        $(".tab-link svg")
            .removeClass("text-[#FB9300]")
            .addClass("text-gray-300 group-hover:text-[#FB9300]");

        $(this)
            .removeClass("text-gray-500 hover:bg-gray-50 hover:text-[#FB9300]")
            .addClass("text-[#FB9300] bg-orange-50 active-tab");

        $(this)
            .find("svg")
            .removeClass("text-gray-300")
            .addClass("text-[#FB9300]");

        $(".tab-content").addClass("hidden");
        $("#" + targetId).removeClass("hidden");

        if (targetId === "shift-mapping") {
            window.loadMappings();
        }

        if (targetId === "attachment") {
            window.loadAttachments();
        }
    });

    $(".select2").select2({
        width: "100%",
        allowClear: false,
    });

    $(".select2-no-search").select2({
        width: "100%",
        minimumResultsForSearch: -1,
    });

    $("#company_id").on("change", function () {
        let companyId = $(this).val();
        let departmentSelect = $("#department_id");
        let selectedDepId = departmentSelect.data("selected");
        let routeTemplate = $(this).data("route-departments");

        if (!routeTemplate) return;

        departmentSelect.html("<option>Loading...</option>").trigger("change");

        if (!companyId) {
            departmentSelect.html(
                '<option value="">Pilih Departemen...</option>',
            );
            return;
        }

        $.ajax({
            url: routeTemplate.replace(":id", companyId),
            type: "GET",
            success: function (data) {
                departmentSelect
                    .empty()
                    .append('<option value="">Pilih Departemen...</option>');
                $.each(data, function (i, dep) {
                    let isSelected = dep.id == selectedDepId ? "selected" : "";
                    departmentSelect.append(
                        `<option value="${dep.id}" ${isSelected}>${dep.name}</option>`,
                    );
                });
                departmentSelect.trigger("change");
            },
        });
    });

    if ($("#company_id").val()) {
        $("#company_id").trigger("change");
    }

    $('select[name="akses_web"]').on("change", function () {
        if ($(this).val() === "YES") {
            $("#tab-web-mapping").fadeIn();
        } else {
            $("#tab-web-mapping").hide();
            if ($("#tab-web-mapping").hasClass("active-tab")) {
                $('a[data-target="general-info"]').trigger("click");
            }
        }
    });

    $("#userForm").on("submit", function (e) {
        e.preventDefault();
        let form = $(this);
        let url = form.attr("action");
        let formData = new FormData(this);

        let submitBtn = form.find('button[type="submit"]');
        let originalText = submitBtn.html();
        submitBtn
            .prop("disabled", true)
            .html(
                '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...',
            );

        $.ajax({
            url: url,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                window.toast("success", response.message);

                if (form.find('input[name="_method"]').val() !== "PUT") {
                    setTimeout(() => {
                        window.location.href = "/users";
                    }, 1500);
                } else {
                    submitBtn.prop("disabled", false).html(originalText);
                }
            },
            error: function (xhr) {
                submitBtn.prop("disabled", false).html(originalText);
                let message = xhr.responseJSON
                    ? xhr.responseJSON.message
                    : "Something went wrong";
                window.toast("error", message);

                if (xhr.status === 422 && xhr.responseJSON.errors) {
                    let errors = xhr.responseJSON.errors;
                    let firstError = Object.values(errors)[0][0];
                    window.toast("error", firstError);
                }
            },
        });
    });
});

let mappingTable;

window.initMappingTable = function () {
    const userId = $("#userForm").attr("action").split("/").pop();
    if (!userId || userId === "users") return;

    if ($.fn.DataTable.isDataTable("#mappingTable")) {
        mappingTable.ajax.reload();
        return;
    }

    mappingTable = $("#mappingTable").DataTable({
        processing: true,
        serverSide: false,
        ajax: `/users/${userId}/shift-mapping`,
        order: [[0, "desc"]],
        columns: [
            {
                data: "work_date",
                render: function (data) {
                    return `<h4 class="text-sm font-bold text-[#343F56]">${moment(
                        data,
                    ).format("DD MMM YYYY")}</h4>`;
                },
            },
            {
                data: "shift_name",
                render: function (data) {
                    return `<span class="px-3 py-1 rounded-lg text-[10px] font-extrabold border bg-gray-50 text-[#343F56] border-gray-100 uppercase tracking-wider">${data}</span>`;
                },
            },
            {
                data: "lock_location",
                render: function (data) {
                    if (data == 1) {
                        return '<span class="px-3 py-1 rounded-lg text-[10px] font-extrabold bg-red-50 text-red-600 border border-red-100 uppercase tracking-wider">Locked</span>';
                    }
                    return '<span class="px-3 py-1 rounded-lg text-[10px] font-extrabold bg-green-50 text-green-600 border border-green-100 uppercase tracking-wider">Unlock</span>';
                },
            },
            {
                data: "id",
                className: "text-right",
                render: function (data) {
                    return `
                        <button type="button" onclick="deleteMapping('${data}')" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all" title="Delete">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    `;
                },
            },
        ],
        language: {
            emptyTable: "Belum ada mapping shift.",
            processing: "Memuat data...",
        },
        dom: "rtip",
    });
};

window.loadMappings = function () {
    window.initMappingTable();
};

window.saveMapping = function () {
    const userId = $("#userForm").attr("action").split("/").pop();
    const shiftId = $("#mapping_shift_id").val();
    const startDate = $("#mapping_start_date").val();
    const endDate = $("#mapping_end_date").val();

    if (!shiftId || !startDate || !endDate) {
        window.toast(
            "error",
            "Harap lengkapi semua field mapping (Shift, Tanggal Mulai, dan Tanggal Selesai)",
        );
        return;
    }

    const data = {
        shift_id: shiftId,
        start_date: startDate,
        end_date: endDate,
        lock_location: $("#mapping_lock_location").is(":checked") ? 1 : 0,
        _token: $('meta[name="csrf-token"]').attr("content"),
    };

    $.ajax({
        url: `/users/${userId}/shift-mapping`,
        type: "POST",
        data: data,
        success: function (response) {
            window.toast("success", response.message);
            if (mappingTable) {
                mappingTable.ajax.reload();
            } else {
                window.loadMappings();
            }
        },
        error: function (xhr) {
            let message = xhr.responseJSON
                ? xhr.responseJSON.message
                : "Something went wrong";
            window.toast("error", message);
        },
    });
};

window.deleteMapping = function (id) {
    Swal.fire({
        title: "Apakah anda yakin?",
        text: "Data mapping akan dihapus permanen!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#FB9300",
        cancelButtonColor: "#343F56",
        confirmButtonText: "Ya, hapus!",
        cancelButtonText: "Batal",
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/users/shift-mapping/${id}`,
                type: "DELETE",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr(
                        "content",
                    ),
                },
                success: function (response) {
                    window.toast("success", response.message);
                    if (mappingTable) {
                        mappingTable.ajax.reload();
                    } else {
                        window.loadMappings();
                    }
                },
            });
        }
    });
};

window.addAttachmentRow = function () {
    const template = document.getElementById("attachmentRowTemplate");
    const clone = template.content.cloneNode(true);
    const container = document.getElementById("attachmentArea");

    const $newRow = $(clone.children);
    $newRow.appendTo(container);

    $newRow.find(".select2").select2({
        width: "100%",
        allowClear: false,
    });
};

window.handleFileSelect = function (input) {
    const fileName = input.files[0]
        ? input.files[0].name
        : "Drag & drop or click to upload";
    $(input).closest(".drop-zone").find(".fileName").text(fileName);
};

window.removeRow = function (btn) {
    $(btn).closest(".attachment-row").remove();
};

window.uploadAttachment = function (btn) {
    const row = $(btn).closest(".attachment-row");
    const docType = row.find(".attachment-doc-type").val();
    const fileInput = row.find(".attachment-file")[0];
    const file = fileInput.files[0];

    if (!docType) {
        window.toast("error", "Pilih tipe dokumen terlebih dahulu");
        return;
    }

    if (!file) {
        window.toast("error", "Pilih file terlebih dahulu");
        return;
    }

    const userId = $("#userForm").attr("action").split("/").pop();
    const formData = new FormData();
    formData.append("doc_type", docType);
    formData.append("file", file);
    formData.append("_token", $('meta[name="csrf-token"]').attr("content"));

    const progressBarContainer = row.find(".progress-bar-container");
    const progressBar = row.find(".progress-bar");
    const uploadBtn = $(btn);

    progressBarContainer.removeClass("hidden");
    uploadBtn.prop("disabled", true).text("Uploading...");

    $.ajax({
        url: `/users/${userId}/attachments`,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        xhr: function () {
            const xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener(
                "progress",
                function (evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = (evt.loaded / evt.total) * 100;
                        progressBar.css("width", percentComplete + "%");
                    }
                },
                false,
            );
            return xhr;
        },
        success: function (response) {
            window.toast("success", response.message);
            row.remove();
            window.loadAttachments();
        },
        error: function (xhr) {
            uploadBtn.prop("disabled", false).text("Upload");
            progressBarContainer.addClass("hidden");
            progressBar.css("width", "0%");
            let message = xhr.responseJSON
                ? xhr.responseJSON.message
                : "Something went wrong";
            window.toast("error", message);
        },
    });
};

window.loadAttachments = function () {
    const userId = $("#userForm").attr("action").split("/").pop();
    if (!userId || userId === "users") return;

    $.ajax({
        url: `/users/${userId}/attachments`,
        type: "GET",
        success: function (attachments) {
            const tbody = $("#attachmentTable tbody");
            tbody.empty();

            if (attachments.length > 0) {
                $("#uploadedListSection").show();
                attachments.forEach((att) => {
                    const date = moment(att.created_at).format(
                        "DD MMM YYYY HH:mm",
                    );
                    const fileUrl = `/storage/${att.file_path}`;
                    tbody.append(`
                        <tr class="group hover:bg-gray-50 transition-all">
                            <td class="py-4 px-2">
                                <span class="px-3 py-1 rounded-lg text-[10px] font-extrabold bg-blue-50 text-blue-600 border border-blue-100 uppercase tracking-wider">${att.doc_type}</span>
                            </td>
                            <td class="py-4 px-2">
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-gray-700">${att.file_origin}</span>
                                    <span class="text-[10px] text-gray-400">${att.file_final}</span>
                                </div>
                            </td>
                            <td class="py-4 px-2 text-xs text-gray-500">${date}</td>
                            <td class="py-4 px-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="${fileUrl}" target="_blank" class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all" title="View">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    </a>
                                    <button type="button" onclick="deleteAttachment(${att.id})" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all" title="Delete">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `);
                });
            } else {
                $("#uploadedListSection").hide();
            }
        },
    });
};

window.deleteAttachment = function (id) {
    Swal.fire({
        title: "Apakah anda yakin?",
        text: "Dokumen akan dihapus permanen!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#FB9300",
        cancelButtonColor: "#343F56",
        confirmButtonText: "Ya, hapus!",
        cancelButtonText: "Batal",
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/users/attachments/${id}`,
                type: "DELETE",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr(
                        "content",
                    ),
                },
                success: function (response) {
                    window.toast("success", response.message);
                    window.loadAttachments();
                },
            });
        }
    });
};
