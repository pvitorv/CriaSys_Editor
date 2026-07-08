@extends('layouts.app')

@section('title', 'Minha conta — CriaSys Editor')

@section('header-actions')
    <a href="{{ route('dashboard') }}" class="text-zinc-400 hover:text-white text-sm">← Dashboard</a>
@endsection

@section('content')
<div class="max-w-2xl mx-auto space-y-8">
    <div>
        <h1 class="text-2xl font-bold">Minha conta</h1>
        <p class="text-zinc-400 text-sm">Olá, <strong class="text-zinc-200">{{ $user->username }}</strong> ({{ $user->email }})</p>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-emerald-900/40 border border-emerald-700 text-emerald-200 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
        <h2 class="font-semibold mb-4">Alterar e-mail</h2>
        <p class="text-xs text-zinc-500 mb-4">É necessário informar sua senha atual.</p>
        <form method="POST" action="{{ route('profile.email') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm text-zinc-400 mb-1">Senha atual</label>
                <input type="password" name="current_password" required class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2">
            </div>
            <div>
                <label class="block text-sm text-zinc-400 mb-1">Novo e-mail</label>
                <input type="email" name="email" value="{{ $user->email }}" required class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2">
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-sm">Salvar e-mail</button>
        </form>
    </div>

    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
        <h2 class="font-semibold mb-4">Alterar senha</h2>
        <p class="text-xs text-zinc-500 mb-4">É necessário informar sua senha atual. Ou use <a href="{{ route('password.request') }}" class="text-violet-400">recuperação por e-mail</a>.</p>
        <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm text-zinc-400 mb-1">Senha atual</label>
                <input type="password" name="current_password" required class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2">
            </div>
            <div>
                <label class="block text-sm text-zinc-400 mb-1">Nova senha</label>
                <input type="password" name="password" required class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2">
            </div>
            <div>
                <label class="block text-sm text-zinc-400 mb-1">Confirmar nova senha</label>
                <input type="password" name="password_confirmation" required class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2">
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-sm">Salvar senha</button>
        </form>
    </div>
</div>
@endsection
