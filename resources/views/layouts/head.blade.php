<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'LinkTown') }} - @yield('title', 'Dashboard')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            letter-spacing: -0.01em;
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
            background: #94a3b8;
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
            color: #64748b !important;
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
            color: #475569 !important;
            padding: 0.5rem 0.8rem !important;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #2563eb !important;
            color: white !important;
            border-color: #2563eb !important;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current) {
            background: #f8fafc !important;
            color: #2563eb !important;
            border-color: #cbd5e1 !important;
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
            border-color: #d1d5db !important;
        }

        .select2-container--default.select2-container--focus .select2-selection--single,
        .select2-container--default.select2-container--open .select2-selection--single {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 1px #3b82f6 !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 14px !important;
            padding-right: 4.75rem !important;
            font-size: 0.875rem !important;
            color: #111827 !important;
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
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 1px #3b82f6 !important;
            outline: none !important;
        }

        .select2-results__option {
            padding: 10px 14px !important;
            font-size: 0.875rem !important;
        }

        .select2-container--default .select2-results__option--selected {
            background-color: #eff6ff !important;
            color: #1d4ed8 !important;
            font-weight: 600 !important;
        }

        .select2-container--default .select2-results__option--highlighted {
            background-color: #3b82f6 !important;
            color: #ffffff !important;
        }

        .select2-container--default.select2-container--disabled .select2-selection--single {
            background-color: #f9fafb !important;
            cursor: not-allowed !important;
        }

        .select2-container--default.select2-container--disabled .select2-selection__rendered {
            color: #9ca3af !important;
        }

        .custom-textarea {
            width: 100% !important;
            border-radius: 0.75rem !important;
            border: 1px solid #e5e7eb !important;
            background-color: #ffffff !important;
            padding: 10px 14px !important;
            font-size: 0.875rem !important;
            color: #111827 !important;
            font-weight: 500 !important;
            line-height: 1.5 !important;
            transition: all 0.2s ease-in-out !important;
            outline: none !important;
            font-family: 'Inter', sans-serif !important;
        }

        .custom-textarea:hover {
            border-color: #d1d5db !important;
        }

        .custom-textarea:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 1px #3b82f6 !important;
        }

        .custom-textarea::placeholder {
            color: #9ca3af !important;
            font-weight: 400 !important;
        }

        .custom-input {
            width: 100% !important;
            border-radius: 0.75rem !important;
            border: 1px solid #e5e7eb !important;
            background-color: #ffffff !important;
            padding: 10px 14px !important;
            font-size: 0.875rem !important;
            color: #111827 !important;
            font-weight: 500 !important;
            line-height: 1.5 !important;
            transition: all 0.2s ease-in-out !important;
            outline: none !important;
            font-family: 'Inter', sans-serif !important;
        }

        .custom-input:hover {
            border-color: #d1d5db !important;
        }

        .custom-input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 1px #3b82f6 !important;
        }

        .custom-input::placeholder {
            color: #9ca3af !important;
            font-weight: 400 !important;
        }
    </style>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal2-popup {
            font-family: 'Inter', sans-serif;
            border-radius: 20px;
        }

        .swal2-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }

        .swal2-content {
            color: #64748b;
        }
    </style>

    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        window.toast = (icon, title) => {
            Toast.fire({
                icon: icon,
                title: title
            });
        }
    </script>

    @yield('extra_css')
</head>