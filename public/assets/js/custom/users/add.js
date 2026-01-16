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
    });

    $(".select2").select2({
        width: "100%",
        allowClear: false,
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
                '<option value="">Pilih Departemen...</option>'
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
                        `<option value="${dep.id}" ${isSelected}>${dep.name}</option>`
                    );
                });
                departmentSelect.trigger("change");
            },
        });
    });

    if ($("#company_id").val()) {
        $("#company_id").trigger("change");
    }

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
                '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...'
            );

        $.ajax({
            url: url,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                window.toast("success", response.message);

                // If it was a create operation (no PUT method in form), redirect
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

window.previewImage = function (event) {
    const reader = new FileReader();
    reader.onload = function () {
        document.getElementById("preview-image").src = reader.result;
    };
    reader.readAsDataURL(event.target.files[0]);
};
