$(document).ready(function () {
    const tables = {};

    function initDataTable(tableEl) {
        const companyId = tableEl.data("company-id");
        const type = tableEl.data("type");
        const tableId = `${type}-${companyId}`;

        if (tables[tableId]) return;

        tables[tableId] = tableEl.DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "/transaction/daily/list",
                data: function (d) {
                    d.company_id = companyId;
                    d.type = type;
                    d.start_date = $("#startDateValue").val();
                    d.end_date = $("#endDateValue").val();
                },
            },
            columns: getColumns(type),
            columnDefs: [{ className: "px-6 py-4", targets: "_all" }],
            language: {
                paginate: {
                    next: '<span class="text-[#FB9300]">→</span>',
                    previous: '<span class="text-[#FB9300]">←</span>',
                },
                info: "Showing <span class='font-bold text-[#343F56]'>_START_</span> to <span class='font-bold text-[#343F56]'>_END_</span> of _TOTAL_ entries",
            },
            createdRow: function (row) {
                $(row).addClass(
                    "group hover:bg-orange-50/30 transition-all duration-200",
                );
            },
        });
    }

    function getColumns(type) {
        const cols = [];
        if (type === "monthly") {
            cols.push({
                data: "transaction_date",
                render: function (data) {
                    return `<span class="font-bold text-[#343F56]">${moment(data).format("DD MMM YYYY")}</span>`;
                },
            });
        }

        cols.push({
            data: "status",
            render: function (data) {
                return `<code class="text-xs font-bold bg-gray-100 px-2 py-1 rounded text-[#343F56]">${data}</code>`;
            },
        });

        cols.push({
            data: "user_name",
            render: function (data) {
                return `<span class="font-medium text-gray-600">${data || "Unknown"}</span>`;
            },
        });

        cols.push({
            data: "total_price",
            render: function (data) {
                return `<span class="font-black text-[#FB9300]">Rp ${new Intl.NumberFormat("id-ID").format(data || 0)}</span>`;
            },
        });

        cols.push({
            data: null,
            orderable: false,
            className: "text-right",
            render: function (data) {
                return `
                    <button onclick="viewDetail('${data.daily_id}')" class="p-2 text-gray-400 hover:text-[#FB9300] hover:bg-orange-50 rounded-xl transition-all" title="View Detail">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                `;
            },
        });

        return cols;
    }

    function refreshTables() {
        const activeTabId = $("#activeTabValue").val();
        $(`div[x-show="activeTab === '${activeTabId}'"] table`).each(
            function () {
                const tableEl = $(this);
                if ($.fn.DataTable.isDataTable(tableEl)) {
                    tableEl.DataTable().ajax.reload();
                } else {
                    initDataTable(tableEl);
                }
            },
        );
    }

    window.addEventListener("tab-changed", function () {
        setTimeout(refreshTables, 100);
    });

    window.addEventListener("filter-changed", function () {
        window.showLoading("dailyLoading");
        const activeTables = Object.values(tables);
        if (activeTables.length === 0) {
            window.hideLoading("dailyLoading");
            return;
        }

        let loadedCount = 0;
        activeTables.forEach((t) => {
            t.ajax.reload(() => {
                loadedCount++;
                if (loadedCount === activeTables.length) {
                    window.hideLoading("dailyLoading");
                }
            });
        });
    });

    setTimeout(() => {
        refreshTables();
        window.hideLoading("dailyLoading");
    }, 500);

    window.viewDetail = function (dailyId) {
        window.showLoading("dailyLoading");
        $.ajax({
            url: `/transaction/daily/details/${dailyId}`,
            type: "GET",
            success: function (response) {
                console.log(response);

                const details = response.data;
                const tbody = $("#tableDetail tbody");
                tbody.empty();

                let total = 0;
                let notes = "No notes available.";

                if (details.length > 0) {
                }

                details.forEach((item) => {
                    const subtotal = item.quantity * item.price;
                    total += subtotal;
                    tbody.append(`
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-bold text-[#343F56]">${item.nama_produk}</div>
                                <div class="text-[10px] text-gray-400 font-medium tracking-wider uppercase">${item.kode_produk || ""}</div>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-gray-600">${item.quantity}</td>
                            <td class="px-6 py-4 text-right font-medium text-gray-600">Rp ${new Intl.NumberFormat("id-ID").format(item.price)}</td>
                            <td class="px-6 py-4 text-right font-bold text-[#343F56]">Rp ${new Intl.NumberFormat("id-ID").format(subtotal)}</td>
                        </tr>
                    `);
                    if (item.note_detail) notes = item.note_detail;
                });

                $("#detailTitle").text("Transaction Detail");
                $("#detailSubtitle").text(dailyId);
                $("#detailTotal").text(
                    `Rp ${new Intl.NumberFormat("id-ID").format(total)}`,
                );
                $("#detailNotes").text(notes);

                window.dispatchEvent(new CustomEvent("open-detail"));
                window.hideLoading("dailyLoading");
            },
            error: function () {
                window.hideLoading("dailyLoading");
                window.toast("error", "Failed to fetch details");
            },
        });
    };
});
