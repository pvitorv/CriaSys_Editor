@extends('layouts.guest')

@section('title', 'Recuperar senha — CriaSys Editor')
@section('subtitle', 'Enviaremos um link para seu e-mail')

@section('content')
<form method="POST" action="{{ route('password.email') }}" class="space-y-4">
    @csrf
    <div>
        <label for="email" class="block text-sm font-medium text-zinc-300 mb-1">E-mail cadastrado</label>
        <input type="email" name="email" id="email" value="{{ old('email') }}" required
            class="w-full rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
    </div>
    <button type="submit" class="w-full py-2.5 rounded-lg bg-violet-600 hover:bg-violet-500 font-medium transition">
        Enviar link de recuperação
    </button>
</form>
@endsection

@section('footer')
    <p><a href="{{ route('login') }}" class="text-violet-400 hover:text-violet-300">← Voltar ao login</a></p>
@endsection
