@extends('layouts.app')

@section('title', 'Dashboard — CriaSys Editor')

@section('header-actions')
    <a href="{{ route('projects.create') }}" class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-white font-medium transition">
        Novo projeto
    </a>
@endsection

@section('content')
<div>
    <h1 class="text-2xl font-bold mb-2">Projetos recentes</h1>
    <p class="text-zinc-400 mb-6">Gerador de slideshow narrado — uso local.</p>

    @if($projects->isEmpty())
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-8 text-center">
            <p class="text-zinc-400 mb-4">Nenhum projeto ainda.</p>
            <a href="{{ route('projects.create') }}" class="inline-block px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-white">
                Criar primeiro projeto
            </a>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($projects as $project)
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5 hover:border-zinc-700 transition">
                    <h2 class="font-semibold text-lg mb-1">{{ $project->name }}</h2>
                    @if($project->description)
                        <p class="text-zinc-400 text-sm mb-3 line-clamp-2">{{ $project->description }}</p>
                    @endif
                    <p class="text-xs text-zinc-500 mb-4">{{ $project->updated_at->diffForHumans() }}</p>
                    <div class="flex gap-2">
                        <a href="{{ route('projects.editor', $project) }}" class="px-3 py-1.5 rounded-md bg-zinc-800 hover:bg-zinc-700 text-sm">
                            Abrir
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
