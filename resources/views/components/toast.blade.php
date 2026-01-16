<div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .swal2-popup {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            border-radius: 16px !important;
            padding: 0.75rem !important;
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(52, 63, 86, 0.1) !important;
        }

        .swal2-title {
            font-size: 0.95rem !important;
            font-weight: 700 !important;
            color: #343F56 !important;
            margin: 0 !important;
            padding-left: 0.5rem !important;
        }

        .swal2-timer-progress-bar {
            background: #FB9300 !important;
            height: 3px !important;
        }

        .swal2-icon {
            transform: scale(0.7) !important;
            margin: 0 !important;
        }
    </style>

    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: '#ffffff',
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

        document.addEventListener('DOMContentLoaded', function () {
            @if(session('success'))
                window.toast('success', "{{ session('success') }}");
            @elseif(session('error'))
                window.toast('error', "{{ session('error') }}");
            @elseif(session('warning'))
                window.toast('warning', "{{ session('warning') }}");
            @elseif(session('info'))
                window.toast('info', "{{ session('info') }}");
            @endif
        });
    </script>
</div>