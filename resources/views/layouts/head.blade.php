<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'LinkTown') }} - @yield('title', 'Dashboard')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            letter-spacing: -0.01em;
            color: #343F56;
        }

        [x-cloak] {
            display: none !important;
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
            border: 2px solid #f8fafc;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #FB9300;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            display: none;
        }

        table.dataTable {
            border: none !important;
            margin: 0 !important;
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0;
        }

        table.dataTable thead th {
            background-color: #f8fafc !important;
            padding: 1.25rem 1.5rem !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.1em !important;
            color: #343F56 !important;
            border-bottom: 1px solid #e2e8f0 !important;
        }

        table.dataTable tbody td {
            padding: 1rem 1.5rem !important;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle;
        }

        table.dataTable tbody tr:last-child td {
            border-bottom: none !important;
        }

        .dataTables_wrapper .dataTables_info {
            padding: 1.5rem !important;
            font-size: 13px !important;
            color: #64748b !important;
            font-weight: 500;
        }

        .dataTables_wrapper .dataTables_paginate {
            padding: 1rem 1.5rem !important;
            gap: 6px;
            display: flex;
            align-items: center;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important;
            background: white !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            color: #343F56 !important;
            padding: 0.5rem 0.8rem !important;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #FB9300 !important;
            color: white !important;
            border-color: #FB9300 !important;
            box-shadow: 0 4px 6px -1px rgba(251, 147, 0, 0.3);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current) {
            background: #fff7ed !important;
            color: #FB9300 !important;
            border-color: #FB9300 !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .select2-container {
            width: 100% !important;
        }

        .select2-container .select2-selection--single {
            position: relative !important;
            height: 42px !important;
            border-radius: 0.75rem !important;
            border: 1px solid #e5e7eb !important;
            background-color: #ffffff !important;
            display: flex !important;
            align-items: center !important;
            transition: all 0.2s ease-in-out !important;
        }

        .select2-container--default .select2-selection--single:hover {
            border-color: #cbd5e1 !important;
        }

        .select2-container--default.select2-container--focus .select2-selection--single,
        .select2-container--default.select2-container--open .select2-selection--single {
            border-color: #FB9300 !important;
            box-shadow: 0 0 0 1px #FB9300 !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 14px !important;
            padding-right: 4.75rem !important;
            font-size: 0.875rem !important;
            color: #343F56 !important;
            font-weight: 500 !important;
            line-height: 1.25rem !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #9ca3af !important;
            font-weight: 400 !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__clear {
            position: absolute !important;
            right: 2.75rem !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            font-size: 18px !important;
            font-weight: 600 !important;
            line-height: 1 !important;
            color: #6b7280 !important;
            cursor: pointer !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__clear:hover {
            color: #ef4444 !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            position: absolute !important;
            right: 0.75rem !important;
            top: 0 !important;
            height: 100% !important;
            display: flex !important;
            align-items: center !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #6b7280 transparent transparent transparent !important;
        }

        .select2-dropdown {
            border-radius: 0.75rem !important;
            border: 1px solid #e5e7eb !important;
            margin-top: 6px !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
                0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
            overflow: hidden !important;
        }

        .select2-search--dropdown {
            padding: 8px !important;
        }

        .select2-search__field {
            height: 38px !important;
            border-radius: 0.5rem !important;
            border: 1px solid #e5e7eb !important;
            padding: 0 10px !important;
            font-size: 0.875rem !important;
        }

        .select2-search__field:focus {
            border-color: #FB9300 !important;
            box-shadow: 0 0 0 1px #FB9300 !important;
            outline: none !important;
        }

        .select2-results__option {
            padding: 10px 14px !important;
            font-size: 0.875rem !important;
        }

        .select2-container--default .select2-results__option--selected {
            background-color: #fff7ed !important;
            color: #FB9300 !important;
            font-weight: 600 !important;
        }

        .select2-container--default .select2-results__option--highlighted {
            background-color: #FB9300 !important;
            color: #ffffff !important;
        }

        .select2-container--default.select2-container--disabled .select2-selection--single {
            background-color: #f9fafb !important;
            cursor: not-allowed !important;
        }

        .select2-container--default.select2-container--disabled .select2-selection__rendered {
            color: #9ca3af !important;
        }

        /* Select2 Multiple Selection Refinement */
        .select2-container--default .select2-selection--multiple {
            min-height: 48px !important;
            border-radius: 0.75rem !important;
            border: 1px solid #e5e7eb !important;
            background-color: #ffffff !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            padding: 4px 8px !important;
            display: flex !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            cursor: text !important;
            /* Ganti ke text agar terasa seperti input */
        }

        /* State: Focus & Open */
        .select2-container--default.select2-container--focus .select2-selection--multiple,
        .select2-container--default.select2-container--open .select2-selection--multiple {
            border-color: #FB9300 !important;
            box-shadow: 0 0 0 3px rgba(251, 147, 0, 0.15) !important;
            /* Glow lebih halus */
            outline: none !important;
        }

        /* Wrapper Tag/Pilihan */
        .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            display: flex !important;
            flex-wrap: wrap !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }

        /* Styling Tiap Tag (Choice) */
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #fff7ed !important;
            border: 1px solid #ffedd5 !important;
            border-radius: 6px !important;
            /* Sedikit lebih kotak agar proporsional */
            padding: 2px 8px !important;
            color: #FB9300 !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            margin: 2px 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            line-height: 1.4 !important;
        }

        /* Tombol Hapus (X) */
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #FB9300 !important;
            border: none !important;
            border-right: 1px solid #ffedd5 !important;
            padding: 0 6px !important;
            margin-right: 6px !important;
            margin-left: -4px !important;
            /* Menempel ke kiri tag */
            transition: all 0.2s !important;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            background-color: #fb9300 !important;
            color: white !important;
        }

        /* Input Search di Dalam Container */
        .select2-container--default .select2-search--inline {
            display: inline-flex !important;
            align-items: center !important;
            padding: 4px 0 !important;
        }

        .select2-container--default .select2-search--inline .select2-search__field {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            font-size: 14px !important;
            margin-left: 4px !important;
            background: transparent !important;
            border: none !important;
            outline: none !important;
            height: 24px !important;
            /* Presisi tengah */
        }

        /* --- Loading States --- */
        .loading-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            border-radius: 0.75rem;
        }

        .spinner {
            width: 28px;
            /* Lebih kecil agar elegan */
            height: 28px;
            border: 3px solid rgba(251, 147, 0, 0.1);
            border-top: 3px solid #FB9300;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        /* Mengunci tinggi label agar sejajar secara horizontal */
        .filter-label {
            height: 20px;
            display: flex;
            align-items: center;
            margin-bottom: 0.375rem;
            /* 1.5 spacing */
        }

        /* Memastikan input memiliki tinggi standar yang sama */
        .custom-input {
            height: 45px !important;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .custom-textarea,
        .custom-input {
            width: 100% !important;
            border-radius: 0.75rem !important;
            border: 1px solid #e5e7eb !important;
            background-color: #ffffff !important;
            padding: 10px 14px !important;
            font-size: 0.875rem !important;
            color: #343F56 !important;
            font-weight: 500 !important;
            line-height: 1.5 !important;
            transition: all 0.2s ease-in-out !important;
            outline: none !important;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .custom-textarea:hover,
        .custom-input:hover {
            border-color: #cbd5e1 !important;
        }

        .custom-textarea:focus,
        .custom-input:focus {
            border-color: #FB9300 !important;
            box-shadow: 0 0 0 1px #FB9300 !important;
        }

        .custom-textarea::placeholder,
        .custom-input::placeholder {
            color: #9ca3af !important;
            font-weight: 400 !important;
        }

        /* DataTables Responsive Custom Styling */
        table.dataTable.dtr-inline.collapsed>tbody>tr>td.dtr-control::before,
        table.dataTable.dtr-inline.collapsed>tbody>tr>th.dtr-control::before,
        table.dataTable.dtr-column.collapsed>tbody>tr>td.dtr-control::before,
        table.dataTable.dtr-column.collapsed>tbody>tr>th.dtr-control::before {
            display: none !important;
        }

        table.dataTable>tbody>tr.child ul.dtr-details {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)) !important;
            gap: 1.5rem !important;
            padding: 1.5rem 2rem !important;
            background-color: #fffaf5 !important;
            border-bottom: 2px solid #fff1e0 !important;
            width: 100% !important;
            list-style-type: none !important;
        }

        table.dataTable>tbody>tr.child ul.dtr-details>li {
            border-bottom: none !important;
            padding: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 0.25rem !important;
        }

        table.dataTable>tbody>tr.child span.dtr-title {
            font-size: 10px !important;
            font-weight: 800 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            color: #94a3b8 !important;
            min-width: unset !important;
        }

        table.dataTable>tbody>tr.child span.dtr-data {
            font-size: 13px !important;
            font-weight: 600 !important;
            color: #343F56 !important;
        }

        table.dataTable.collapsed tbody tr.parent {
            background-color: #fff7ed !important;
        }
    </style>

    @yield('extra_css')
</head>