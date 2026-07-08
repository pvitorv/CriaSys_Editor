@extends('layouts.guest')

@section('title', 'Entrar — CriaSys Editor')

@section('content')
<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf
    <div>
        <label for="login" class="block text-sm font-medium text-zinc-300 mb-1">Usuário ou e-mail</label>
        <input type="text" name="login" id="login" value="{{ old('login') }}" required autofocus
            class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
    </div>
    <div>
        <label for="password" class="block text-sm font-medium text-zinc-300 mb-1">Senha</label>
        <input type="password" name="password" id="password" required
            class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
    </div>
    <label class="flex items-center gap-2 text-sm text-zinc-400">
        <input type="checkbox" name="remember" class="rounded border-zinc-600 text-violet-500">
        Lembrar de mim
    </label>
    <button type="submit" class="w-full py-2.5 rounded-lg bg-violet-600 hover:bg-violet-500 font-medium transition">
        Entrar
    </button>
</form>
@endsection

@section('footer')
    <p><a href="{{ route('password.request') }}" class="text-violet-400 hover:text-violet-300">Esqueci minha senha</a></p>
    <p class="mt-2">Não tem conta? <a href="{{ route('register') }}" class="text-violet-400 hover:text-violet-300">Cadastre-se</a></p>
@endsection
