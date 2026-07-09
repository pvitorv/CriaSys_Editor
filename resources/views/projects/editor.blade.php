@extends('layouts.app')

@section('title', $project->name . ' — Editor')

@section('header-actions')
    <a href="{{ route('dashboard') }}" class="text-zinc-400 hover:text-white text-sm">← Dashboard</a>
@endsection

@section('content')
<div
    x-data="editorApp({{ $project->id }}, @js([
        'description' => $project->description ?? '',
        'name' => $project->name,
        'defaultTtsEngine' => app(\App\Services\Tts\TtsEngineFactory::class)->recommendedEngine(),
        'defaultVoice' => config('criasys.tts.default_voice'),
    ]))"
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
            <span x-show="publishAuto && projectCreditsCount" class="text-[11px] text-emerald-400 hidden sm:inline">
                ✓ Publicação automática — <span x-text="projectCreditsCount"></span> crédito(s) já nas descrições
            </span>
            <span x-show="saving" class="text-zinc-500">Salvando...</span>
            <span x-show="message" x-text="message" class="text-emerald-400"></span>
            <span x-show="error" x-text="error" class="text-red-400"></span>
            <button x-show="selectedSlide" @click="saveSlide()" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300">Salvar agora</button>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-4 flex-1 min-h-0 shrink">
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
            <div class="p-3 border-b border-zinc-800 flex items-center justify-between gap-2">
                <h2 class="font-medium text-sm">Preview</h2>
                <div class="flex items-center gap-2">
                    <span x-show="previewPlaying" class="text-[10px] text-violet-400">
                        Slide <span x-text="previewIndex + 1"></span>/<span x-text="slides.length"></span>
                        · <span x-text="(previewSlide?.duration_seconds || 0) + 's'"></span>
                    </span>
                    <button
                        x-show="slides.length > 1"
                        @click="previewPlaying ? stopSlideshow() : playSlideshow()"
                        class="text-xs px-2 py-1 rounded"
                        :class="previewPlaying ? 'bg-red-900/60 text-red-300' : 'bg-violet-700 text-white hover:bg-violet-600'"
                        x-text="previewPlaying ? 'Parar' : '▶ Reproduzir slideshow'"
                    ></button>
                </div>
            </div>
            <div class="flex-1 flex items-center justify-center p-4 overflow-hidden">
                <div class="w-full aspect-video bg-zinc-950 rounded-lg border border-zinc-800 relative overflow-hidden">
                    <div
                        class="absolute inset-0 transition-all duration-500"
                        :class="{
                            'opacity-0': previewTransitioning,
                            'opacity-100': !previewTransitioning,
                            'translate-x-0': previewTransitioning && previewTransitionKind === 'slide',
                            '-translate-x-4': previewTransitioning && previewTransitionKind === 'slide'
                        }"
                    >
                        <template x-if="previewSlide?.video_url">
                            <video
                                :key="'pv-' + previewSlide?.id + '-' + previewPlayToken"
                                :src="previewSlide.video_url"
                                class="absolute inset-0 w-full h-full object-cover opacity-90"
                                autoplay
                                muted
                                playsinline
                                x-ref="previewVideo"
                            ></video>
                        </template>
                        <template x-if="!previewSlide?.video_url && previewSlide?.image_url">
                            <img :src="previewSlide.image_url" class="absolute inset-0 w-full h-full object-cover opacity-80">
                        </template>
                        <div class="absolute inset-0 bg-black/40 flex flex-col items-center justify-center text-center p-6">
                            <h3
                                class="font-bold mb-2"
                                :style="'color:' + (previewSlide?.text_style?.title_color || '#fff') + ';font-size:' + Math.min(previewSlide?.text_style?.title_size || 32, 32) + 'px'"
                                x-text="previewSlide?.title || 'Selecione um slide'"
                            ></h3>
                            <p class="text-lg text-zinc-300 mb-2" x-text="previewSlide?.subtitle || ''"></p>
                            <p class="text-sm text-zinc-400" x-text="previewSlide?.body_text || ''"></p>
                        </div>
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
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden shrink-0">
        <div class="px-4 py-3 border-b border-zinc-800 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-sm text-white">Timeline</h2>
                <p class="text-[11px] text-zinc-500 mt-0.5">
                    <span x-text="slides.length"></span> clip(s) ·
                    <span x-text="formatTimelineTime(timelineTotalSeconds)"></span> total
                    <span x-show="narration?.audio_url" class="text-emerald-400 ml-1">· narração pronta</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="adjustTimelineZoom(-4)" class="w-7 h-7 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-sm" title="Diminuir zoom">−</button>
                <span class="text-[10px] text-zinc-500 w-14 text-center tabular-nums" x-text="timelineZoom + ' px/s'"></span>
                <button type="button" @click="adjustTimelineZoom(4)" class="w-7 h-7 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-sm" title="Aumentar zoom">+</button>
                <button
                    type="button"
                    x-show="slides.length > 1"
                    @click="previewPlaying ? stopSlideshow() : playSlideshow()"
                    class="ml-2 text-xs px-3 py-1.5 rounded-lg"
                    :class="previewPlaying ? 'bg-red-900/50 text-red-300' : 'bg-violet-700 text-white hover:bg-violet-600'"
                    x-text="previewPlaying ? 'Parar' : '▶ Play timeline'"
                ></button>
            </div>
        </div>

        <div x-show="!slides.length" class="px-4 py-8 text-center text-sm text-zinc-500">
            Adicione slides para montar a linha do tempo.
        </div>

        <div x-show="slides.length" x-ref="timelineScroll" class="overflow-x-auto overflow-y-hidden px-4 py-4" style="max-height: 160px;">
            <div class="relative" :style="'width: ' + timelineTrackWidthPx + 'px; min-width: 100%'">
                {{-- Régua de tempo --}}
                <div class="relative h-5 mb-2 border-b border-zinc-700/80">
                    <template x-for="tick in timelineTicks" :key="'tick-' + tick.sec">
                        <div class="absolute top-0 h-full border-l border-zinc-700/60" :style="'left: ' + tick.px + 'px'">
                            <span class="absolute -top-0.5 left-1 text-[9px] text-zinc-600 tabular-nums whitespace-nowrap" x-text="tick.label"></span>
                        </div>
                    </template>
                </div>

                {{-- Faixa de clips --}}
                <div class="flex gap-2 items-stretch relative min-h-[88px]">
                    <template x-for="(slide, index) in slides" :key="'tl-' + slide.id">
                        <div
                            draggable="true"
                            @dragstart="dragStart(index)"
                            @dragover.prevent
                            @drop.prevent="dropSlide(index)"
                            @click="selectSlide(slide)"
                            class="flex-shrink-0 rounded-lg border-2 overflow-hidden cursor-grab active:cursor-grabbing transition-all flex flex-col group"
                            :class="{
                                'border-violet-400 ring-2 ring-violet-500/30 bg-violet-950/40': selectedSlide?.id === slide.id,
                                'border-zinc-600 bg-zinc-800/90 hover:border-zinc-500': selectedSlide?.id !== slide.id,
                                'border-emerald-500/90 ring-2 ring-emerald-500/25': previewPlaying && previewIndex === index,
                            }"
                            :style="'width: ' + timelineClipWidth(slide) + 'px'"
                            :title="(slide.title || 'Slide ' + (index + 1)) + ' · ' + formatTimelineTime(slide.duration_seconds)"
                        >
                            <div class="h-11 bg-zinc-950 relative shrink-0 border-b border-zinc-700/50">
                                <template x-if="slide.image_url">
                                    <img :src="slide.image_url" alt="" class="absolute inset-0 w-full h-full object-cover opacity-75">
                                </template>
                                <template x-if="!slide.image_url">
                                    <div class="absolute inset-0 bg-gradient-to-br from-zinc-800 to-zinc-900"></div>
                                </template>
                                <span class="absolute top-1 left-1 text-[9px] font-medium bg-black/75 px-1.5 py-0.5 rounded text-zinc-200" x-text="index + 1"></span>
                                <span x-show="slide.video_path" class="absolute top-1 right-1 text-[8px] bg-violet-600/90 px-1 rounded text-white">VÍDEO</span>
                                <span x-show="slide.narration_text?.trim()" class="absolute bottom-1 right-1 text-[8px] bg-emerald-900/80 px-1 rounded text-emerald-200">🎙</span>
                            </div>
                            <div class="p-2 flex-1 flex flex-col justify-between min-h-[36px]">
                                <p class="text-[10px] text-zinc-200 font-medium leading-snug line-clamp-2" x-text="slide.title || 'Sem título'"></p>
                                <div class="flex items-center justify-between mt-1 gap-1">
                                    <span class="text-[9px] text-zinc-500 tabular-nums" x-text="formatTimelineTime(slide.duration_seconds)"></span>
                                    <span class="text-[8px] text-zinc-600 uppercase truncate max-w-[4rem]" x-text="slide.transition_type || 'fade'"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Abas inferiores --}}
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 flex-1 min-h-0 flex flex-col overflow-hidden">
        <div class="flex border-b border-zinc-800 shrink-0">
            <template x-for="tab in ['roteiro', 'audio', 'biblioteca', 'exportar']" :key="tab">
                <button
                    @click="switchTab(tab)"
                    :class="activeTab === tab ? 'border-violet-500 text-white' : 'border-transparent text-zinc-400'"
                    class="px-4 py-2 text-sm border-b-2 capitalize"
                    x-text="tab"
                ></button>
            </template>
        </div>

        <div class="p-4 flex-1 min-h-0 overflow-y-auto">
            {{-- Roteiro --}}
            <div x-show="activeTab === 'roteiro'" class="space-y-4">
                <div>
                    <label class="text-xs text-zinc-400">Roteiro completo — cole o texto; o sistema detecta parágrafos, falas, versos e cenas</label>
                    <textarea
                        x-model="fullScript"
                        @input="onFullScriptInput()"
                        @paste="onFullScriptPaste($event)"
                        rows="8"
                        placeholder="Cole aqui o roteiro inteiro...

JOÃO: Olá, como vai?
MARIA: Tudo bem, e você?

Na floresta escura e fria,
O vento sopra todo dia.

[CENA 2 - A revelação]
O narrador continua a história com calma."
                        class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm font-mono"
                    ></textarea>
                    <p x-show="scriptStats" x-cloak class="text-[11px] text-zinc-500 mt-1">
                        Detectado:
                        <strong class="text-zinc-300" x-text="scriptStats?.slides"></strong> slide(s) —
                        <span x-text="scriptStats?.prose"></span> parágrafo(s),
                        <span x-text="scriptStats?.dialogues"></span> fala(s),
                        <span x-text="scriptStats?.verses"></span> verso(s),
                        <span x-text="scriptStats?.refrains || 0"></span> refrão(ões).
                        Ao colar aqui (ou na narração do slide, se for texto grande), divide nos slides automaticamente.
                    </p>
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
                        @paste="onNarrationPaste($event)"
                        rows="4"
                        placeholder="Texto que será lido na narração deste slide..."
                        class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm"
                    ></textarea>
                    <button type="button" @click="copyTitleToNarration()" class="mt-2 text-xs text-violet-400 hover:text-violet-300">Usar título + subtítulo + corpo</button>
                </div>

                <div class="flex flex-wrap gap-3 items-end border-t border-zinc-800 pt-4">
                    <div>
                        <label class="text-xs text-zinc-400">Motor TTS</label>
                        <select x-model="ttsEngine" @change="onEngineChange()" class="block mt-1 rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm min-w-[220px]">
                            <template x-for="eng in ttsEngines" :key="eng.slug">
                                <option :value="eng.slug" :disabled="!eng.available" x-text="eng.name + (eng.available ? '' : ' (indisponível)')"></option>
                            </template>
                        </select>
                        <p x-show="selectedTtsEngineMeta?.price_hint" class="text-[10px] text-zinc-500 mt-1" x-text="selectedTtsEngineMeta?.price_hint"></p>
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
                        <strong class="text-zinc-400">Recomendado:</strong>
                        OpenAI TTS (~US$ 0,015 / 1.000 caracteres — muito mais barato que ElevenLabs) ou
                        <strong class="text-zinc-400">Piper</strong> (100% grátis no seu PC).
                        Edge TTS é gratuito mas a Microsoft bloqueia com frequência (“Output has been disabled”).
                        Configure em <a href="{{ route('integrations.edit') }}" class="text-violet-400 hover:text-violet-300">Integrações</a>.
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
                        placeholder="Buscar em português — ex.: praia, futebol, cachorro, cidade..."
                        class="flex-1 min-w-[200px] rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm"
                    >
                    <button @click="searchFromSlideTitle()" :disabled="!selectedSlide?.title" class="px-3 py-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 disabled:opacity-40 text-sm">Título</button>
                    <button @click="searchMedia()" :disabled="mediaSearching" class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-sm">
                        <span x-text="mediaSearching ? 'Buscando...' : 'Buscar'"></span>
                    </button>
                </div>
                <p x-show="mediaErrors.length && !mediaResults.length" class="text-xs text-yellow-400" x-text="mediaErrors.join(' ')"></p>
                <p class="text-[11px] text-zinc-500">Digite em português — o app traduz automaticamente para as APIs (Openverse, Mixkit, Pexels…).</p>
                <p x-show="mediaResults.length && mediaType === 'image'" class="text-xs text-emerald-400">Clique na imagem para inserir no slide selecionado.</p>
                <p x-show="mediaResults.length && mediaType === 'video'" class="text-xs text-emerald-400">Clique no vídeo para inserir como B-roll no slide selecionado.</p>
                <div x-show="publishAuto && projectCreditsCount" class="rounded border border-emerald-800/40 bg-emerald-950/30 p-2">
                    <p class="text-xs text-emerald-300">
                        ✓ <strong>Publicação automática</strong> — ao inserir mídia da biblioteca, créditos e descrições (YouTube, TikTok, Instagram) são gerados e salvos nos arquivos de exportação. Nada para copiar manualmente.
                    </p>
                </div>
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
                            <p x-show="item.attribution_text" class="absolute top-0 left-0 right-0 bg-black/80 text-[9px] px-1 py-0.5 line-clamp-2 leading-tight opacity-0 group-hover:opacity-100 transition-opacity" x-text="item.attribution_text" title="Crédito sugerido pela plataforma"></p>
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
                </div>

                <div class="rounded-lg border border-emerald-800/50 bg-emerald-950/20 p-3 space-y-3">
                    <h3 class="text-sm font-medium text-zinc-200">Créditos e licenças (automático)</h3>
                    <p class="text-[11px] text-zinc-500">
                        <strong class="text-zinc-400">Biblioteca grátis</strong> (Openverse, Mixkit, Pexels, Pixabay…) → créditos oficiais entram sozinhos nas descrições.
                        <strong class="text-zinc-400">Mídia paga</strong> (Envato, Storyblocks…) → cadastre a licença do projeto abaixo; uploads manuais herdam o registro.
                    </p>
                    <p x-show="!projectCreditsCount" class="text-xs text-zinc-500">Importe da aba Biblioteca ou vincule licença paga para gerar o bloco CRÉDITOS E LICENÇAS.</p>
                    <p x-show="projectCreditsCount" class="text-xs text-emerald-400" x-text="projectCreditsCount + ' material(is) com crédito ou licença nas descrições por plataforma'"></p>
                    <div x-show="Object.keys(publishFiles).length" class="flex flex-wrap gap-1">
                        <template x-for="(file, key) in publishFiles" :key="key">
                            <a :href="file.url" target="_blank" download class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-violet-300" x-text="file.filename || key"></a>
                        </template>
                    </div>
                </div>

                <div class="rounded-lg border border-amber-800/50 bg-amber-950/20 p-3 space-y-3">
                    <h3 class="text-sm font-medium text-zinc-200">Licença de assinatura (Envato, Storyblocks…)</h3>
                    <p class="text-[11px] text-zinc-500">
                        Cadastre <strong class="text-zinc-400">uma vez</strong> o nome do projeto na plataforma (ex.: «Meu vídeo piloto» na Envato).
                        Depois é só baixar os arquivos e fazer upload aqui — não precisa abrir a Envato a cada mídia.
                    </p>

                    <div x-show="stockLicenses.length" class="space-y-2">
                        <template x-for="reg in stockLicenses" :key="reg.id">
                            <div class="flex flex-wrap items-center gap-2 rounded bg-zinc-900/80 border border-zinc-700 px-2 py-2 text-xs">
                                <span class="font-medium text-amber-200" x-text="providerLabel(reg.provider)"></span>
                                <span class="text-zinc-300" x-text="'«' + reg.project_title + '»'"></span>
                                <span x-show="reg.is_default" class="px-1.5 py-0.5 rounded bg-amber-900/60 text-amber-200 text-[10px]">padrão</span>
                                <button
                                    x-show="!reg.is_default"
                                    @click="setDefaultStockLicense(reg)"
                                    type="button"
                                    class="text-[10px] px-2 py-0.5 rounded bg-zinc-700 hover:bg-zinc-600"
                                >Usar como padrão</button>
                                <button
                                    @click="applyStockLicenseToLocal(reg)"
                                    type="button"
                                    class="text-[10px] px-2 py-0.5 rounded bg-amber-800 hover:bg-amber-700 text-white"
                                >Vincular uploads já usados</button>
                                <button
                                    @click="removeStockLicense(reg)"
                                    type="button"
                                    class="text-[10px] px-2 py-0.5 rounded bg-zinc-800 hover:bg-red-900 text-zinc-400"
                                >Remover</button>
                            </div>
                        </template>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="text-xs text-zinc-400 sm:col-span-2">
                            Plataforma
                            <select x-model="stockLicenseForm.provider" class="w-full mt-1 rounded bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm">
                                <template x-for="p in stockLicenseProviders" :key="p.slug">
                                    <option :value="p.slug" x-text="p.name"></option>
                                </template>
                            </select>
                        </label>
                        <label class="text-xs text-zinc-400 sm:col-span-2">
                            Nome do projeto na plataforma
                            <input
                                x-model="stockLicenseForm.project_title"
                                type="text"
                                placeholder="Ex.: Meu documentário 2026"
                                class="w-full mt-1 rounded bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm"
                            >
                        </label>
                        <p x-show="stockLicenseProviderHint" class="text-[10px] text-zinc-500 sm:col-span-2" x-text="stockLicenseProviderHint"></p>
                        <label class="text-xs text-zinc-400">
                            URL da licença (opcional)
                            <input x-model="stockLicenseForm.license_url" type="url" placeholder="https://..." class="w-full mt-1 rounded bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm">
                        </label>
                        <label class="text-xs text-zinc-400">
                            Nota (opcional)
                            <input x-model="stockLicenseForm.license_note" type="text" placeholder="Cliente X, campanha Y" class="w-full mt-1 rounded bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm">
                        </label>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button @click="saveStockLicense()" type="button" class="px-3 py-1.5 rounded bg-amber-700 hover:bg-amber-600 text-sm text-white">Cadastrar licença</button>
                        <label class="flex items-center gap-2 text-xs text-zinc-400 cursor-pointer">
                            <input type="checkbox" x-model="attachPaidLicenseOnUpload" class="rounded border-zinc-600">
                            Aplicar licença padrão em uploads manuais
                        </label>
                    </div>
                </div>

                <div class="rounded-lg border border-zinc-700 p-3 space-y-2">
                    <label class="text-sm font-medium text-zinc-200">Descrição do vídeo (aparece no texto de publicação)</label>
                    <textarea
                        x-model="projectDescription"
                        @input="scheduleDescriptionSave()"
                        rows="3"
                        placeholder="Descreva o conteúdo do vídeo — esta descrição entra automaticamente nos textos do YouTube, TikTok e Instagram."
                        class="w-full rounded bg-zinc-900 border border-zinc-700 px-3 py-2 text-sm text-zinc-300"
                    ></textarea>
                    <p class="text-[10px] text-zinc-500">Salva automaticamente e regenera os textos de publicação com créditos incluídos.</p>
                </div>

                <div class="rounded-lg border border-violet-800/50 bg-violet-950/20 p-3 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-medium text-zinc-200">Descrições prontas por plataforma</h3>
                        <a
                            x-show="publishFiles[selectedPlatformDesc]?.url"
                            :href="publishFiles[selectedPlatformDesc]?.url"
                            target="_blank"
                            download
                            class="text-xs px-2 py-1 rounded bg-violet-700 hover:bg-violet-600 text-white"
                        >Baixar .txt</a>
                    </div>
                    <p class="text-[11px] text-zinc-500">Texto completo com créditos no final — gerado automaticamente ao importar mídia da biblioteca.</p>
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
                                x-text="platformDescriptions[selectedPlatformDesc]?.description || ''"
                            ></textarea>
                        </div>
                    </template>
                    <p x-show="!platformDescriptions[selectedPlatformDesc]" class="text-xs text-zinc-500">Importe mídia da biblioteca para montar as descrições com créditos.</p>
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
