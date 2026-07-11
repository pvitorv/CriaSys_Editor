@extends('layouts.app')

@section('title', 'Dashboard — CriaSys Editor')

@section('header-actions')
    <button
        x-data
        x-show="window.criasys"
        @click="window.criasys?.openExportsFolder()"
        class="text-zinc-400 hover:text-white text-sm hidden"
        x-init="$el.classList.remove('hidden')"
    >Abrir exports</button>
    <a href="{{ route('projects.create') }}"
       @class([
           'px-4 py-2 rounded-lg font-medium transition',
           'bg-violet-600 hover:bg-violet-500 text-white' => $canCreate,
           'bg-zinc-700 text-zinc-500 cursor-not-allowed pointer-events-none' => !$canCreate,
       ])
       @if(!$canCreate) aria-disabled="true" @endif
    >
        Novo projeto
    </a>
@endsection

@section('content')
<div x-data="dashboardApp()">
    <h1 class="text-2xl font-bold mb-2">Projetos recentes</h1>
    @if($deployment['is_online'])
        <p class="text-amber-400/90 text-sm mb-2">Modo online — {{ $deployment['max_active_projects'] }} projeto ativo por vez. Exporte e exclua para iniciar o próximo.</p>
        <p class="text-xs text-zinc-500 mb-2">Configure links do canal em <a href="{{ route('profile.edit') }}" class="text-violet-400 hover:text-violet-300 underline">Minha conta</a> · Veja <code class="text-zinc-400">DOCS_DEPLOYMENT.md</code> no repositório.</p>
    @else
        <p class="text-zinc-400 mb-2">Modo desktop — projetos limitados apenas pelo espaço em disco.</p>
    @endif
    <p class="text-xs text-zinc-500 mb-6">Atalhos no editor: Ctrl+S salvar · Ctrl+N novo slide · Ctrl+Shift+S sincronizar narração</p>

    @if($deployment['is_desktop'] || $canCreate)
        <div class="mb-6">
            <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-sm text-zinc-200 cursor-pointer">
                <span>Importar projeto (ZIP bundle)</span>
                <input type="file" accept=".zip,application/zip" class="hidden" @change="importBundle($event)">
            </label>
            <p class="text-[11px] text-zinc-500 mt-2">Restaura slides, áudio, assets e descrições de um bundle exportado anteriormente.</p>
        </div>
    @elseif($deployment['is_online'])
        <p class="text-xs text-amber-400/80 mb-6">Exporte e exclua o projeto atual para importar outro bundle.</p>
    @endif

    <p x-show="message" x-text="message" class="text-emerald-400 text-sm mb-4"></p>
    <p x-show="error" x-text="error" class="text-red-400 text-sm mb-4"></p>

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
                    <p class="text-xs text-zinc-500 mb-4">{{ $project->updated_at->diffForHumans() }}
                        @if($project->status === 'exported')
                            <span class="text-emerald-400 ml-1">· exportado</span>
                        @endif
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('projects.editor', $project) }}" class="px-3 py-1.5 rounded-md bg-zinc-800 hover:bg-zinc-700 text-sm">
                            Abrir
                        </a>
                        @if($deployment['is_desktop'])
                        <button @click="duplicateProject({{ $project->id }})" class="px-3 py-1.5 rounded-md bg-zinc-800 hover:bg-zinc-700 text-sm text-zinc-300">
                            Duplicar
                        </button>
                        @endif
                        <button @click="archiveProject({{ $project->id }})" class="px-3 py-1.5 rounded-md bg-zinc-800 hover:bg-zinc-700 text-sm text-zinc-400">
                            Arquivar
                        </button>
                        <button @click="deleteProject({{ $project->id }})" class="px-3 py-1.5 rounded-md bg-red-900/40 hover:bg-red-900/60 text-sm text-red-300">
                            Excluir
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
