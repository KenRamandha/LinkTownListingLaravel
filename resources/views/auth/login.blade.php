<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkTown - Modern Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brandOrange: '#FB9300',
                        brandBlue: '#343F56',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at top right, #fef3c7 0%, #ffffff 50%, #f1f5f9 100%);
        }

        .glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(52, 63, 86, 0.1);
        }

        .input-focus:focus {
            border-color: #FB9300;
            box-shadow: 0 0 0 4px rgba(251, 147, 0, 0.1);
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen p-4 overflow-hidden relative">

    <div class="absolute top-[-10%] right-[-5%] w-96 h-96 bg-brandOrange/10 rounded-full blur-3xl"></div>
    <div class="absolute bottom-[-10%] left-[-5%] w-96 h-96 bg-brandBlue/5 rounded-full blur-3xl"></div>

    <div class="w-full max-w-[420px] relative">
        <div class="flex flex-col items-center mb-8">
            <div
                class="w-16 h-16 bg-brandOrange rounded-2xl flex items-center justify-center shadow-2xl shadow-brandOrange/30 mb-5 transform transition-transform hover:scale-105">
                <span class="text-white text-3xl font-extrabold tracking-tighter">L</span>
            </div>
            <h1 class="text-3xl font-bold text-brandBlue tracking-tight">LinkTown</h1>
            <p class="text-gray-500 mt-2 text-sm font-medium">Welcome back! Please enter your details.</p>
        </div>

        <div class="glass rounded-[2.5rem] shadow-2xl shadow-brandBlue/10 p-10">
            <form method="POST" action="{{ route('login.post') }}" class="space-y-6">
                @csrf

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-brandBlue/70 uppercase tracking-widest ml-1">Phone
                        Number</label>
                    <div class="relative group">
                        <span
                            class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 group-focus-within:text-brandOrange transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                        </span>
                        <input type="text" name="phone" value="{{ old('phone') }}" required autofocus
                            class="w-full pl-11 pr-4 py-4 bg-white border border-gray-200 rounded-2xl outline-none transition-all input-focus text-brandBlue placeholder:text-gray-300"
                            placeholder="0812 3456 7890">
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex justify-between items-center px-1">
                        <label
                            class="block text-xs font-bold text-brandBlue/70 uppercase tracking-widest">Password</label>
                    </div>
                    <div class="relative group">
                        <span
                            class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 group-focus-within:text-brandOrange transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </span>
                        <input type="password" name="password" required
                            class="w-full pl-11 pr-4 py-4 bg-white border border-gray-200 rounded-2xl outline-none transition-all input-focus text-brandBlue placeholder:text-gray-300"
                            placeholder="••••••••••••">
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-brandOrange hover:bg-[#e68600] text-white font-bold py-4 rounded-2xl shadow-lg shadow-brandOrange/20 transition-all transform active:scale-[0.98] mt-2">
                    Sign In
                </button>
            </form>

            <div class="mt-10 pt-6 border-t border-gray-100 flex flex-col items-center">
                <p class="text-xs text-gray-400 font-semibold tracking-widest uppercase">
                    © {{ date('Y') }} <span class="text-brandBlue">LinkTown</span>
                </p>
            </div>
        </div>
    </div>

    <div id="loadingOverlay"
        class="fixed inset-0 bg-brandBlue/20 backdrop-blur-md z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
        <div class="bg-white p-10 rounded-[2.5rem] flex flex-col items-center justify-center shadow-2xl">
            <div class="relative w-14 h-14">
                <div class="absolute inset-0 border-4 border-brandOrange/20 rounded-full"></div>
                <div
                    class="absolute inset-0 border-4 border-brandOrange rounded-full border-t-transparent animate-spin">
                </div>
            </div>
            <p class="mt-5 text-brandBlue font-bold tracking-wide">Securing Session...</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const brandColor = '#FB9300';
        const darkBlue = '#343F56';

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: '#ffffff',
            color: darkBlue,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        document.querySelector('form').addEventListener('submit', async function (e) {
            e.preventDefault();
            const form = this;
            const submitBtn = form.querySelector('button[type="submit"]');
            const loadingOverlay = document.getElementById('loadingOverlay');

            loadingOverlay.classList.remove('hidden');
            setTimeout(() => loadingOverlay.classList.add('opacity-100'), 10);
            submitBtn.disabled = true;

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: formData
                });

                const data = await response.json();

                if (response.ok) {
                    await Toast.fire({
                        icon: 'success',
                        title: 'Login Berhasil!',
                        text: 'Mengarahkan ke dashboard...'
                    });
                    window.location.href = data.redirect;
                } else {
                    throw data;
                }
            } catch (error) {
                loadingOverlay.classList.remove('opacity-100');
                setTimeout(() => loadingOverlay.classList.add('hidden'), 300);
                submitBtn.disabled = false;
                Toast.fire({
                    icon: 'error',
                    title: 'Login Gagal',
                    text: 'Periksa kembali nomor hp atau password anda.'
                });
            }
        });
    </script>
</body>

</html>