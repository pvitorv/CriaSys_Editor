@extends('layouts.guest')

@section('title', 'Nova senha — CriaSys Editor')
@section('subtitle', 'Defina sua nova senha')

@section('content')
<form method="POST" action="{{ route('password.update') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div>
        <label for="email" class="block text-sm font-medium text-zinc-300 mb-1">E-mail</label>
        <input type="email" name="email" id="email" value="{{ old('email', $email) }}" required
            class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
    </div>
    <div>
        <label for="password" class="block text-sm font-medium text-zinc-300 mb-1">Nova senha</label>
        <input type="password" name="password" id="password" required
            class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
    </div>
    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-zinc-300 mb-1">Confirmar nova senha</label>
        <input type="password" name="password_confirmation" id="password_confirmation" required
            class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
    </div>
    <button type="submit" class="w-full py-2.5 rounded-lg bg-violet-600 hover:bg-violet-500 font-medium transition">
        Redefinir senha
    </button>
</form>
@endsection

@section('footer')
    <p><a href="{{ route('login') }}" class="text-violet-400 hover:text-violet-300">← Voltar ao login</a></p>
@endsection
