<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Entrar — CriaSys Editor')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-zinc-950 text-zinc-100 min-h-screen antialiased flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <a href="/" class="text-2xl font-bold tracking-tight text-white">
                CriaSys <span class="text-violet-400">Editor</span>
            </a>
            <p class="text-zinc-500 text-sm mt-2">@yield('subtitle', 'Acesso ao sistema')</p>
        </div>

        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6 shadow-xl">
            @if(session('status'))
                <div class="mb-4 rounded-lg bg-emerald-900/40 border border-emerald-700 text-emerald-200 px-4 py-3 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-lg bg-red-900/40 border border-red-700 text-red-200 px-4 py-3 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>

        @hasSection('footer')
            <div class="text-center mt-6 text-sm text-zinc-500">
                @yield('footer')
            </div>
        @endif
    </div>
</body>
</html>
