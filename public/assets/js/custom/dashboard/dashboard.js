/**
 * Dashboard Features JS
 * Separated from dashboard.blade.php for better maintainability and performance.
 */

// Global DataTable instances
var atTable, vsTable;

document.addEventListener("DOMContentLoaded", function () {
    // Initial Configuration & Variables
    const config = window.DashboardConfig || {};

    // Load More Logic
    const loadMoreBtn = document.getElementById("load-more");
    const activityContainer = document.getElementById("activity-container");
    const loadingText = document.getElementById("loading-text");

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener("click", function () {
            const page = this.getAttribute("data-page");
            loadMoreBtn.classList.add("hidden");
            loadingText.classList.remove("hidden");

            fetch(`${config.homeUrl}?page=${page}`, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
            })
                .then((response) => response.text())
                .then((html) => {
                    if (html.trim() === "") {
                        loadMoreBtn.remove();
                        loadingText.innerText = "No more activities to load.";
                        loadingText.classList.remove("hidden");
                        return;
                    }
                    activityContainer.insertAdjacentHTML("beforeend", html);
                    this.setAttribute("data-page", parseInt(page) + 1);
                    loadMoreBtn.classList.remove("hidden");
                    loadingText.classList.add("hidden");
                })
                .catch((err) => {
                    console.error("Error loading more activities:", err);
                    loadMoreBtn.classList.remove("hidden");
                    loadingText.classList.add("hidden");
                });
        });
    }

    // Select2 Initialization
    const select2Options = {
        placeholder: "Select Users",
        allowClear: true,
        closeOnSelect: false,
        width: "100%",
        dropdownParent: $("#dashboard-container"),
    };

    $("#at-user-select").select2(select2Options);
    $("#vs-user-select").select2(select2Options);

    // Handle All Users selection logic
    $(".select2-filter").on("select2:select", function (e) {
        if (e.params.data.id === "all") {
            $(this).val(["all"]).trigger("change");
        } else {
            let currentVal = $(this).val();
            if (currentVal.includes("all")) {
                currentVal = currentVal.filter((v) => v !== "all");
                $(this).val(currentVal).trigger("change");
            }
        }
    });

    // Flatpickr Initialization
    const flatpickrOptions = {
        mode: "range",
        dateFormat: "Y-m-d",
        defaultDate: [
            moment().startOf("month").format("YYYY-MM-DD"),
            moment().endOf("month").format("YYYY-MM-DD"),
        ],
    };

    const atPicker = flatpickr("#at-date-range", flatpickrOptions);
    const vsPicker = flatpickr("#vs-date-range", flatpickrOptions);

    // Attendance Table
    atTable = $("#at-datatable").DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: config.attendanceDataUrl,
            beforeSend: function () {
                let alpine = Alpine.$data(
                    document.getElementById("dashboard-container"),
                );
                if (alpine) alpine.loadingAttendance = true;
            },
            data: function (d) {
                d.user_ids = $("#at-user-select").val();
                let range = atPicker.selectedDates;
                if (range.length === 2) {
                    d.start_date = moment(range[0]).format("YYYY-MM-DD");
                    d.end_date = moment(range[1]).format("YYYY-MM-DD");
                } else {
                    d.start_date = moment()
                        .startOf("month")
                        .format("YYYY-MM-DD");
                    d.end_date = moment().endOf("month").format("YYYY-MM-DD");
                }
            },
            complete: function () {
                let alpine = Alpine.$data(
                    document.getElementById("dashboard-container"),
                );
                if (alpine)
                    setTimeout(() => (alpine.loadingAttendance = false), 300);
            },
        },
        columns: [
            { data: "name" },
            {
                data: "work_date",
                render: (d) => moment(d).format("DD MMM YYYY"),
            },
            { data: "checkin_time", render: (d) => d || "--:--" },
            { data: "checkout_time", render: (d) => d || "--:--" },
            { data: "checkin_address", className: "text-xs max-w-xs truncate" },
            {
                data: "checkout_address",
                className: "text-xs max-w-xs truncate",
            },
        ],
        order: [[1, "desc"]],
    });

    // Visit Table
    vsTable = $("#vs-datatable").DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: config.visitDataUrl,
            beforeSend: function () {
                let alpine = Alpine.$data(
                    document.getElementById("dashboard-container"),
                );
                if (alpine) alpine.loadingVisit = true;
            },
            data: function (d) {
                d.user_ids = $("#vs-user-select").val();
                let range = vsPicker.selectedDates;
                if (range.length === 2) {
                    d.start_date = moment(range[0]).format("YYYY-MM-DD");
                    d.end_date = moment(range[1]).format("YYYY-MM-DD");
                } else {
                    d.start_date = moment()
                        .startOf("month")
                        .format("YYYY-MM-DD");
                    d.end_date = moment().endOf("month").format("YYYY-MM-DD");
                }
            },
            complete: function () {
                let alpine = Alpine.$data(
                    document.getElementById("dashboard-container"),
                );
                if (alpine)
                    setTimeout(() => (alpine.loadingVisit = false), 300);
            },
        },
        columns: [
            { data: "name" },
            { data: "tanggal", render: (d) => moment(d).format("DD MMM YYYY") },
            {
                data: "visit_in",
                render: (d) => (d ? moment(d).format("HH:mm") : "--:--"),
            },
            {
                data: "visit_out",
                render: (d) => (d ? moment(d).format("HH:mm") : "--:--"),
            },
            { data: "address_in", className: "text-xs max-w-xs truncate" },
            { data: "keterangan_in", className: "text-xs italic" },
        ],
        order: [[1, "desc"]],
    });

    $("#at-filter-btn").on("click", () => {
        if (atTable) atTable.ajax.reload();
    });

    $("#vs-filter-btn").on("click", () => {
        if (vsTable) vsTable.ajax.reload();
    });

    $("#at-export-btn").on("click", () => {
        alert("Exporting Attendance CSV...");
    });

    $("#vs-export-btn").on("click", () => {
        alert("Exporting Visit CSV...");
    });
});
