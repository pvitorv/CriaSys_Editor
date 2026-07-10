@extends('layouts.app')

@section('main-class', 'w-full max-w-none px-0 py-4')

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
    class="flex flex-col gap-4 w-[70vw] max-w-[70vw] mx-auto px-1 sm:px-2 pb-10"
    @resize.window="syncTimelineZoomToViewport(); syncTopPanelHeight()"
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

    <div class="grid grid-cols-12 gap-4 items-start">
        {{-- Lista de slides — mesma altura do preview, scroll interno --}}
        <div
            class="col-span-3 flex flex-col min-h-0 rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden"
            :style="topPanelHeight ? 'height:' + topPanelHeight + 'px' : ''"
        >
            <div class="p-3 border-b border-zinc-800 flex justify-between items-center shrink-0">
                <h2 class="font-medium text-sm">Slides</h2>
                <button @click="addSlide()" class="text-violet-400 hover:text-violet-300 text-sm">+ Adicionar</button>
            </div>
            <ul class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-2 space-y-1" x-ref="slideList">
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
                                <p class="text-sm truncate" x-text="slidePreviewText(slide, index)"></p>
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

        {{-- Preview — reprodutor 16:9 inteiro; define a altura da linha --}}
        <div class="col-span-4 flex flex-col rounded-xl border border-zinc-800 bg-zinc-900" x-ref="previewColumn">
            <div class="p-3 border-b border-zinc-800 flex items-center justify-between gap-2 shrink-0">
                <h2 class="font-medium text-sm">Preview</h2>
                <div class="flex items-center gap-2">
                    <span x-show="previewPlaying" class="text-[10px] text-violet-400" x-text="previewModeLabel"></span>
                    <span x-show="previewPlaying && slides.length" class="text-[10px] text-zinc-500">
                        · <span x-text="(previewSlide?.duration_seconds || 0) + 's'"></span>
                    </span>
                    <span x-show="narration?.audio_url && !previewPlaying" class="text-[10px] text-emerald-400">narração pronta</span>
                    <button
                        x-show="canPlayPreview"
                        @click="previewPlaying ? stopSlideshow() : playSlideshow()"
                        class="text-xs px-2 py-1 rounded"
                        :class="previewPlaying ? 'bg-red-900/60 text-red-300' : 'bg-violet-700 text-white hover:bg-violet-600'"
                        x-text="previewPlaying ? 'Parar' : (slides.length ? '▶ Reproduzir' : '▶ Preview roteiro')"
                    ></button>
                </div>
            </div>
            <div class="p-4">
                <div class="relative w-full aspect-video rounded-lg border border-zinc-800 overflow-hidden shadow-lg shadow-black/40 bg-black">
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
                        <template x-if="previewSlide && !previewSlide?.video_url && previewSlide?.image_url">
                            <img :src="previewSlide.image_url" class="absolute inset-0 w-full h-full object-cover opacity-80">
                        </template>
                        <div
                            class="absolute inset-0 flex flex-col items-center text-center p-4 sm:p-6 overflow-hidden"
                            :class="previewVerticalAlignClass()"
                            :style="previewSlide?.video_url || previewSlide?.image_url ? 'background: rgba(0,0,0,0.45)' : 'background: #000'"
                        >
                            <p
                                class="font-medium leading-relaxed whitespace-pre-line max-w-prose w-full"
                                :style="previewTextStyle()"
                                x-text="previewDisplayText || (canPlayPreview ? '' : 'Cole o roteiro ou adicione slides')"
                            ></p>
                            <p x-show="!previewDisplayText && !slides.length" class="text-xs text-zinc-500 mt-4">Tela preta — texto e narração aparecem aqui</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Propriedades — mesma altura do preview, scroll interno --}}
        <div
            class="col-span-5 flex flex-col min-h-0 rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden"
            :style="topPanelHeight ? 'height:' + topPanelHeight + 'px' : ''"
        >
            <div class="p-3 border-b border-zinc-800 flex justify-between items-center shrink-0">
                <h2 class="font-medium text-sm">Propriedades</h2>
                <button x-show="selectedSlide" @click="searchFromSlideBody()" class="text-xs text-violet-400 hover:text-violet-300">Buscar imagem pelo texto</button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
            <div class="p-4 space-y-3" x-show="selectedSlide">
                <div>
                    <label class="text-xs text-zinc-400">Corpo</label>
                    <textarea x-model="selectedSlide.body_text" @input="scheduleSave()" rows="5" placeholder="Texto visível no slide" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-zinc-400">Duração (s)</label>
                        <input type="number" step="0.1" min="0.5" x-model.number="selectedSlide.duration_seconds" @input="onManualDurationChange()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-zinc-400">Modo de tempo</label>
                        <select x-model="selectedSlide.duration_mode" @change="onDurationModeChange()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                            <option value="narration">Narração (3–15s)</option>
                            <option value="video" :disabled="!selectedSlide?.video_path">Vídeo (corrido)</option>
                            <option value="manual">Manual</option>
                        </select>
                    </div>
                </div>
                <p class="text-[10px] text-zinc-500 -mt-1">
                    <span x-show="selectedSlide?.duration_mode === 'narration'">Tempo proporcional às palavras/linhas de cada slide (mín. 3s, máx. 15s).</span>
                    <span x-show="selectedSlide?.duration_mode === 'video'">Usa a duração real do vídeo B-roll — narração corre junto.</span>
                    <span x-show="selectedSlide?.duration_mode === 'manual'">Você define o tempo manualmente acima.</span>
                </p>
                <div class="flex flex-wrap gap-2 -mt-1">
                    <button type="button" @click="recalculateNarrationDurations()" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700">Recalcular tempos (narração)</button>
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Transição</label>
                    <select x-model="selectedSlide.transition_type" @change="scheduleSave()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                        <option value="fade">Fade</option>
                        <option value="cut">Corte</option>
                        <option value="slide">Slide</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-zinc-400">Cor do texto</label>
                        <input type="color" x-model="selectedSlide.text_style.body_color" @input="syncBodyTextStyle()" class="w-full mt-1 h-9 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                    </div>
                    <div>
                        <label class="text-xs text-zinc-400">Tamanho do texto</label>
                        <input type="number" min="12" max="96" x-model.number="selectedSlide.text_style.body_size" @input="syncBodyTextStyle()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-zinc-400">Posição na tela</label>
                    <select x-model="selectedSlide.text_style.vertical_align" @change="scheduleSave()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                        <option value="top">Alto</option>
                        <option value="center">Centro</option>
                        <option value="bottom">Baixo</option>
                    </select>
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
                    <button type="button" @click="copyBodyToNarration()" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700">Corpo → narração</button>
                    <button type="button" @click="saveSlide()" class="text-xs px-2 py-1 rounded bg-violet-700 hover:bg-violet-600">Salvar propriedades</button>
                </div>
            </div>
            <p x-show="!selectedSlide && slides.length" class="p-4 text-sm text-zinc-500">Selecione um slide na lista.</p>
            <p x-show="!slides.length" class="p-4 text-sm text-zinc-500">Adicione um slide para começar.</p>
            </div>
        </div>
    </div>

    {{-- Timeline — 70% da largura da tela --}}
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden shrink-0 w-full">
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
                <button
                    type="button"
                    @click="openAudioTab()"
                    class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300"
                    title="Trilhas sonoras e efeitos especiais"
                >
                    🎵 Trilhas & FX
                    <span x-show="audioModulesCount" class="text-violet-400" x-text="'(' + audioModulesCount + ')'"></span>
                </button>
                <button type="button" @click="adjustTimelineZoom(-4)" class="w-7 h-7 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-sm" title="Diminuir zoom">−</button>
                <span class="text-[10px] text-zinc-500 w-14 text-center tabular-nums" x-text="timelineZoom + ' px/s'"></span>
                <button type="button" @click="adjustTimelineZoom(4)" class="w-7 h-7 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-sm" title="Aumentar zoom">+</button>
                <button type="button" @click="resetTimelineZoom()" class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-400" title="Ajustar à largura da tela">Ajustar</button>
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

        <div x-show="slides.length" x-ref="timelineScroll" class="overflow-x-auto overflow-y-auto px-4 py-4 w-full" style="max-height: min(340px, 28vh);">
            <div class="relative" :style="'width: ' + timelineTrackWidthPx + 'px; min-width: ' + timelineViewportWidthPx() + 'px'">
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
                            :title="slidePreviewText(slide, index) + ' · ' + formatTimelineTime(slide.duration_seconds)"
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
                                <p class="text-[10px] text-zinc-200 font-medium leading-snug line-clamp-2" x-text="slidePreviewText(slide, index)"></p>
                                <div class="flex items-center justify-between mt-1 gap-1">
                                    <span class="text-[9px] text-zinc-500 tabular-nums" x-text="formatTimelineTime(slide.duration_seconds)"></span>
                                    <span class="text-[8px] text-zinc-600 uppercase truncate max-w-[4rem]" x-text="slide.transition_type || 'fade'"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Faixas de áudio --}}
                <div x-show="showTimelineAudioLanes" class="mt-3 pt-3 border-t border-zinc-800 space-y-1.5">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-[10px] text-zinc-500 uppercase tracking-wide">Áudio</p>
                        <button type="button" @click="openAudioTab()" class="text-[10px] text-violet-400 hover:text-violet-300">Editar trilhas & efeitos →</button>
                    </div>

                    <div class="flex items-center gap-2 h-6">
                        <span class="w-[4.5rem] shrink-0 text-[9px] text-emerald-500/90 truncate">Narração</span>
                        <div class="relative h-5 rounded border border-zinc-800 bg-zinc-950/60" :style="'width: ' + Math.max(timelineTrackWidthPx, 120) + 'px'">
                            <div
                                x-show="narration?.audio_url"
                                class="absolute inset-y-0 left-0 rounded bg-emerald-900/40 border border-emerald-700/40"
                                :style="'width: ' + timelineSecondsToPx(timelineTotalSeconds) + 'px'"
                                title="Narração completa"
                            ></div>
                        </div>
                    </div>

                    <template x-for="(track, slot) in audioTracks" :key="'tl-audio-' + slot">
                        <div class="flex items-center gap-2 h-6">
                            <span class="w-[4.5rem] shrink-0 text-[9px] text-amber-500/90 truncate" x-text="track.label"></span>
                            <div class="relative h-5 rounded border border-zinc-800 bg-zinc-950/60" :style="'width: ' + Math.max(timelineTrackWidthPx, 120) + 'px'">
                                <div
                                    x-show="track.file_path"
                                    class="absolute inset-y-0 rounded bg-amber-900/35 border border-amber-700/40 cursor-pointer hover:bg-amber-900/50"
                                    :style="'left: ' + timelineSecondsToPx(track.start_at) + 'px; width: ' + timelineAudioSpanWidth(track.start_at) + 'px'"
                                    :title="track.label + ' · vol ' + Math.round((track.volume ?? 0.35) * 100) + '% · início ' + formatTimelineTime(track.start_at)"
                                    @click="openAudioTab()"
                                ></div>
                            </div>
                        </div>
                    </template>

                    <div class="flex items-start gap-2 min-h-[26px]">
                        <span class="w-[4.5rem] shrink-0 text-[9px] text-rose-400/90 pt-1">Efeitos</span>
                        <div class="relative h-6 rounded border border-zinc-800 bg-zinc-950/60" :style="'width: ' + Math.max(timelineTrackWidthPx, 120) + 'px'">
                            <template x-for="fx in soundEffects" :key="'tl-fx-' + fx.id">
                                <button
                                    type="button"
                                    class="absolute top-0 h-5 rounded px-1 truncate text-left bg-rose-900/50 border border-rose-600/50 hover:bg-rose-900/70 text-[8px] text-rose-100"
                                    :class="selectedSoundEffectId === fx.id ? 'ring-1 ring-rose-400' : ''"
                                    :style="'left: ' + timelineSecondsToPx(fx.start_at) + 'px; width: ' + timelineFxClipWidth() + 'px; max-width: 120px'"
                                    :title="(fx.label || 'Efeito') + ' · ' + formatTimelineTime(fx.start_at)"
                                    @click="selectSoundEffect(fx)"
                                    x-text="fx.label || 'FX'"
                                ></button>
                            </template>
                            <span x-show="!soundEffects.length" class="absolute inset-0 flex items-center justify-center text-[9px] text-zinc-600 pointer-events-none">
                                clique em Trilhas & FX para adicionar
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Abas inferiores — mesma largura da timeline --}}
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 flex flex-col w-full">
        <div class="flex border-b border-zinc-800 shrink-0">
            <template x-for="tab in editorTabs" :key="tab.id">
                <button
                    @click="switchTab(tab.id)"
                    :class="activeTab === tab.id ? 'border-violet-500 text-white' : 'border-transparent text-zinc-400'"
                    class="px-4 py-2 text-sm border-b-2 flex items-center gap-1.5"
                >
                    <span x-text="tab.label"></span>
                    <span
                        x-show="tab.id === 'audio' && audioModulesCount"
                        class="text-[10px] bg-violet-600/80 text-white px-1.5 py-0.5 rounded-full tabular-nums"
                        x-text="audioModulesCount"
                    ></span>
                </button>
            </template>
        </div>

        <div class="p-4">
            {{-- Roteiro --}}
            <div x-show="activeTab === 'roteiro'" class="space-y-4">
                <div>
                    <label class="text-xs text-zinc-400">Roteiro completo — cole o texto; o sistema divide em slides automaticamente</label>
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
                        Detectado: <strong class="text-zinc-300" x-text="scriptStats?.slides"></strong> slide(s).
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
                    <button type="button" @click="copyBodyToNarration()" class="mt-2 text-xs text-violet-400 hover:text-violet-300">Usar corpo do slide</button>
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
            <div x-show="activeTab === 'audio'" class="space-y-5">
                <div>
                    <h3 class="text-sm font-medium text-white mb-1">Trilhas sonoras (até 3)</h3>
                    <p class="text-[11px] text-zinc-500 mb-3">Mix de trilhas com volume e início independentes. Ducking abaixa a trilha durante a narração (trilha 1).</p>
                    <button type="button" @click="openLibraryForMusic(selectedMusicSlot ?? 0)" class="mb-3 text-xs px-3 py-1.5 rounded-lg bg-amber-900/40 border border-amber-700/40 text-amber-200 hover:bg-amber-900/60">
                        🔍 Buscar trilha na biblioteca
                    </button>
                    <div class="space-y-3">
                        <template x-for="(track, slot) in audioTracks" :key="'music-' + slot">
                            <div class="rounded-lg border border-zinc-800 bg-zinc-950/50 p-3 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-medium text-zinc-200" x-text="track.label"></span>
                                    <span x-show="track.file_path" class="text-[10px] text-emerald-400">ativa</span>
                                    <button x-show="track.id" type="button" @click="removeMusicTrack(slot)" class="text-[10px] text-red-400 hover:text-red-300">Remover</button>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <div>
                                        <label class="text-[10px] text-zinc-500">Volume</label>
                                        <input type="range" min="0" max="1" step="0.05" x-model.number="track.volume" @change="saveMusicTrack(slot)" class="block w-full mt-1">
                                    </div>
                                    <div>
                                        <label class="text-[10px] text-zinc-500">Início (s)</label>
                                        <input type="number" min="0" step="0.1" x-model.number="track.start_at" @change="saveMusicTrack(slot)" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1 text-xs">
                                    </div>
                                    <div x-show="slot === 0" class="flex items-end">
                                        <label class="flex items-center gap-1 text-[10px] text-zinc-400">
                                            <input type="checkbox" x-model="track.ducking_enabled" @change="saveMusicTrack(slot)">
                                            Ducking
                                        </label>
                                    </div>
                                    <div class="flex items-end">
                                        <label class="text-[10px] text-violet-400 cursor-pointer hover:text-violet-300">
                                            <input type="file" accept="audio/*" class="hidden" @change="selectedMusicSlot = slot; uploadAudio($event)">
                                            Importar MP3/WAV
                                        </label>
                                    </div>
                                </div>
                                <audio x-show="track.audio_url" :src="track.audio_url" controls class="w-full h-8 mt-1" preload="metadata"></audio>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="border-t border-zinc-800 pt-4">
                    <h3 class="text-sm font-medium text-white mb-1">Efeitos sonoros</h3>
                    <p class="text-[11px] text-zinc-500 mb-3">Posicione efeitos em qualquer segundo da linha do tempo do preview.</p>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button type="button" @click="openLibraryForSfx(0)" class="text-xs px-3 py-1.5 rounded-lg bg-rose-900/40 border border-rose-700/40 text-rose-200 hover:bg-rose-900/60">
                            🔍 Buscar efeito na biblioteca
                        </button>
                        <label class="text-xs px-3 py-1.5 rounded-lg bg-violet-700 hover:bg-violet-600 cursor-pointer">
                            + Adicionar efeito
                            <input type="file" accept="audio/*" class="hidden" @change="uploadSoundEffect($event)">
                        </label>
                    </div>
                    <div x-show="!soundEffects.length" class="text-xs text-zinc-500 py-4 text-center border border-dashed border-zinc-800 rounded-lg">
                        Nenhum efeito — importe sons curtos (impacto, risada, ambiente…)
                    </div>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        <template x-for="fx in soundEffects" :key="'fx-' + fx.id">
                            <div
                                class="rounded-lg border bg-zinc-950/50 p-3"
                                :class="selectedSoundEffectId === fx.id ? 'border-rose-500/60 ring-1 ring-rose-500/30' : 'border-zinc-800'"
                            >
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 items-end">
                                    <div class="col-span-2 sm:col-span-1">
                                        <label class="text-[10px] text-zinc-500">Nome</label>
                                        <input type="text" x-model="fx.label" @change="saveSoundEffect(fx)" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1 text-xs">
                                    </div>
                                    <div>
                                        <label class="text-[10px] text-zinc-500">Início (s)</label>
                                        <input type="number" min="0" step="0.1" x-model.number="fx.start_at" @change="saveSoundEffect(fx)" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1 text-xs">
                                    </div>
                                    <div>
                                        <label class="text-[10px] text-zinc-500">Volume</label>
                                        <input type="range" min="0" max="1" step="0.05" x-model.number="fx.volume" @change="saveSoundEffect(fx)" class="block w-full mt-1">
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" @click="removeSoundEffect(fx)" class="text-[10px] text-red-400">Remover</button>
                                    </div>
                                </div>
                                <audio x-show="fx.audio_url" :src="fx.audio_url" controls class="w-full h-8 mt-2" preload="metadata"></audio>
                            </div>
                        </template>
                    </div>
                </div>

                <p class="text-[10px] text-zinc-600">No preview, narração + trilhas + efeitos tocam juntos ao pressionar ▶ Reproduzir.</p>
            </div>

            {{-- Biblioteca --}}
            <div x-show="activeTab === 'biblioteca'" class="space-y-3">
                <div class="flex flex-wrap gap-2 border-b border-zinc-800 pb-3">
                    <button type="button" @click="setMediaLibraryMode('visual')" class="text-xs px-3 py-1.5 rounded-lg" :class="mediaType === 'image' || mediaType === 'video' ? 'bg-violet-700 text-white' : 'bg-zinc-800 text-zinc-400'">Visual</button>
                    <button type="button" @click="setMediaLibraryMode('music')" class="text-xs px-3 py-1.5 rounded-lg" :class="mediaType === 'music' ? 'bg-amber-800 text-amber-100' : 'bg-zinc-800 text-zinc-400'">🎵 Trilhas</button>
                    <button type="button" @click="setMediaLibraryMode('sfx')" class="text-xs px-3 py-1.5 rounded-lg" :class="mediaType === 'sfx' ? 'bg-rose-800 text-rose-100' : 'bg-zinc-800 text-zinc-400'">💥 Efeitos</button>
                </div>

                <p class="text-xs text-zinc-500" x-show="mediaType === 'image' || mediaType === 'video'">Imagens e vídeos — Openverse grátis + Pexels/Pixabay/Unsplash com chave.</p>
                <p class="text-xs text-zinc-500" x-show="mediaType === 'music'">Biblioteca de trilhas — Mixkit grátis + Freesound/Pixabay (com chave). Créditos vão para a descrição automaticamente.</p>
                <p class="text-xs text-zinc-500" x-show="mediaType === 'sfx'">Biblioteca de efeitos sonoros — Mixkit grátis + Freesound (CC, crédito ao autor obrigatório).</p>

                <div class="flex flex-wrap gap-2">
                    <template x-if="mediaType === 'image' || mediaType === 'video'">
                        <select x-model="mediaType" class="rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm">
                            <option value="image">Imagens</option>
                            <option value="video">Vídeos curtos</option>
                        </select>
                    </template>
                    <select x-model="mediaSource" class="rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm">
                        <template x-if="mediaType === 'music' || mediaType === 'sfx'">
                            <option value="all">Todas as fontes gratuitas</option>
                            <option value="mixkit">Mixkit (sem chave)</option>
                            <option value="freesound">Freesound (chave .env)</option>
                        </template>
                        <template x-if="mediaType === 'music'">
                            <option value="pixabay">Pixabay (chave .env)</option>
                        </template>
                        <template x-if="mediaType === 'image' || mediaType === 'video'">
                            <option value="all">Todas (gratuitas + APIs)</option>
                            <option value="openverse">Openverse (sem chave)</option>
                            <option value="pexels">Pexels</option>
                            <option value="pixabay">Pixabay</option>
                            <option value="unsplash">Unsplash</option>
                            <option value="mixkit">Mixkit</option>
                        </template>
                    </select>
                    <div x-show="mediaType === 'music'" class="flex items-center gap-2">
                        <label class="text-[10px] text-zinc-500">Trilha</label>
                        <select x-model.number="selectedMusicSlot" class="rounded bg-zinc-800 border border-zinc-700 px-2 py-2 text-sm">
                            <option :value="0">Trilha 1</option>
                            <option :value="1">Trilha 2</option>
                            <option :value="2">Trilha 3</option>
                        </select>
                    </div>
                    <input
                        type="text"
                        x-model="mediaQuery"
                        @keydown.enter.prevent="searchMedia()"
                        :placeholder="mediaType === 'sfx' ? 'Buscar efeito — impacto, risada, whoosh, notification…' : (mediaType === 'music' ? 'Buscar trilha — ambient, cinematic, upbeat…' : 'Buscar em português — praia, futebol, cidade…')"
                        class="flex-1 min-w-[200px] rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm"
                    >
                    <button x-show="mediaType === 'image'" @click="searchFromSlideBody()" :disabled="!selectedSlide?.body_text?.trim()" class="px-3 py-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 disabled:opacity-40 text-sm">Texto slide</button>
                    <button @click="searchMedia()" :disabled="mediaSearching" class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-sm">
                        <span x-text="mediaSearching ? 'Buscando...' : 'Buscar'"></span>
                    </button>
                </div>

                <div x-show="mediaLibraryProviders && (mediaType === 'music' || mediaType === 'sfx')" class="flex flex-wrap gap-2 text-[10px]">
                    <template x-for="provider in (mediaType === 'music' ? mediaLibraryProviders?.music : mediaLibraryProviders?.sfx) || []" :key="provider.id">
                        <span class="px-2 py-1 rounded border" :class="provider.configured ? 'border-emerald-700/50 text-emerald-400 bg-emerald-950/30' : 'border-zinc-700 text-zinc-500'">
                            <span x-text="provider.label"></span>
                            <span x-show="provider.attribution" title="Requer crédito na descrição"> ©</span>
                            <span x-show="!provider.configured"> (sem chave)</span>
                        </span>
                    </template>
                </div>

                <p x-show="mediaErrors.length && !mediaResults.length" class="text-xs text-yellow-400" x-text="mediaErrors.join(' ')"></p>
                <p x-show="mediaResults.length && (mediaType === 'image')" class="text-xs text-emerald-400">Clique na imagem para inserir no slide selecionado.</p>
                <p x-show="mediaResults.length && mediaType === 'video'" class="text-xs text-emerald-400">Clique no vídeo para inserir como B-roll no slide selecionado.</p>
                <p x-show="mediaResults.length && mediaType === 'music'" class="text-xs text-amber-400">Ouça o preview e clique em Inserir — vai para a trilha selecionada com licença registrada.</p>
                <p x-show="mediaResults.length && mediaType === 'sfx'" class="text-xs text-rose-400">Ouça e clique em Inserir — posicione na timeline (segundos).</p>

                <div x-show="publishAuto && projectCreditsCount" class="rounded border border-emerald-800/40 bg-emerald-950/30 p-2">
                    <p class="text-xs text-emerald-300">
                        ✓ <strong>Licenças ativas</strong> — ao importar, créditos e descrições (YouTube, TikTok, Instagram) são gerados automaticamente nos arquivos de exportação.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-72 overflow-y-auto">
                    <template x-for="item in mediaResults" :key="item.source + '-' + item.id">
                        <div class="rounded-lg border border-zinc-700 bg-zinc-800/80 overflow-hidden group">
                            <template x-if="item.type === 'audio' || item.type === 'sfx'">
                                <div class="p-3 space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-xs font-medium text-zinc-200 line-clamp-2" x-text="item.title || item.author || 'Áudio'"></p>
                                        <span class="text-[9px] uppercase shrink-0 px-1.5 py-0.5 rounded bg-zinc-900 text-zinc-400" x-text="item.source"></span>
                                    </div>
                                    <audio x-show="item.preview_url || item.download_url" :src="item.preview_url || item.download_url" controls class="w-full h-8" preload="none" @click.stop></audio>
                                    <p x-show="item.duration_seconds" class="text-[10px] text-zinc-500" x-text="item.duration_seconds + 's'"></p>
                                    <p x-show="item.attribution_text" class="text-[9px] text-zinc-500 line-clamp-2" x-text="item.attribution_text"></p>
                                    <div class="flex items-center justify-between gap-2 pt-1">
                                        <span x-show="item.requires_attribution || item.attribution_text" class="text-[10px] text-yellow-500" title="Crédito na descrição">© crédito</span>
                                        <span x-show="item.license_type" class="text-[9px] text-zinc-600 truncate" x-text="item.license_type"></span>
                                        <button type="button" @click="importMedia(item)" class="text-xs px-2 py-1 rounded bg-violet-700 hover:bg-violet-600 shrink-0">Inserir</button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="item.type !== 'audio' && item.type !== 'sfx'">
                                <div class="relative cursor-pointer" @click="importMedia(item)">
                                    <template x-if="item.type === 'video'">
                                        <div class="relative h-24">
                                            <img :src="item.preview_url" :alt="item.author || 'Vídeo'" class="w-full h-24 object-cover" loading="lazy">
                                            <span class="absolute inset-0 flex items-center justify-center text-2xl text-white/90">▶</span>
                                            <span x-show="item.duration_seconds" class="absolute bottom-1 right-1 text-[10px] bg-black/80 px-1 rounded" x-text="item.duration_seconds + 's'"></span>
                                        </div>
                                    </template>
                                    <template x-if="item.type !== 'video'">
                                        <img :src="item.preview_url" :alt="item.title || 'Imagem'" class="w-full h-24 object-cover" loading="lazy">
                                    </template>
                                    <span class="absolute bottom-0 left-0 right-0 bg-black/70 text-[10px] px-1 py-0.5 truncate" x-text="item.source"></span>
                                    <p x-show="item.attribution_text" class="absolute top-0 left-0 right-0 bg-black/80 text-[9px] px-1 py-0.5 line-clamp-2 leading-tight opacity-0 group-hover:opacity-100 transition-opacity" x-text="item.attribution_text"></p>
                                    <span x-show="item.requires_attribution || item.attribution_text" class="absolute top-1 right-1 text-[10px] bg-yellow-600/80 px-1 rounded" title="Crédito ao autor">©</span>
                                    <div class="absolute inset-0 bg-violet-900/60 opacity-0 group-hover:opacity-100 flex items-center justify-center text-xs font-medium">Inserir</div>
                                </div>
                            </template>
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
