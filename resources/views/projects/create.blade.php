@extends('layouts.app')

@section('title', 'Novo projeto — CriaSys Editor')

@section('header-actions')
    <a href="{{ route('dashboard') }}" class="text-zinc-400 hover:text-white">← Voltar</a>
@endsection

@section('content')
<div class="max-w-lg">
    <h1 class="text-2xl font-bold mb-6">Novo projeto</h1>

    <form method="POST" action="{{ route('projects.store') }}" class="space-y-5" x-data="{ templateId: '' }">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-zinc-300 mb-1">Nome</label>
            <input type="text" name="name" id="name" required value="{{ old('name') }}"
                class="w-full rounded-lg bg-zinc-900 border border-zinc-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500">
            @error('name')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-zinc-300 mb-1">Descrição</label>
            <textarea name="description" id="description" rows="3"
                class="w-full rounded-lg bg-zinc-900 border border-zinc-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500">{{ old('description') }}</textarea>
        </div>

        <div>
            <label for="template_id" class="block text-sm font-medium text-zinc-300 mb-1">Template</label>
            <select name="template_id" id="template_id" x-model="templateId"
                class="w-full rounded-lg bg-zinc-900 border border-zinc-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500">
                <option value="">Em branco (manual)</option>
                @foreach($templates as $template)
                    <option value="{{ $template->id }}" @selected(old('template_id') == $template->id)>
                        {{ $template->name }} ({{ $template->aspect_ratio }})
                    </option>
                @endforeach
            </select>
            @foreach($templates as $template)
                <p x-show="templateId == '{{ $template->id }}'" class="text-xs text-zinc-500 mt-1">{{ $template->description }}</p>
            @endforeach
        </div>

        <div x-show="!templateId">
            <label class="block text-sm font-medium text-zinc-300 mb-2">Proporção base</label>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="aspect_ratio" value="16:9" checked class="text-violet-500">
                    <span>16:9 (YouTube)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="aspect_ratio" value="9:16" class="text-violet-500">
                    <span>9:16 (Shorts/Reels)</span>
                </label>
            </div>
        </div>

        <button type="submit" class="px-5 py-2.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-white font-medium">
            Criar projeto
        </button>
    </form>
</div>
@endsection
