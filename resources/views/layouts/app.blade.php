<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CriaSys Editor')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-zinc-950 text-zinc-100 min-h-screen antialiased">
    <header class="border-b border-zinc-800 bg-zinc-900/80 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight text-white">
                CriaSys <span class="text-violet-400">Editor</span>
            </a>
            <nav class="flex items-center gap-3 text-sm">
                <span x-data x-show="window.criasys?.isDesktop" class="text-xs text-violet-400 hidden" x-init="$el.classList.remove('hidden')">Desktop</span>
                @yield('header-actions')
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6">
        @if(session('success'))
            <div class="mb-4 rounded-lg bg-emerald-900/40 border border-emerald-700 text-emerald-200 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
