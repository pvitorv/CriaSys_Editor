<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CriaSys Editor')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-zinc-950 text-zinc-100 min-h-screen antialiased" x-data="alertsApp()" x-init="init()">
    <header class="border-b border-zinc-800 bg-zinc-900/80 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight text-white">
                CriaSys <span class="text-violet-400">Editor</span>
            </a>
            <nav class="flex items-center gap-3 text-sm">
                <span x-data x-show="window.criasys?.isDesktop" class="text-xs text-violet-400 hidden" x-init="$el.classList.remove('hidden')">Desktop</span>
                @auth
                    <span class="text-zinc-400 hidden sm:inline">{{ auth()->user()->username }}</span>
                    <a href="{{ route('integrations.edit') }}" class="text-zinc-400 hover:text-white">Integrações</a>
                    <a href="{{ route('profile.edit') }}" class="text-zinc-400 hover:text-white">Conta</a>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.users.index') }}" class="text-violet-400 hover:text-violet-300">Admin</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-zinc-500 hover:text-red-400">Sair</button>
                    </form>
                @endauth
                @yield('header-actions')
            </nav>
        </div>
    </header>

    <div x-show="alerts.length" x-cloak class="border-b border-yellow-800/50 bg-yellow-950/40">
        <div class="max-w-7xl mx-auto px-4 py-3 space-y-2">
            <template x-for="alert in alerts" :key="alert.id">
                <div class="flex items-start justify-between gap-4 text-sm">
                    <div>
                        <strong class="text-yellow-200" x-text="alert.subject || 'Alerta'"></strong>
                        <span class="text-yellow-600/80 text-xs ml-2" x-text="alert.from_user ? 'de ' + alert.from_user.username : ''"></span>
                        <p class="text-yellow-100/90 mt-0.5" x-text="alert.message"></p>
                    </div>
                    <button @click="dismiss(alert.id)" class="text-yellow-400 hover:text-yellow-200 text-xs shrink-0">Dispensar</button>
                </div>
            </template>
        </div>
    </div>

    <main class="@yield('main-class', 'max-w-7xl mx-auto px-4 py-6')">
        @if(session('success'))
            <div class="mb-4 rounded-lg bg-emerald-900/40 border border-emerald-700 text-emerald-200 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
