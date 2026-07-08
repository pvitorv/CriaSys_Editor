@extends('layouts.guest')

@section('title', 'Cadastro — CriaSys Editor')
@section('subtitle', 'Crie sua conta')

@section('content')
<form method="POST" action="{{ route('register') }}" class="space-y-4">
    @csrf
    <div>
        <label for="name" class="block text-sm font-medium text-zinc-300 mb-1">Nome completo</label>
        <input type="text" name="name" id="name" value="{{ old('name') }}" required
            class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
    </div>
    <div>
        <label for="username" class="block text-sm font-medium text-zinc-300 mb-1">Usuário</label>
        <input type="text" name="username" id="username" value="{{ old('username') }}" required pattern="[A-Za-z0-9_-]+"
            class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
        <p class="text-xs text-zinc-500 mt-1">Letras, números, _ e -</p>
    </div>
    <div>
        <label for="email" class="block text-sm font-medium text-zinc-300 mb-1">E-mail</label>
        <input type="email" name="email" id="email" value="{{ old('email') }}" required
            class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
    </div>
    <div>
        <label for="password" class="block text-sm font-medium text-zinc-300 mb-1">Senha</label>
        <input type="password" name="password" id="password" required
            class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
    </div>
    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-zinc-300 mb-1">Confirmar senha</label>
        <input type="password" name="password_confirmation" id="password_confirmation" required
            class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
    </div>
    <button type="submit" class="w-full py-2.5 rounded-lg bg-violet-600 hover:bg-violet-500 font-medium transition">
        Criar conta
    </button>
</form>
@endsection

@section('footer')
    <p>Já tem conta? <a href="{{ route('login') }}" class="text-violet-400 hover:text-violet-300">Entrar</a></p>
@endsection
