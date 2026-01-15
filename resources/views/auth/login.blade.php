<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkTown - Modern Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top left, #eff6ff 0%, #dbeafe 50%, #f8fafc 100%);
        }

        .glass {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen p-4 overflow-hidden relative">

    <div class="absolute top-[-10%] left-[-5%] w-72 h-72 bg-blue-400/10 rounded-full blur-3xl"></div>
    <div class="absolute bottom-[-10%] right-[-5%] w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl"></div>

    <div class="w-full max-w-[440px] relative">
        <div class="flex flex-col items-center mb-10">
            <div
                class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-200 mb-4 rotate-3 hover:rotate-0 transition-transform duration-500">
                <span class="text-white text-3xl font-bold tracking-tighter">L</span>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">LinkTown</h1>
            <p class="text-gray-500 mt-2 text-sm">Access your workspace and dashboard.</p>
        </div>

        <div class="glass rounded-[2rem] shadow-2xl shadow-blue-900/5 p-10 border border-white">
            <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
                @csrf

                <div class="space-y-2">
                    <label class="block text-[13px] font-bold text-gray-700 uppercase tracking-wider ml-1">Phone
                        Number</label>
                    <div class="relative group">
                        <span
                            class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 group-focus-within:text-blue-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                        </span>
                        <input type="text" name="phone" value="{{ old('phone') }}" required autofocus
                            class="w-full pl-11 pr-4 py-3.5 bg-white/50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all placeholder:text-gray-300 @error('phone') border-red-500 @enderror"
                            placeholder="0812 3456 7890">
                    </div>
                    @error('phone')
                        <p class="text-xs text-red-500 ml-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2 text-right">
                    <label
                        class="block text-[13px] font-bold text-gray-700 uppercase tracking-wider text-left ml-1">Password</label>
                    <div class="relative group">
                        <span
                            class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 group-focus-within:text-blue-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </span>
                        <input type="password" name="password" required
                            class="w-full pl-11 pr-4 py-3.5 bg-white/50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all placeholder:text-gray-300 @error('password') border-red-500 @enderror"
                            placeholder="••••••••••••">
                    </div>
                    @error('password')
                        <p class="text-xs text-red-500 text-left ml-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-blue-200 transition-all transform active:scale-[0.98]">
                    Sign In
                </button>
            </form>

            <div class="mt-8 flex items-center justify-center gap-2">
                <span class="w-8 h-[1px] bg-gray-200"></span>
                <p class="text-xs text-gray-400 font-medium tracking-tight">© {{ date('Y') }} LINKTOWN</p>
                <span class="w-8 h-[1px] bg-gray-200"></span>
            </div>
        </div>
    </div>

</body>

</html>