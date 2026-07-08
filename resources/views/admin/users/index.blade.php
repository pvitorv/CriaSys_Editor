@extends('layouts.app')

@section('title', 'Administração — Usuários')

@section('header-actions')
    <a href="{{ route('dashboard') }}" class="text-zinc-400 hover:text-white text-sm">← Dashboard</a>
@endsection

@section('content')
<div>
    <h1 class="text-2xl font-bold mb-2">Administração de usuários</h1>
    <p class="text-zinc-400 text-sm mb-6">Logado como <strong class="text-violet-400">{{ auth()->user()->username }}</strong></p>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-900/40 border border-emerald-700 text-emerald-200 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="space-y-4">
        @foreach($users as $user)
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-semibold">{{ $user->username }}</h2>
                            @if($user->is_admin)
                                <span class="text-xs px-2 py-0.5 rounded bg-violet-900 text-violet-300">Admin</span>
                            @endif
                            <span class="text-xs px-2 py-0.5 rounded {{ $user->isActive() ? 'bg-emerald-900/50 text-emerald-300' : 'bg-yellow-900/50 text-yellow-300' }}">
                                {{ $user->isActive() ? 'Ativo' : 'Pausado' }}
                            </span>
                        </div>
                        <p class="text-sm text-zinc-400">{{ $user->name }} — {{ $user->email }}</p>
                        <p class="text-xs text-zinc-500 mt-1">{{ $user->projects()->count() }} projetos</p>
                    </div>

                    @if($user->id !== auth()->id())
                        <div class="flex flex-wrap gap-2">
                            @if($user->isActive())
                                <form method="POST" action="{{ route('admin.users.pause', $user) }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-yellow-900/50 text-yellow-200 text-xs hover:bg-yellow-900">Pausar</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.users.activate', $user) }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-900/50 text-emerald-200 text-xs hover:bg-emerald-900">Reativar</button>
                                </form>
                            @endif

                            @unless($user->is_admin)
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Excluir {{ $user->username }} permanentemente?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-900/50 text-red-200 text-xs hover:bg-red-900">Excluir</button>
                                </form>
                            @endunless
                        </div>
                    @else
                        <span class="text-xs text-zinc-500">Sua conta</span>
                    @endif
                </div>

                @if($user->id !== auth()->id())
                    <form method="POST" action="{{ route('admin.users.alert', $user) }}" class="mt-4 pt-4 border-t border-zinc-800 space-y-2">
                        @csrf
                        <input type="text" name="subject" placeholder="Assunto do alerta" class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm">
                        <textarea name="message" rows="2" placeholder="Mensagem de alerta para {{ $user->username }}..." required class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm"></textarea>
                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-violet-700 hover:bg-violet-600 text-xs">Enviar alerta</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
