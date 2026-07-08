@extends('layouts.app')

@section('title', $project->name . ' — Editor')

@section('header-actions')
    <a href="{{ route('dashboard') }}" class="text-zinc-400 hover:text-white text-sm">← Dashboard</a>
@endsection

@section('content')
<div
    x-data="editorApp({{ $project->id }})"
    x-init="init()"
    class="flex flex-col gap-4"
    style="height: calc(100vh - 8rem);"
>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold">{{ $project->name }}</h1>
            <p class="text-zinc-500 text-sm">Editor de slideshow</p>
        </div>
        <div class="flex gap-2 text-sm">
            <span x-show="saving" class="text-zinc-500">Salvando...</span>
            <span x-show="message" x-text="message" class="text-emerald-400"></span>
            <span x-show="error" x-text="error" class="text-red-400"></span>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-4 flex-1 min-h-0">
        {{-- Lista de slides --}}
        <div class="col-span-3 flex flex-col min-h-0 rounded-xl border border-zinc-800 bg-zinc-900">
            <div class="p-3 border-b border-zinc-800 flex justify-between items-center">
                <h2 class="font-medium text-sm">Slides</h2>
                <button @click="addSlide()" class="text-violet-400 hover:text-violet-300 text-sm">+ Adicionar</button>
            </div>
            <ul class="flex-1 overflow-y-auto p-2 space-y-1" x-ref="slideList">
                <template x-for="(slide, index) in slides" :key="slide.id">
                    <li
                        @click="selectSlide(slide)"
                        :class="selectedSlide?.id === slide.id ? 'bg-violet-900/40 border-violet-600' : 'bg-zinc-800/50 border-zinc-700 hover:border-zinc-600'"
                        class="rounded-lg border p-2 cursor-pointer transition"
                        :data-id="slide.id"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-zinc-500 w-5" x-text="index + 1"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm truncate" x-text="slide.title || 'Slide sem título'"></p>
                                <p class="text-xs text-zinc-500" x-text="slide.duration_seconds + 's'"></p>
                            </div>
                            <button @click.stop="removeSlide(slide)" class="text-zinc-500 hover:text-red-400 text-xs">✕</button>
                        </div>
                    </li>
                </template>
            </ul>
        </div>

        {{-- Preview --}}
        <div class="col-span-5 flex flex-col min-h-0 rounded-xl border border-zinc-800 bg-zinc-900">
            <div class="p-3 border-b border-zinc-800">
                <h2 class="font-medium text-sm">Preview</h2>
            </div>
            <div class="flex-1 flex items-center justify-center p-4 overflow-hidden">
                <div class="w-full aspect-video bg-zinc-950 rounded-lg border border-zinc-800 relative overflow-hidden">
                    <template x-if="selectedSlide?.image_url">
                        <img :src="selectedSlide.image_url" class="absolute inset-0 w-full h-full object-cover opacity-80">
                    </template>
                    <div class="absolute inset-0 bg-black/40 flex flex-col items-center justify-center text-center p-6">
                        <h3 class="text-2xl font-bold mb-2" x-text="selectedSlide?.title || ''"></h3>
                        <p class="text-lg text-zinc-300 mb-2" x-text="selectedSlide?.subtitle || ''"></p>
                        <p class="text-sm text-zinc-400" x-text="selectedSlide?.body_text || ''"></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Propriedades --}}
        <div class="col-span-4 flex flex-col min-h-0 rounded-xl border border-zinc-800 bg-zinc-900 overflow-y-auto">
            <div class="p-3 border-b border-zinc-800">
                <h2 class="font-medium text-sm">Propriedades</h2>
            </div>
            <div class="p-4 space-y-3" x-show="selectedSlide">
                <div>
                    <label class="text-xs text-zinc-400">Título</label>
                    <input type="text" x-model="selectedSlide.title" @change="saveSlide()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Subtítulo</label>
                    <input type="text" x-model="selectedSlide.subtitle" @change="saveSlide()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Corpo</label>
                    <textarea x-model="selectedSlide.body_text" @change="saveSlide()" rows="3" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm"></textarea>
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Duração (s)</label>
                    <input type="number" step="0.1" min="0.5" x-model.number="selectedSlide.duration_seconds" @change="saveSlide()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Transição</label>
                    <select x-model="selectedSlide.transition_type" @change="saveSlide()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                        <option value="fade">Fade</option>
                        <option value="cut">Corte</option>
                        <option value="slide">Slide</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Imagem</label>
                    <input type="file" accept="image/*" @change="uploadImage($event)" class="w-full mt-1 text-sm text-zinc-400">
                </div>
            </div>
        </div>
    </div>

    {{-- Timeline --}}
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-3">
        <h2 class="font-medium text-sm mb-2">Timeline</h2>
        <div class="flex gap-1 h-8">
            <template x-for="slide in slides" :key="'tl-' + slide.id">
                <div
                    class="rounded bg-violet-800/60 border border-violet-600/50 flex items-center justify-center text-xs truncate px-1"
                    :style="'flex: ' + slide.duration_seconds"
                    x-text="slide.title || 'Slide'"
                ></div>
            </template>
        </div>
    </div>

    {{-- Abas inferiores --}}
    <div class="rounded-xl border border-zinc-800 bg-zinc-900">
        <div class="flex border-b border-zinc-800">
            <template x-for="tab in ['roteiro', 'biblioteca', 'exportar']" :key="tab">
                <button
                    @click="activeTab = tab"
                    :class="activeTab === tab ? 'border-violet-500 text-white' : 'border-transparent text-zinc-400'"
                    class="px-4 py-2 text-sm border-b-2 capitalize"
                    x-text="tab"
                ></button>
            </template>
        </div>

        <div class="p-4">
            {{-- Roteiro --}}
            <div x-show="activeTab === 'roteiro'" class="space-y-4">
                <div x-show="selectedSlide">
                    <label class="text-xs text-zinc-400">Texto de narração (slide selecionado)</label>
                    <textarea x-model="selectedSlide.narration_text" @change="saveSlide()" rows="4" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm"></textarea>
                </div>
                <div class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="text-xs text-zinc-400">Voz</label>
                        <select x-model="voice" class="block mt-1 rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm">
                            <option value="pt-BR-FranciscaNeural">Francisca (feminina)</option>
                            <option value="pt-BR-AntonioNeural">Antonio (masculino)</option>
                        </select>
                    </div>
                    <button @click="generateNarration()" :disabled="narrationLoading" class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-sm">
                        <span x-text="narrationLoading ? 'Gerando...' : 'Gerar narração'"></span>
                    </button>
                    <button @click="syncNarration()" class="px-4 py-2 rounded-lg bg-zinc-700 hover:bg-zinc-600 text-sm">
                        Sincronizar slides
                    </button>
                </div>
                <template x-if="narration?.audio_url">
                    <audio :src="narration.audio_url" controls class="w-full"></audio>
                </template>
            </div>

            {{-- Biblioteca --}}
            <div x-show="activeTab === 'biblioteca'" class="space-y-3">
                <div class="flex gap-2">
                    <input type="text" x-model="mediaQuery" placeholder="Buscar imagens no Pexels..." class="flex-1 rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm">
                    <button @click="searchMedia()" class="px-4 py-2 rounded-lg bg-zinc-700 hover:bg-zinc-600 text-sm">Buscar</button>
                </div>
                <div class="grid grid-cols-4 gap-2 max-h-48 overflow-y-auto">
                    <template x-for="photo in mediaResults" :key="photo.id">
                        <div class="relative group cursor-pointer rounded overflow-hidden border border-zinc-700" @click="importPhoto(photo)">
                            <img :src="photo.preview_url" class="w-full h-20 object-cover">
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center text-xs">Inserir</div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Exportar --}}
            <div x-show="activeTab === 'exportar'" class="space-y-4">
                <div class="flex flex-wrap gap-3">
                    <button @click="renderVideo('youtube_landscape')" class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-sm">
                        Renderizar MP4 16:9
                    </button>
                    <button @click="generateThumb()" class="px-4 py-2 rounded-lg bg-zinc-700 hover:bg-zinc-600 text-sm">
                        Gerar thumbnail
                    </button>
                </div>
                <div class="space-y-2">
                    <h3 class="text-sm font-medium text-zinc-300">Fila de render</h3>
                    <template x-for="job in renderJobs" :key="job.id">
                        <div class="rounded-lg bg-zinc-800 p-3 text-sm">
                            <div class="flex justify-between mb-1">
                                <span x-text="job.preset"></span>
                                <span x-text="job.status" :class="job.status === 'completed' ? 'text-emerald-400' : job.status === 'failed' ? 'text-red-400' : 'text-yellow-400'"></span>
                            </div>
                            <div class="w-full bg-zinc-700 rounded h-1.5">
                                <div class="bg-violet-500 h-1.5 rounded transition-all" :style="'width:' + job.progress + '%'"></div>
                            </div>
                            <p x-show="job.error_log" x-text="job.error_log" class="text-red-400 text-xs mt-1"></p>
                            <a x-show="job.output_url" :href="job.output_url" target="_blank" class="text-violet-400 text-xs mt-1 inline-block">Abrir vídeo</a>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
