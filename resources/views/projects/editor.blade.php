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
        <div class="flex gap-2 text-sm items-center">
            <span x-show="saving" class="text-zinc-500">Salvando...</span>
            <span x-show="message" x-text="message" class="text-emerald-400"></span>
            <span x-show="error" x-text="error" class="text-red-400"></span>
            <button x-show="selectedSlide" @click="saveSlide()" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300">Salvar agora</button>
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
                        draggable="true"
                        @dragstart="dragStart(index)"
                        @dragover.prevent
                        @drop.prevent="dropSlide(index)"
                        :class="selectedSlide?.id === slide.id ? 'bg-violet-900/40 border-violet-600' : 'bg-zinc-800/50 border-zinc-700 hover:border-zinc-600'"
                        class="rounded-lg border p-2 cursor-grab active:cursor-grabbing transition"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-zinc-600 select-none" title="Arrastar">⋮⋮</span>
                            <span class="text-xs text-zinc-500 w-5" x-text="index + 1"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm truncate" x-text="slide.title || 'Slide sem título'"></p>
                                <p class="text-xs text-zinc-500">
                                    <span x-text="slide.duration_seconds + 's'"></span>
                                    <span x-show="slide.video_path" class="text-violet-400 ml-1">▶ vídeo</span>
                                </p>
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
                    <template x-if="selectedSlide?.video_url">
                        <video :src="selectedSlide.video_url" class="absolute inset-0 w-full h-full object-cover opacity-90" autoplay muted loop playsinline></video>
                    </template>
                    <template x-if="!selectedSlide?.video_url && selectedSlide?.image_url">
                        <img :src="selectedSlide.image_url" class="absolute inset-0 w-full h-full object-cover opacity-80">
                    </template>
                    <div class="absolute inset-0 bg-black/40 flex flex-col items-center justify-center text-center p-6">
                        <h3
                            class="font-bold mb-2"
                            :style="'color:' + (selectedSlide?.text_style?.title_color || '#fff') + ';font-size:' + Math.min(selectedSlide?.text_style?.title_size || 32, 32) + 'px'"
                            x-text="selectedSlide?.title || 'Selecione um slide'"
                        ></h3>
                        <p class="text-lg text-zinc-300 mb-2" x-text="selectedSlide?.subtitle || ''"></p>
                        <p class="text-sm text-zinc-400" x-text="selectedSlide?.body_text || ''"></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Propriedades --}}
        <div class="col-span-4 flex flex-col min-h-0 rounded-xl border border-zinc-800 bg-zinc-900 overflow-y-auto">
            <div class="p-3 border-b border-zinc-800 flex justify-between items-center">
                <h2 class="font-medium text-sm">Propriedades</h2>
                <button x-show="selectedSlide" @click="searchFromSlideTitle()" class="text-xs text-violet-400 hover:text-violet-300">Buscar imagem pelo título</button>
            </div>
            <div class="p-4 space-y-3" x-show="selectedSlide">
                <div>
                    <label class="text-xs text-zinc-400">Título</label>
                    <input type="text" x-model="selectedSlide.title" @input="scheduleSave()" placeholder="Título do slide" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Subtítulo</label>
                    <input type="text" x-model="selectedSlide.subtitle" @input="scheduleSave()" placeholder="Subtítulo opcional" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Corpo</label>
                    <textarea x-model="selectedSlide.body_text" @input="scheduleSave()" rows="3" placeholder="Texto visível no slide" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-zinc-400">Duração (s)</label>
                        <input type="number" step="0.1" min="0.5" x-model.number="selectedSlide.duration_seconds" @input="scheduleSave()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-zinc-400">Transição</label>
                        <select x-model="selectedSlide.transition_type" @change="scheduleSave()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                            <option value="fade">Fade</option>
                            <option value="cut">Corte</option>
                            <option value="slide">Slide</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-zinc-400">Cor do título</label>
                        <input type="color" x-model="selectedSlide.text_style.title_color" @input="scheduleSave()" class="w-full mt-1 h-9 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                    </div>
                    <div>
                        <label class="text-xs text-zinc-400">Tamanho título</label>
                        <input type="number" min="12" max="96" x-model.number="selectedSlide.text_style.title_size" @input="scheduleSave()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Imagem de fundo</label>
                    <input type="file" accept="image/*" @change="uploadImage($event)" class="w-full mt-1 text-sm text-zinc-400">
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Vídeo curto (B-roll)</label>
                    <input type="file" accept="video/*" @change="uploadVideo($event)" class="w-full mt-1 text-sm text-zinc-400">
                    <button x-show="selectedSlide?.video_path" type="button" @click="clearSlideVideo()" class="mt-1 text-xs text-red-400 hover:text-red-300">Remover vídeo do slide</button>
                </div>
                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="button" @click="copyTitleToNarration()" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700">Título → narração</button>
                    <button type="button" @click="saveSlide()" class="text-xs px-2 py-1 rounded bg-violet-700 hover:bg-violet-600">Salvar propriedades</button>
                </div>
            </div>
            <p x-show="!selectedSlide && slides.length" class="p-4 text-sm text-zinc-500">Selecione um slide na lista.</p>
            <p x-show="!slides.length" class="p-4 text-sm text-zinc-500">Adicione um slide para começar.</p>
        </div>
    </div>

    {{-- Timeline --}}
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-3">
        <h2 class="font-medium text-sm mb-2">Timeline</h2>
        <div class="flex gap-1 h-8">
            <template x-for="slide in slides" :key="'tl-' + slide.id">
                <div
                    class="rounded bg-violet-800/60 border border-violet-600/50 flex items-center justify-center text-xs truncate px-1 cursor-pointer"
                    :class="selectedSlide?.id === slide.id ? 'ring-1 ring-violet-400' : ''"
                    @click="selectSlide(slide)"
                    :style="'flex: ' + slide.duration_seconds"
                    x-text="slide.title || 'Slide'"
                ></div>
            </template>
        </div>
    </div>

    {{-- Abas inferiores --}}
    <div class="rounded-xl border border-zinc-800 bg-zinc-900">
        <div class="flex border-b border-zinc-800">
            <template x-for="tab in ['roteiro', 'audio', 'biblioteca', 'exportar']" :key="tab">
                <button
                    @click="switchTab(tab)"
                    :class="activeTab === tab ? 'border-violet-500 text-white' : 'border-transparent text-zinc-400'"
                    class="px-4 py-2 text-sm border-b-2 capitalize"
                    x-text="tab"
                ></button>
            </template>
        </div>

        <div class="p-4">
            {{-- Roteiro --}}
            <div x-show="activeTab === 'roteiro'" class="space-y-4">
                <div>
                    <label class="text-xs text-zinc-400">Roteiro completo (cole ou escreva — parágrafos separados por linha em branco)</label>
                    <textarea
                        x-model="fullScript"
                        rows="5"
                        placeholder="Parágrafo do slide 1...

Parágrafo do slide 2...

Parágrafo do slide 3..."
                        class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm font-mono"
                    ></textarea>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <button type="button" @click="applyFullScript()" class="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-sm">Aplicar roteiro nos slides</button>
                        <button type="button" @click="buildFullScriptFromSlides()" class="px-3 py-1.5 rounded-lg bg-zinc-700 hover:bg-zinc-600 text-sm">Carregar dos slides</button>
                    </div>
                </div>

                <div x-show="selectedSlide" class="border-t border-zinc-800 pt-4">
                    <label class="text-xs text-zinc-400">Narração do slide selecionado</label>
                    <textarea
                        x-model="selectedSlide.narration_text"
                        @input="scheduleSave()"
                        rows="4"
                        placeholder="Texto que será lido na narração deste slide..."
                        class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm"
                    ></textarea>
                    <button type="button" @click="copyTitleToNarration()" class="mt-2 text-xs text-violet-400 hover:text-violet-300">Usar título + subtítulo + corpo</button>
                </div>

                <div class="flex flex-wrap gap-3 items-end border-t border-zinc-800 pt-4">
                    <div>
                        <label class="text-xs text-zinc-400">Motor TTS</label>
                        <select x-model="ttsEngine" @change="onEngineChange()" class="block mt-1 rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm">
                            <template x-for="eng in ttsEngines" :key="eng.slug">
                                <option :value="eng.slug" :disabled="!eng.available" x-text="eng.name + (eng.available ? '' : ' (indisponível)')"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-zinc-400">Voz</label>
                        <select x-model="voice" class="block mt-1 rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm">
                            <template x-if="voicesLoading">
                                <option value="">Carregando vozes...</option>
                            </template>
                            <template x-if="!voicesLoading && !voices.length">
                                <option value="">Nenhuma voz — configure em Integrações</option>
                            </template>
                            <template x-for="v in voices" :key="v.id">
                                <option :value="v.id" x-text="v.name"></option>
                            </template>
                        </select>
                    </div>
                    <button @click="testNarration()" :disabled="previewLoading || narrationLoading" class="px-4 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-600 disabled:opacity-50 text-sm">
                        <span x-text="previewLoading ? 'Gerando teste...' : '▶ Testar voz (slide atual)'"></span>
                    </button>
                    <button @click="generateNarration()" :disabled="narrationLoading || previewLoading" class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-sm">
                        <span x-text="narrationLoading ? 'Gerando...' : 'Gerar narração completa'"></span>
                    </button>
                    <button @click="syncNarration()" class="px-4 py-2 rounded-lg bg-zinc-700 hover:bg-zinc-600 text-sm">
                        Sincronizar duração
                    </button>
                    <p class="w-full text-xs text-zinc-500">
                        Quer usar sua própria voz? Conecte a ElevenLabs em
                        <a href="{{ route('integrations.edit') }}" class="text-violet-400 hover:text-violet-300">Integrações</a>
                        e sua voz clonada aparece aqui.
                    </p>
                </div>

                <div class="rounded-lg border border-zinc-700 bg-zinc-800/50 p-4 space-y-3">
                    <h3 class="text-sm font-medium text-zinc-300">Ouvir áudio</h3>
                    <p x-show="!previewAudioUrl && !narration?.audio_url" class="text-xs text-zinc-500">
                        Use <strong>Testar voz</strong> para ouvir o slide atual, ou <strong>Gerar narração completa</strong> para todos os slides.
                    </p>
                    <div x-show="previewAudioUrl">
                        <p class="text-xs text-emerald-400 mb-1">Teste do slide</p>
                        <audio :src="previewAudioUrl" controls class="w-full" preload="auto"></audio>
                    </div>
                    <div x-show="narration?.audio_url">
                        <p class="text-xs text-violet-400 mb-1">Narração completa do projeto</p>
                        <audio :src="narration.audio_url" controls class="w-full" preload="auto"></audio>
                    </div>
                </div>
            </div>

            {{-- Áudio --}}
            <div x-show="activeTab === 'audio'" class="space-y-4">
                <div class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="text-xs text-zinc-400">Volume trilha</label>
                        <input type="range" min="0" max="1" step="0.05" x-model.number="audioTrack.volume" @change="saveAudioTrack()" class="block w-40 mt-1">
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" x-model="audioTrack.ducking_enabled" @change="saveAudioTrack()">
                        Ducking (abaixa trilha na narração)
                    </label>
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Importar trilha local (MP3/WAV)</label>
                    <input type="file" accept="audio/*" @change="uploadAudio($event)" class="w-full mt-1 text-sm text-zinc-400">
                </div>
                <template x-if="audioTrack?.file_path">
                    <p class="text-xs text-emerald-400">Trilha configurada</p>
                </template>
            </div>

            {{-- Biblioteca --}}
            <div x-show="activeTab === 'biblioteca'" class="space-y-3">
                <p class="text-xs text-zinc-500">Imagens (Openverse grátis) e vídeos curtos (Mixkit grátis + Pexels/Pixabay com chave). Áudio para trilha.</p>
                <div class="flex flex-wrap gap-2">
                    <select x-model="mediaSource" class="rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm">
                        <option value="all">Todas (gratuitas + APIs)</option>
                        <option value="openverse">Openverse (sem chave)</option>
                        <option value="pexels">Pexels</option>
                        <option value="pixabay">Pixabay</option>
                        <option value="unsplash">Unsplash</option>
                        <option value="mixkit">Mixkit (áudio)</option>
                    </select>
                    <select x-model="mediaType" class="rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm">
                        <option value="image">Imagens</option>
                        <option value="video">Vídeos curtos</option>
                        <option value="audio">Áudio</option>
                    </select>
                    <input
                        type="text"
                        x-model="mediaQuery"
                        @keydown.enter.prevent="searchMedia()"
                        placeholder="Buscar pelo título ou palavra-chave..."
                        class="flex-1 min-w-[200px] rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm"
                    >
                    <button @click="searchFromSlideTitle()" :disabled="!selectedSlide?.title" class="px-3 py-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 disabled:opacity-40 text-sm">Título</button>
                    <button @click="searchMedia()" :disabled="mediaSearching" class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-sm">
                        <span x-text="mediaSearching ? 'Buscando...' : 'Buscar'"></span>
                    </button>
                </div>
                <p x-show="mediaErrors.length && !mediaResults.length" class="text-xs text-yellow-400" x-text="mediaErrors.join(' ')"></p>
                <p x-show="mediaResults.length && mediaType === 'image'" class="text-xs text-emerald-400">Clique na imagem para inserir no slide selecionado.</p>
                <p x-show="mediaResults.length && mediaType === 'video'" class="text-xs text-emerald-400">Clique no vídeo para inserir como B-roll no slide selecionado.</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 max-h-56 overflow-y-auto">
                    <template x-for="item in mediaResults" :key="item.source + '-' + item.id">
                        <div class="relative group cursor-pointer rounded overflow-hidden border border-zinc-700 bg-zinc-800" @click="importMedia(item)">
                            <template x-if="item.type === 'audio'">
                                <div class="h-24 flex items-center justify-center text-xs text-center p-2" x-text="item.title || item.author || 'Áudio'"></div>
                            </template>
                            <template x-if="item.type === 'video'">
                                <div class="relative h-24">
                                    <img :src="item.preview_url" :alt="item.author || 'Vídeo'" class="w-full h-24 object-cover" loading="lazy">
                                    <span class="absolute inset-0 flex items-center justify-center text-2xl text-white/90">▶</span>
                                    <span x-show="item.duration_seconds" class="absolute bottom-1 right-1 text-[10px] bg-black/80 px-1 rounded" x-text="item.duration_seconds + 's'"></span>
                                </div>
                            </template>
                            <template x-if="item.type !== 'audio' && item.type !== 'video'">
                                <img :src="item.preview_url" :alt="item.title || 'Imagem'" class="w-full h-24 object-cover" loading="lazy">
                            </template>
                            <span class="absolute bottom-0 left-0 right-0 bg-black/70 text-[10px] px-1 py-0.5 truncate" x-text="item.source"></span>
                            <span x-show="item.requires_attribution || item.attribution_text" class="absolute top-1 right-1 text-[10px] bg-yellow-600/80 px-1 rounded" title="Crédito ao autor">©</span>
                            <div class="absolute inset-0 bg-violet-900/60 opacity-0 group-hover:opacity-100 flex items-center justify-center text-xs font-medium">Inserir</div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Exportar --}}
            <div x-show="activeTab === 'exportar'" class="space-y-4">
                <div>
                    <h3 class="text-sm font-medium text-zinc-300 mb-2">Render vídeo</h3>
                    <label class="flex items-center gap-2 text-sm text-zinc-400 mb-3">
                        <input type="checkbox" x-model="burnSubtitles">
                        Queimar legendas no vídeo (burn-in via SRT)
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="preset in exportPresets.filter(p => p.slug !== 'thumbnail')" :key="preset.slug">
                            <button @click="renderVideo(preset.slug)" class="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-xs" x-text="preset.name"></button>
                        </template>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button @click="generateThumb()" class="px-4 py-2 rounded-lg bg-zinc-700 hover:bg-zinc-600 text-sm">Gerar thumbnail</button>
                    <button @click="exportSubtitles()" class="px-4 py-2 rounded-lg bg-zinc-700 hover:bg-zinc-600 text-sm">Exportar legendas.srt</button>
                    <button @click="exportPsd()" class="px-4 py-2 rounded-lg bg-zinc-700 hover:bg-zinc-600 text-sm">Exportar slides PSD</button>
                    <button @click="exportPackage()" class="px-4 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-600 text-sm">Pacote Premiere/Affinity</button>
                    <button @click="generatePlatformDescriptions()" :disabled="platformDescLoading" class="px-4 py-2 rounded-lg bg-violet-700 hover:bg-violet-600 disabled:opacity-50 text-sm">
                        <span x-text="platformDescLoading ? 'Gerando...' : 'Gerar descrições + créditos'"></span>
                    </button>
                </div>

                <div class="rounded-lg border border-violet-800/50 bg-violet-950/20 p-3 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-medium text-zinc-200">Descrições para publicar (com créditos dos autores)</h3>
                        <button @click="copyPlatformDescription()" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700">Copiar descrição</button>
                    </div>
                    <p class="text-[11px] text-zinc-500">Ao importar imagens/vídeos da biblioteca, o sistema registra os créditos. A seção <strong class="text-zinc-400">CRÉDITOS E LICENÇAS</strong> fica no final de cada texto — cole na descrição do YouTube, TikTok ou Instagram.</p>
                    <div class="flex flex-wrap gap-1">
                        <template x-for="key in platformDescKeys" :key="key">
                            <button
                                @click="selectedPlatformDesc = key"
                                :class="selectedPlatformDesc === key ? 'bg-violet-600 text-white' : 'bg-zinc-800 text-zinc-400'"
                                class="px-2 py-1 rounded text-xs capitalize"
                                x-text="platformDescriptions[key]?.platform || key.replace('_', ' ')"
                            ></button>
                        </template>
                    </div>
                    <template x-if="platformDescriptions[selectedPlatformDesc]">
                        <div>
                            <p class="text-[10px] text-zinc-500 mb-1">
                                <span x-text="platformDescriptions[selectedPlatformDesc].materials_count + ' material(is) com crédito · ' + platformDescriptions[selectedPlatformDesc].char_count + ' caracteres'"></span>
                            </p>
                            <textarea
                                readonly
                                rows="12"
                                class="w-full rounded bg-zinc-900 border border-zinc-700 px-3 py-2 text-xs font-mono text-zinc-300"
                                :value="platformDescriptions[selectedPlatformDesc]?.description || ''"
                            ></textarea>
                        </div>
                    </template>
                    <p x-show="!platformDescriptions[selectedPlatformDesc]" class="text-xs text-zinc-500">Clique em &quot;Gerar descrições + créditos&quot; ou importe mídia da biblioteca primeiro.</p>
                </div>

                <div class="rounded-lg border border-zinc-700 p-3 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-medium text-zinc-300">Central de mídias — escolha o que baixar</h3>
                        <div class="flex gap-2">
                            <button @click="selectAllReadyDownloads()" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700">Selecionar prontos</button>
                            <button @click="downloadSelected()" class="text-xs px-2 py-1 rounded bg-violet-700 hover:bg-violet-600">Baixar selecionados</button>
                        </div>
                    </div>
                    <p class="text-[11px] text-zinc-500">Todos os vídeos, pacotes, legendas, narração e thumbs gerados aparecem aqui. Marque e baixe só o que precisar.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-zinc-500 border-b border-zinc-700">
                                    <th class="py-1 pr-2 text-left w-8"></th>
                                    <th class="py-1 pr-2 text-left">Tipo</th>
                                    <th class="py-1 pr-2 text-left">Arquivo</th>
                                    <th class="py-1 pr-2 text-left">Formato</th>
                                    <th class="py-1 pr-2 text-left">Status</th>
                                    <th class="py-1 pr-2 text-right">Tamanho</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in downloads" :key="item.id">
                                    <tr class="border-b border-zinc-800/80">
                                        <td class="py-1.5 pr-2">
                                            <input type="checkbox" :disabled="item.status !== 'ready' || !item.url" :checked="selectedDownloadIds.includes(item.id)" @change="toggleDownload(item.id)">
                                        </td>
                                        <td class="py-1.5 pr-2 capitalize" x-text="item.category"></td>
                                        <td class="py-1.5 pr-2">
                                            <span x-text="item.label"></span>
                                            <a x-show="item.url" :href="item.url" target="_blank" class="text-violet-400 ml-1 hover:text-violet-300">↗</a>
                                        </td>
                                        <td class="py-1.5 pr-2" x-text="item.format"></td>
                                        <td class="py-1.5 pr-2">
                                            <span x-text="item.status" :class="item.status === 'ready' ? 'text-emerald-400' : item.status === 'failed' ? 'text-red-400' : 'text-yellow-400'"></span>
                                            <span x-show="item.progress && item.status !== 'ready'" x-text="' (' + item.progress + '%)'" class="text-zinc-500"></span>
                                        </td>
                                        <td class="py-1.5 text-right text-zinc-400" x-text="formatBytes(item.size)"></td>
                                    </tr>
                                </template>
                                <tr x-show="!downloads.length">
                                    <td colspan="6" class="py-3 text-zinc-500 text-center">Nenhuma mídia gerada ainda. Use os botões acima para renderizar ou exportar.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-2">
                    <h3 class="text-sm font-medium text-zinc-300">Pacotes de export</h3>
                    <template x-for="pkg in exportPackages" :key="pkg.id">
                        <div class="rounded-lg bg-zinc-800 p-2 text-xs flex justify-between items-center gap-2">
                            <span x-text="'Pacote #' + pkg.id"></span>
                            <div class="flex items-center gap-2">
                                <span x-text="pkg.status" :class="pkg.status === 'completed' ? 'text-emerald-400' : pkg.status === 'failed' ? 'text-red-400' : 'text-yellow-400'"></span>
                                <a x-show="pkg.download_url" :href="pkg.download_url" download class="text-violet-400 hover:text-violet-300">Baixar ZIP</a>
                            </div>
                        </div>
                    </template>
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
                            <div class="flex gap-2 mt-2">
                                <button x-show="job.status === 'failed'" @click="retryRender(job)" class="text-xs text-yellow-400 hover:text-yellow-300">Tentar novamente</button>
                                <a x-show="job.output_url" :href="job.output_url" target="_blank" class="text-violet-400 text-xs">Abrir vídeo</a>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
