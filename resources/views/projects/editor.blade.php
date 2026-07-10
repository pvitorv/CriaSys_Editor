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

        <div x-show="slides.length" x-ref="timelineScroll" class="overflow-x-auto overflow-y-auto px-4 py-4 w-full" style="max-height: min(380px, 32vh);">
            {{-- Ferramentas: playhead e cortes --}}
            <div class="flex flex-wrap items-center gap-2 mb-3 pb-2 border-b border-zinc-800/80">
                <span class="text-[10px] text-zinc-500 uppercase tracking-wide">Marcador</span>
                <span class="text-xs tabular-nums text-violet-300 font-medium" x-text="formatTimelineTime(timelinePlayheadSec)"></span>
                <button type="button" @click="timelineTool = 'select'" class="text-[10px] px-2 py-1 rounded" :class="timelineTool === 'select' ? 'bg-violet-700 text-white' : 'bg-zinc-800 text-zinc-400'">Selecionar</button>
                <button type="button" @click="timelineTool = 'cut'" class="text-[10px] px-2 py-1 rounded" :class="timelineTool === 'cut' ? 'bg-rose-800 text-rose-100' : 'bg-zinc-800 text-zinc-400'">Cortar</button>
                <button type="button" @click="markTimelineCutIn()" class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300" title="Marca entrada">In ↓</button>
                <button type="button" @click="markTimelineCutOut()" class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300" title="Marca saída">Out ↑</button>
                <button type="button" @click="applyTimelineTrim()" class="text-[10px] px-2 py-1 rounded bg-rose-900/60 hover:bg-rose-800 text-rose-200" :disabled="!timelineSelectedClip">Aplicar corte</button>
                <button type="button" @click="clearTimelineCutMarks()" class="text-[10px] px-2 py-1 rounded bg-zinc-800 text-zinc-500" x-show="timelineCutMarkIn != null || timelineCutMarkOut != null">Limpar marcas</button>
                <span x-show="timelineCutMarkIn != null" class="text-[10px] text-emerald-400 tabular-nums">In: <span x-text="formatTimelineTime(timelineCutMarkIn)"></span></span>
                <span x-show="timelineCutMarkOut != null" class="text-[10px] text-amber-400 tabular-nums">Out: <span x-text="formatTimelineTime(timelineCutMarkOut)"></span></span>
                <span x-show="timelineSelectedClip" class="text-[10px] text-zinc-500 ml-auto">Selecionado: <span class="text-zinc-300" x-text="timelineSelectedClipLabel || timelineSelectedClip?.kind"></span></span>
            </div>

            <div class="relative" :style="'width: ' + timelineTrackWidthPx + 'px; min-width: ' + timelineViewportWidthPx() + 'px'" x-ref="timelineTrackArea" @click="setPlayheadFromTimelineEvent($event)">
                {{-- Régua superior --}}
                <div class="relative h-5 mb-2 border-b border-zinc-700/80 pointer-events-none">
                    <template x-for="tick in timelineTicks" :key="'tick-top-' + tick.sec">
                        <div class="absolute top-0 h-full border-l border-zinc-700/60" :style="'left: ' + tick.px + 'px'">
                            <span class="absolute -top-0.5 left-1 text-[9px] text-zinc-600 tabular-nums whitespace-nowrap" x-text="tick.label"></span>
                        </div>
                    </template>
                </div>

                {{-- Playhead + marcas de corte --}}
                <div class="absolute inset-0 pointer-events-none z-20" style="top: 1.25rem; bottom: 1.25rem;">
                    <div class="absolute top-0 bottom-0 w-px bg-violet-400 shadow-[0_0_6px_rgba(167,139,250,0.8)]" :style="'left: ' + timelineSecondsToPx(timelinePlayheadSec) + 'px'">
                        <div class="absolute -top-1 -left-1.5 w-3 h-3 bg-violet-400 rotate-45"></div>
                    </div>
                    <div x-show="timelineCutMarkIn != null" class="absolute top-0 bottom-0 w-px bg-emerald-500/80" :style="'left: ' + timelineSecondsToPx(timelineCutMarkIn) + 'px'"></div>
                    <div x-show="timelineCutMarkOut != null" class="absolute top-0 bottom-0 w-px bg-amber-500/80" :style="'left: ' + timelineSecondsToPx(timelineCutMarkOut) + 'px'"></div>
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

                    <div class="flex items-center gap-2 h-6" @click.stop>
                        <span class="w-[4.5rem] shrink-0 text-[9px] text-emerald-500/90 truncate">Narração</span>
                        <div class="relative h-5 rounded border border-zinc-800 bg-zinc-950/60 cursor-pointer" :style="'width: ' + Math.max(timelineTrackWidthPx, 120) + 'px'" @click.stop="selectTimelineNarration()">
                            <div
                                x-show="narration?.audio_url"
                                class="absolute inset-y-0 left-0 rounded border border-emerald-700/40"
                                :class="timelineSelectedClip?.kind === 'narration' ? 'bg-emerald-800/60 ring-1 ring-emerald-400' : 'bg-emerald-900/40'"
                                :style="'width: ' + timelineNarrationWidthPx() + 'px'"
                                title="Narração — clique para selecionar e cortar"
                            ></div>
                        </div>
                    </div>

                    <template x-for="(track, slot) in audioTracks" :key="'tl-audio-' + slot">
                        <div class="flex items-center gap-2 h-6" @click.stop>
                            <span class="w-[4.5rem] shrink-0 text-[9px] text-amber-500/90 truncate" x-text="track.label"></span>
                            <div class="relative h-5 rounded border border-zinc-800 bg-zinc-950/60" :style="'width: ' + Math.max(timelineTrackWidthPx, 120) + 'px'">
                                <div
                                    x-show="track.file_path"
                                    @click.stop="selectTimelineMusic(slot)"
                                    class="absolute inset-y-0 rounded border cursor-pointer hover:brightness-110"
                                    :class="timelineSelectedClip?.kind === 'music' && timelineSelectedClip?.slot === slot ? 'bg-amber-800/55 border-amber-400 ring-1 ring-amber-400' : 'bg-amber-900/35 border-amber-700/40'"
                                    :style="'left: ' + timelineSecondsToPx(track.start_at) + 'px; width: ' + timelineMusicClipWidth(track) + 'px'"
                                    :title="track.label + ' · vol ' + Math.round((track.volume ?? 0.35) * 100) + '% · clique para cortar'"
                                ></div>
                            </div>
                        </div>
                    </template>

                    <div class="flex items-start gap-2 min-h-[26px]" @click.stop>
                        <span class="w-[4.5rem] shrink-0 text-[9px] text-rose-400/90 pt-1">Efeitos</span>
                        <div class="relative h-6 rounded border border-zinc-800 bg-zinc-950/60" :style="'width: ' + Math.max(timelineTrackWidthPx, 120) + 'px'">
                            <template x-for="fx in soundEffects" :key="'tl-fx-' + fx.id">
                                <button
                                    type="button"
                                    class="absolute top-0 h-5 rounded px-1 truncate text-left border hover:brightness-110"
                                    :class="timelineSelectedClip?.kind === 'sfx' && timelineSelectedClip?.id === fx.id ? 'bg-rose-800/70 border-rose-400 ring-1 ring-rose-400 text-rose-50' : 'bg-rose-900/50 border-rose-600/50 text-rose-100'"
                                    :style="'left: ' + timelineSecondsToPx(fx.start_at) + 'px; width: ' + timelineFxDisplayWidth(fx) + 'px'"
                                    :title="(fx.label || 'Efeito') + ' · ' + formatTimelineTime(fx.start_at)"
                                    @click.stop="selectSoundEffect(fx)"
                                    x-text="fx.label || 'FX'"
                                ></button>
                            </template>
                            <span x-show="!soundEffects.length" class="absolute inset-0 flex items-center justify-center text-[9px] text-zinc-600 pointer-events-none">
                                clique em Trilhas & FX para adicionar
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Régua inferior (espelho da superior) --}}
                <div class="relative h-5 mt-3 pt-2 border-t border-zinc-700/80 pointer-events-none">
                    <template x-for="tick in timelineTicks" :key="'tick-bottom-' + tick.sec">
                        <div class="absolute top-2 h-full border-l border-zinc-700/60" :style="'left: ' + tick.px + 'px'">
                            <span class="absolute top-3 left-1 text-[9px] text-zinc-600 tabular-nums whitespace-nowrap" x-text="tick.label"></span>
                        </div>
                    </template>
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
                <p x-show="mediaResults.length && (mediaType === 'image')" class="text-xs text-emerald-400">Clique na imagem para inserir no slide — ou use <strong>Image Studio</strong> para editar a arte.</p>
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
                                <div class="relative group/visual">
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
                                        <p x-show="item.attribution_text" class="absolute top-0 left-0 right-0 bg-black/80 text-[9px] px-1 py-0.5 line-clamp-2 leading-tight opacity-0 group-hover/visual:opacity-100 transition-opacity" x-text="item.attribution_text"></p>
                                        <span x-show="item.requires_attribution || item.attribution_text" class="absolute top-1 right-1 text-[10px] bg-yellow-600/80 px-1 rounded" title="Crédito ao autor">©</span>
                                        <div class="absolute inset-0 bg-violet-900/60 opacity-0 group-hover/visual:opacity-100 flex items-center justify-center text-xs font-medium">Inserir no slide</div>
                                    </div>
                                    <button
                                        x-show="item.type !== 'video'"
                                        type="button"
                                        @click.stop="imageStudioImportFromLibraryItem(item)"
                                        class="absolute top-1 left-1 z-10 text-[10px] px-1.5 py-0.5 rounded bg-violet-700/90 hover:bg-violet-600 text-white opacity-0 group-hover/visual:opacity-100 transition-opacity"
                                        title="Abrir no Image Studio"
                                    >🎨 Studio</button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Image Studio --}}
            <div x-show="activeTab === 'image_studio'" class="space-y-4" x-cloak>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-violet-200">Image Studio</h3>
                        <p class="text-[11px] text-zinc-500 mt-0.5">Formatos à esquerda · ferramentas no topo do canvas · layouts prontos abaixo dos formatos.</p>
                        <p class="text-[10px] mt-1 text-emerald-400" x-show="imageStudioReady" x-text="'Carregado: ' + (imageStudioPresets.length||0) + ' formatos · ' + (imageStudioTemplates.length||0) + ' layouts · ' + (imageStudioElements.length||0) + ' elementos'"></p>
                    </div>
                    <div class="flex flex-wrap gap-2 items-center">
                        <span x-show="imageStudioSaving" class="text-[10px] text-zinc-500">Salvando…</span>
                        <button type="button" @click="saveImageStudioDesign()" class="text-xs px-3 py-1.5 rounded-lg bg-zinc-700 hover:bg-zinc-600">Salvar</button>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-[240px_1fr_260px] gap-4">
                    {{-- Formatos + layouts + elementos --}}
                    <div class="space-y-3 rounded-xl border border-zinc-800 bg-zinc-950/50 p-3 max-h-[75vh] overflow-y-auto">
                        <input type="search" x-model="imageStudioPresetFilter" placeholder="Buscar formato…" class="w-full text-xs px-2 py-1.5 rounded bg-zinc-900 border border-zinc-700">
                        <template x-for="(presets, groupName) in imageStudioPresetGroups" :key="'isg-' + groupName">
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-zinc-500 mb-1" x-text="groupName"></p>
                                <div class="space-y-1">
                                    <template x-for="preset in presets" :key="preset.slug">
                                        <button type="button" @click="switchImageStudioPreset(preset.slug)" class="w-full text-left text-xs px-2 py-1.5 rounded border transition" :class="imageStudioPreset === preset.slug ? 'border-violet-500 bg-violet-950/40 text-white' : 'border-zinc-800 text-zinc-400 hover:border-zinc-600'">
                                            <span x-text="preset.icon"></span>
                                            <span x-text="preset.name" class="ml-1"></span>
                                            <span class="block text-[9px] text-zinc-600 tabular-nums" x-text="preset.width + '×' + preset.height"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <div class="pt-3 border-t border-zinc-800">
                            <p class="text-[10px] uppercase tracking-wide text-zinc-500 mb-2">Layouts prontos (<span x-text="imageStudioTemplates.length"></span>)</p>
                            <div class="space-y-1">
                                <template x-for="tpl in imageStudioTemplates" :key="'ist-' + tpl.slug">
                                    <button type="button" @click="imageStudioApplyTemplate(tpl)" class="w-full text-left text-xs px-2 py-1.5 rounded border border-zinc-800 text-zinc-400 hover:border-violet-600 hover:text-violet-200 transition" :title="tpl.description">
                                        <span x-text="tpl.name"></span>
                                        <span class="block text-[9px] text-zinc-600 truncate" x-text="tpl.description"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <div class="pt-3 border-t border-zinc-800">
                            <input type="search" x-model="imageStudioElementFilter" placeholder="Buscar ícone ou forma…" class="w-full text-xs px-2 py-1.5 rounded bg-zinc-900 border border-zinc-700 mb-2">
                            <p class="text-[10px] uppercase tracking-wide text-zinc-500 mb-2">Elementos (<span x-text="imageStudioElements.length"></span>)</p>
                            <template x-for="(items, groupName) in imageStudioElementsByGroup" :key="'elg-' + groupName">
                                <p class="text-[9px] text-zinc-600 mb-1 mt-2" x-text="groupName"></p>
                                <div class="grid grid-cols-4 gap-1 mb-1">
                                    <template x-for="el in items" :key="el.slug">
                                        <button type="button" @click="imageStudioAddElement(el)" class="aspect-square rounded border border-zinc-800 hover:border-violet-500 flex items-center justify-center p-1" :title="el.name">
                                            <img x-show="el.type === 'svg_icon' && el.icon_url" :src="el.icon_url" alt="" class="w-5 h-5 invert opacity-90 pointer-events-none">
                                            <span x-show="el.type !== 'svg_icon'" class="text-base leading-none" x-text="el.icon || '•'"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Canvas --}}
                    <div class="space-y-3 min-w-0">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="imageStudioUndo()" :disabled="!imageStudioCanUndo" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 disabled:opacity-40" title="Ctrl+Z">↶ Desfazer</button>
                            <button type="button" @click="imageStudioRedo()" :disabled="!imageStudioCanRedo" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 disabled:opacity-40" title="Ctrl+Y">↷ Refazer</button>
                            <button type="button" @click="imageStudioAddText()" class="text-xs px-2 py-1 rounded bg-violet-800 hover:bg-violet-700">+ Texto</button>
                            <button type="button" @click="imageStudioAddShape('rect')" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700">+ Retângulo</button>
                            <button type="button" @click="imageStudioAddShape('circle')" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700">+ Círculo</button>
                            <label class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 cursor-pointer">
                                + Imagem
                                <input type="file" accept="image/*" @change="imageStudioUploadImage($event)" class="hidden">
                            </label>
                            <label class="text-xs px-2 py-1 rounded bg-emerald-900/60 hover:bg-emerald-800 cursor-pointer" :class="imageStudioBgRemoving ? 'opacity-50 pointer-events-none' : ''">
                                ✂ Remover fundo (arquivo)
                                <input type="file" accept="image/*" @change="imageStudioRemoveBackground($event)" class="hidden" :disabled="imageStudioBgRemoving">
                            </label>
                            <button type="button" @click="imageStudioRemoveBgFromSelection()" :disabled="imageStudioBgRemoving || imageStudioSelectedObject?.type !== 'image'" class="text-xs px-2 py-1 rounded bg-emerald-800 hover:bg-emerald-700 disabled:opacity-40" title="Remove fundo da imagem já selecionada no canvas">
                                <span x-show="!imageStudioBgRemoving">✂ Remover fundo da seleção</span>
                                <span x-show="imageStudioBgRemoving">Processando…</span>
                            </button>
                            <label class="text-xs px-2 py-1 rounded bg-zinc-800 flex items-center gap-1 cursor-pointer">
                                <input type="checkbox" x-model="imageStudioShowFormatGuides" @change="onImageStudioFormatGuidesChange()" class="rounded"> Sangrias / contorno
                            </label>
                            <label class="text-xs px-2 py-1 rounded bg-zinc-800 flex items-center gap-1 cursor-pointer">
                                <input type="checkbox" x-model="imageStudioShowGrid" @change="onImageStudioGridChange()" class="rounded"> Grid
                            </label>
                            <label class="text-xs px-2 py-1 rounded bg-zinc-800 flex items-center gap-1 cursor-pointer">
                                <input type="checkbox" x-model="imageStudioSnapGrid" @change="onImageStudioGridChange()" class="rounded"> Snap
                            </label>
                            <select x-model.number="imageStudioGridSize" @change="onImageStudioGridChange()" class="text-xs px-2 py-1 rounded bg-zinc-800 border border-zinc-700">
                                <option :value="10">10px</option>
                                <option :value="20">20px</option>
                                <option :value="40">40px</option>
                            </select>
                            <div class="flex items-center gap-1.5 ml-auto border border-zinc-700 rounded-lg px-2 py-0.5 bg-zinc-900/80">
                                <button type="button" @click="imageStudioZoomOut()" class="text-xs px-1.5 py-0.5 rounded hover:bg-zinc-700" title="Diminuir zoom">−</button>
                                <input type="range" min="8" max="400" step="1" x-model.number="imageStudioZoom" @input="imageStudioSetZoomPercent(imageStudioZoom)" class="w-20 accent-violet-500">
                                <button type="button" @click="imageStudioZoomIn()" class="text-xs px-1.5 py-0.5 rounded hover:bg-zinc-700" title="Aumentar zoom">+</button>
                                <span class="text-[10px] text-zinc-400 w-9 text-center" x-text="imageStudioZoom + '%'"></span>
                                <button type="button" @click="imageStudioZoomReset()" class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800 hover:bg-zinc-700">100%</button>
                                <button type="button" @click="fitImageStudioCanvas()" class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800 hover:bg-zinc-700">Ajustar</button>
                            </div>
                        </div>
                        <p class="text-[10px] text-violet-400" x-show="imageStudioCurrentPreset" x-text="'Formato ativo: ' + (imageStudioCurrentPreset?.name || '') + ' — ' + (imageStudioCurrentPreset?.width || 0) + '×' + (imageStudioCurrentPreset?.height || 0) + 'px (contorno violeta = corte · amarelo = sangria)'"></p>

                        <div class="rounded-xl border border-zinc-700 bg-zinc-950 p-4 overflow-auto max-h-[70vh] flex justify-center items-start" x-ref="imageStudioCanvasWrap">
                            <div x-ref="imageStudioCanvasScaler" class="shadow-2xl shadow-black/40 ring-2 ring-violet-500/40 inline-block bg-zinc-900">
                                <canvas x-ref="imageStudioCanvas" class="block"></canvas>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-xs text-zinc-400">
                                Cor de fundo do canvas
                                <input type="color" x-model="imageStudioBgColor" @input="onImageStudioBgChange()" class="w-full mt-1 h-9 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                            </label>
                            <label class="text-xs text-zinc-400">
                                Transparência fundo
                                <input type="range" min="0" max="100" x-model.number="imageStudioBgOpacity" @input="onImageStudioBgChange()" class="w-full mt-2">
                                <span class="text-[10px] text-zinc-500" x-text="imageStudioBgOpacity + '%'"></span>
                            </label>
                        </div>
                    </div>

                    {{-- Camadas + export --}}
                    <div class="space-y-3 max-h-[75vh] overflow-y-auto">
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/50 p-3 space-y-2">
                            <p class="text-xs font-medium text-zinc-300">Camadas</p>
                            <template x-if="!imageStudioLayers.length">
                                <p class="text-[10px] text-zinc-600">Adicione texto, formas ou imagens.</p>
                            </template>
                            <template x-for="layer in imageStudioLayers" :key="layer.id">
                                <div class="flex items-center gap-1 rounded bg-zinc-900/80 border border-zinc-800 px-1.5 py-1">
                                    <button type="button" @click="imageStudioSelectLayer(layer)" class="flex-1 text-left text-[10px] text-zinc-300 truncate" x-text="layer.name"></button>
                                    <button type="button" @click="imageStudioLayerAction(layer, 'visibility')" class="text-[10px] px-1" x-text="layer.visible ? '👁' : '🚫'"></button>
                                    <button type="button" @click="imageStudioLayerAction(layer, 'lock')" class="text-[10px] px-1" x-text="layer.locked ? '🔒' : '🔓'"></button>
                                    <button type="button" @click="imageStudioLayerAction(layer, 'up')" class="text-[10px] px-1">↑</button>
                                    <button type="button" @click="imageStudioLayerAction(layer, 'delete')" class="text-[10px] px-1 text-red-400">×</button>
                                </div>
                            </template>
                        </div>

                        <div x-show="imageStudioSelectedObject" class="rounded-xl border border-zinc-800 bg-zinc-950/50 p-3 space-y-2">
                            <p class="text-xs font-medium text-zinc-300">Objeto selecionado</p>
                            <label class="text-xs text-zinc-400 block">
                                Opacidade
                                <input type="range" min="0" max="100" :value="Math.round((imageStudioSelectedObject?.opacity ?? 1) * 100)" @input="imageStudioObjectOpacity($event.target.value)" class="w-full mt-1">
                            </label>
                            <div class="flex flex-wrap gap-1 pt-1">
                                <button type="button" @click="imageStudioAlignObject('left')" class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800">⬅</button>
                                <button type="button" @click="imageStudioAlignObject('center-h')" class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800">↔</button>
                                <button type="button" @click="imageStudioAlignObject('right')" class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800">➡</button>
                                <button type="button" @click="imageStudioAlignObject('top')" class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800">⬆</button>
                                <button type="button" @click="imageStudioAlignObject('center-v')" class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800">↕</button>
                                <button type="button" @click="imageStudioAlignObject('bottom')" class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800">⬇</button>
                            </div>
                            <template x-if="imageStudioSelectedObject?.type === 'image'">
                                <div class="space-y-2 pt-2 border-t border-zinc-800">
                                    <button type="button" @click="imageStudioRemoveBgFromSelection()" :disabled="imageStudioBgRemoving" class="w-full text-[10px] py-1.5 rounded bg-emerald-900/60 hover:bg-emerald-800 disabled:opacity-40">
                                        <span x-show="!imageStudioBgRemoving">✂ Remover fundo desta imagem</span>
                                        <span x-show="imageStudioBgRemoving">Removendo fundo…</span>
                                    </button>
                                    <p class="text-[10px] text-violet-400 font-medium">Filtros</p>
                                    <label class="text-[10px] text-zinc-400 block">Brilho<input type="range" min="0" max="100" x-model.number="imageStudioFilters.brightness" @input="imageStudioApplyFilters()" class="w-full mt-1"></label>
                                    <label class="text-[10px] text-zinc-400 block">Contraste<input type="range" min="0" max="100" x-model.number="imageStudioFilters.contrast" @input="imageStudioApplyFilters()" class="w-full mt-1"></label>
                                    <label class="text-[10px] text-zinc-400 block">Saturação<input type="range" min="0" max="100" x-model.number="imageStudioFilters.saturation" @input="imageStudioApplyFilters()" class="w-full mt-1"></label>
                                    <label class="text-[10px] text-zinc-400 block">Desfoque<input type="range" min="0" max="100" x-model.number="imageStudioFilters.blur" @input="imageStudioApplyFilters()" class="w-full mt-1"></label>
                                    <label class="text-[10px] text-zinc-400 block">P&B<input type="range" min="0" max="100" x-model.number="imageStudioFilters.grayscale" @input="imageStudioApplyFilters()" class="w-full mt-1"></label>
                                    <button type="button" @click="imageStudioClearFilters()" class="text-[10px] px-2 py-1 rounded bg-zinc-800 text-zinc-400 hover:text-white">Limpar filtros</button>
                                </div>
                            </template>
                        </div>

                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/50 p-3 space-y-2">
                            <p class="text-xs font-medium text-zinc-300">Molduras (Thumbnail)</p>
                            <select x-model="imageStudioFrameSlug" class="w-full text-xs px-2 py-1.5 rounded bg-zinc-900 border border-zinc-700">
                                <option value="none">Sem moldura</option>
                                <template x-for="fr in imageStudioFrames" :key="'isfm-' + fr.slug">
                                    <option :value="fr.slug" x-text="fr.name"></option>
                                </template>
                            </select>
                            <input type="color" x-model="imageStudioFrameColor" class="w-full h-8 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                            <button type="button" @click="imageStudioApplyFrame()" :disabled="imageStudioFrameSlug === 'none'" class="w-full text-xs py-1.5 rounded bg-zinc-800 hover:bg-violet-800 disabled:opacity-40">Aplicar moldura</button>
                        </div>

                        <div class="rounded-xl border border-violet-900/40 bg-violet-950/20 p-3 space-y-2">
                            <p class="text-xs font-medium text-violet-200">Exportar</p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="fmt in imageStudioExportFormats" :key="'isf-' + fmt.id">
                                    <button type="button" @click="imageStudioExport(fmt.id)" class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-violet-800" x-text="fmt.label"></button>
                                </template>
                            </div>
                        </div>

                        <div class="rounded-xl border border-emerald-900/40 bg-emerald-950/20 p-3 space-y-2">
                            <p class="text-xs font-medium text-emerald-200">Integração CriaSys</p>
                            <button type="button" @click="imageStudioPushThumbnail()" class="w-full text-xs py-2 rounded-lg bg-violet-800 hover:bg-violet-700">Enviar para Thumbnail</button>
                            <button type="button" @click="imageStudioPushLibrary()" class="w-full text-xs py-2 rounded-lg bg-emerald-800 hover:bg-emerald-700">Salvar na biblioteca do projeto</button>
                        </div>

                        <div x-show="typeof window !== 'undefined' && window.criasys?.isDesktop" class="rounded-xl border border-sky-900/40 bg-sky-950/20 p-3 space-y-2">
                            <p class="text-xs font-medium text-sky-200">Pasta local (Electron)</p>
                            <button type="button" @click="imageStudioPickLocalFolder()" class="w-full text-xs py-2 rounded-lg bg-sky-800 hover:bg-sky-700">Monitorar pasta de imagens</button>
                            <p x-show="imageStudioLocalWatch" class="text-[9px] text-zinc-500 break-all" x-text="imageStudioLocalWatch"></p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Thumbnail --}}
            <div x-show="activeTab === 'thumbnail'" class="space-y-4">
                {{-- Plataformas de entrega --}}
                <div>
                    <h3 class="text-sm font-medium text-zinc-300 mb-2">Plataforma de entrega</h3>
                    <p class="text-[11px] text-zinc-500 mb-2">Cada plataforma tem formato e arquivo próprios — a capa acompanha o destino do vídeo.</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="plat in thumbnailPlatforms" :key="plat.slug">
                            <button
                                type="button"
                                @click="switchThumbnailPlatform(plat.slug)"
                                class="text-xs px-3 py-2 rounded-lg border transition flex items-center gap-1.5"
                                :class="selectedThumbnailPlatform === plat.slug ? 'border-violet-500 bg-violet-950/50 text-white' : 'border-zinc-700 bg-zinc-900/60 text-zinc-400 hover:border-zinc-600'"
                            >
                                <span x-text="plat.icon"></span>
                                <span x-text="plat.name"></span>
                                <span class="text-[10px] opacity-60" x-text="plat.aspect"></span>
                            </button>
                        </template>
                    </div>
                    <p class="text-[10px] text-zinc-600 mt-2" x-text="thumbnailPlatformHint()"></p>
                </div>

                <div class="flex flex-wrap items-start gap-6">
                    <div class="flex-1 min-w-[280px] space-y-4 min-w-0">
                        {{-- Fonte da imagem --}}
                        <div class="rounded-lg border border-zinc-800 bg-zinc-950/40 p-3 space-y-3">
                            <h3 class="text-sm font-medium text-zinc-300">Imagem de fundo</h3>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="thumbnailSettings.image_source = 'slide'; onThumbnailImageSourceChange()" class="text-xs px-3 py-1.5 rounded-lg" :class="thumbnailSettings.image_source === 'slide' ? 'bg-violet-700 text-white' : 'bg-zinc-800 text-zinc-400'">Do slide</button>
                                <button type="button" @click="thumbnailSettings.image_source = 'upload'" class="text-xs px-3 py-1.5 rounded-lg" :class="thumbnailSettings.image_source === 'upload' ? 'bg-violet-700 text-white' : 'bg-zinc-800 text-zinc-400'">Arquivo do PC</button>
                                <button type="button" @click="thumbnailSettings.image_source = 'solid'; onThumbnailImageSourceChange()" class="text-xs px-3 py-1.5 rounded-lg" :class="thumbnailSettings.image_source === 'solid' ? 'bg-violet-700 text-white' : 'bg-zinc-800 text-zinc-400'">Só cor sólida</button>
                            </div>
                            <div x-show="thumbnailSettings.image_source === 'slide'" class="grid grid-cols-1 gap-2">
                                <label class="text-xs text-zinc-400">
                                    Slide de origem
                                    <select x-model.number="thumbnailSettings.slide_index" @change="onThumbnailSlideChange()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                                        <template x-for="(slide, idx) in slides" :key="'th-slide-' + slide.id">
                                            <option :value="idx" x-text="'Slide ' + (idx + 1) + (slide.video_path ? ' (vídeo)' : slide.image_url ? ' (imagem)' : '')"></option>
                                        </template>
                                    </select>
                                    <p class="text-[10px] text-zinc-600 mt-1">A capa usa o vídeo ou imagem deste slide — igual ao preview do editor.</p>
                                </label>
                            </div>
                            <div x-show="thumbnailSettings.image_source === 'upload'" class="space-y-2">
                                <label class="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-700 hover:border-violet-600 p-6 cursor-pointer transition">
                                    <span class="text-2xl">📁</span>
                                    <span class="text-xs text-zinc-400 text-center">Clique para importar JPG, PNG ou WebP do seu computador</span>
                                    <span class="text-[10px] text-zinc-600">Ideal quando a capa não vem do vídeo/slides</span>
                                    <input type="file" accept="image/jpeg,image/png,image/webp" @change="uploadThumbnailImage($event)" class="hidden">
                                </label>
                                <p x-show="thumbnailSettings.custom_image_path" class="text-[10px] text-emerald-400">✓ Imagem externa carregada para esta plataforma</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-zinc-300 mb-2">Modelos profissionais</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                                <template x-for="tpl in filteredThumbnailTemplates" :key="tpl.slug">
                                    <button
                                        type="button"
                                        @click="selectThumbnailTemplate(tpl.slug)"
                                        class="rounded-lg border p-3 text-left transition relative overflow-hidden"
                                        :class="thumbnailSettings.template === tpl.slug ? 'border-violet-500 bg-violet-950/40 ring-1 ring-violet-500/50' : 'border-zinc-700 bg-zinc-900/60 hover:border-zinc-600'"
                                    >
                                        <span class="text-[9px] uppercase tracking-wide text-violet-400/80" x-text="tpl.category"></span>
                                        <span class="text-xs font-semibold text-zinc-100 block mt-0.5" x-text="tpl.name"></span>
                                        <p class="text-[10px] text-zinc-500 mt-1 line-clamp-2 leading-snug" x-text="tpl.description"></p>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Molduras — separado dos modelos --}}
                        <div class="rounded-xl border border-amber-900/40 bg-amber-950/10 p-4 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-amber-100">Molduras</h3>
                                    <p class="text-[10px] text-zinc-500 mt-0.5">
                                        <span x-text="filteredThumbnailFrames.length"></span> opções — combine com qualquer modelo acima
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        x-show="thumbnailSettings.frame_slug && thumbnailSettings.frame_slug !== 'none'"
                                        @click="clearThumbnailFrame()"
                                        class="text-[10px] px-2 py-1 rounded bg-zinc-800 text-zinc-400 hover:text-white"
                                    >
                                        Remover moldura
                                    </button>
                                    <button
                                        type="button"
                                        @click="toggleFrameManageMode()"
                                        class="text-[10px] px-2 py-1 rounded"
                                        :class="frameManageMode ? 'bg-amber-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:text-white'"
                                    >
                                        <span x-text="frameManageMode ? 'Concluir gestão' : 'Gerenciar molduras'"></span>
                                    </button>
                                </div>
                            </div>

                            {{-- Minhas molduras — upload e conjuntos --}}
                            <div class="rounded-lg border border-emerald-900/40 bg-emerald-950/20 p-3 space-y-3">
                                <div>
                                    <h4 class="text-xs font-semibold text-emerald-200">Minhas molduras</h4>
                                    <p class="text-[10px] text-zinc-500 mt-0.5">Importe PNG/WebP com transparência (bordas, balões, efeitos que você criou)</p>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="text-xs text-zinc-400">
                                        Nome da moldura
                                        <input type="text" x-model="newCustomFrameName" placeholder="Ex: Borda roxa Ei Nerd" class="w-full mt-1 rounded bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm">
                                    </label>
                                    <label class="text-xs text-zinc-400">
                                        Conjunto / pasta
                                        <select x-model="newCustomFrameCategory" class="w-full mt-1 rounded bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm">
                                            <option value="personalizado">Minhas molduras</option>
                                            <template x-for="(meta, slug) in frameCustomCategories" :key="'ccat-' + slug">
                                                <option :value="slug" x-text="meta.label || slug"></option>
                                            </template>
                                        </select>
                                    </label>
                                </div>
                                <label class="block text-xs text-zinc-400">
                                    Arquivo (PNG recomendado, 1280×720 ou maior)
                                    <input type="file" accept="image/png,image/webp,image/jpeg" @change="uploadCustomFrame($event)" class="w-full mt-1 text-sm text-zinc-400 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-emerald-800 file:text-emerald-100">
                                </label>
                                <div class="flex flex-wrap gap-2 items-end pt-1 border-t border-zinc-800/80">
                                    <label class="text-xs text-zinc-400 flex-1 min-w-[140px]">
                                        Novo conjunto
                                        <input type="text" x-model="newFrameCollectionName" placeholder="Ex: Pack Ei Nerd 2026" class="w-full mt-1 rounded bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm">
                                    </label>
                                    <button type="button" @click="createFrameCollection()" class="text-xs px-3 py-2 rounded-lg bg-emerald-800 hover:bg-emerald-700 text-white shrink-0">Criar conjunto</button>
                                </div>

                                {{-- Pastas / conjuntos do usuário — remover --}}
                                <div x-show="Object.keys(frameCustomCategories).length" class="pt-2 border-t border-zinc-800/80 space-y-2">
                                    <p class="text-[10px] text-zinc-500 font-medium">Suas pastas — clique para filtrar ou remover</p>
                                    <template x-for="(meta, slug) in frameCustomCategories" :key="'manage-cat-' + slug">
                                        <div x-show="isCustomFrameCategory(slug)" class="flex items-center justify-between gap-2 rounded-lg bg-zinc-900/80 border border-zinc-800 px-2 py-1.5">
                                            <button
                                                type="button"
                                                @click="selectedFrameCategory = slug; thumbnailFrameSearch = ''"
                                                class="text-xs text-zinc-200 hover:text-emerald-300 truncate text-left flex-1"
                                                x-text="meta.label || slug"
                                            ></button>
                                            <button
                                                type="button"
                                                @click="deleteFrameCategory(slug)"
                                                class="text-[10px] px-2 py-1 rounded bg-red-950/70 text-red-300 hover:bg-red-900/70 shrink-0"
                                                title="Excluir pasta e molduras"
                                            >
                                                Remover pasta
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div x-show="frameManageMode && canDeleteFrameCategory(selectedFrameCategory)" class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="deleteFrameCategory(selectedFrameCategory)"
                                    class="text-[10px] px-2 py-1 rounded bg-red-950/60 text-red-300 hover:bg-red-900/60"
                                >
                                    <span x-text="isCustomFrameCategory(selectedFrameCategory) ? 'Excluir pasta' : 'Ocultar conjunto'"></span>
                                    «<span x-text="frameCategoryLabel(selectedFrameCategory)"></span>»
                                </button>
                            </div>

                            <div x-show="frameManageMode && (frameLibraryHiddenFrames.length || frameLibraryHiddenCategories.length)" class="rounded-lg border border-zinc-700 bg-zinc-900/60 p-3 space-y-2">
                                <p class="text-[10px] text-zinc-400 font-medium">Ocultas — clique para restaurar</p>
                                <div class="flex flex-wrap gap-1.5" x-show="frameLibraryHiddenFrames.length">
                                    <template x-for="hf in frameLibraryHiddenFrames" :key="'hf-' + hf.slug">
                                        <button type="button" @click="restoreHiddenFrame(hf.slug)" class="text-[10px] px-2 py-1 rounded-full bg-zinc-800 text-zinc-300 hover:bg-emerald-900/50" x-text="'↩ ' + hf.name"></button>
                                    </template>
                                </div>
                                <div class="flex flex-wrap gap-1.5" x-show="frameLibraryHiddenCategories.length">
                                    <template x-for="hc in frameLibraryHiddenCategories" :key="'hc-' + hc.slug">
                                        <button type="button" @click="restoreHiddenCategory(hc.slug)" class="text-[10px] px-2 py-1 rounded-full bg-zinc-800 text-amber-200 hover:bg-amber-900/40" x-text="'↩ ' + hc.label"></button>
                                    </template>
                                </div>
                            </div>

                            <input
                                type="search"
                                x-model="thumbnailFrameSearch"
                                placeholder="Buscar moldura ou canal (ex: Ei Nerd, Código Fonte)..."
                                class="w-full text-xs px-3 py-2 rounded-lg bg-zinc-900 border border-zinc-700 text-zinc-200 placeholder-zinc-500 focus:border-amber-600 focus:outline-none"
                            >

                            <div class="flex flex-wrap gap-1.5">
                                <span class="text-[10px] text-zinc-500 self-center mr-1">Canais:</span>
                                <button type="button" @click="selectedFrameCategory = 'youtube_br'; thumbnailFrameSearch = 'Ei Nerd'" class="text-[10px] px-2 py-1 rounded-full bg-zinc-800 text-zinc-300 hover:bg-violet-900/50 hover:text-violet-200">Ei Nerd</button>
                                <button type="button" @click="selectedFrameCategory = 'youtube_br'; thumbnailFrameSearch = 'Nerd de Negócios'" class="text-[10px] px-2 py-1 rounded-full bg-zinc-800 text-zinc-300 hover:bg-blue-900/50 hover:text-blue-200">Nerd de Negócios</button>
                                <button type="button" @click="selectedFrameCategory = 'youtube_br'; thumbnailFrameSearch = 'Código Fonte'" class="text-[10px] px-2 py-1 rounded-full bg-zinc-800 text-zinc-300 hover:bg-orange-900/50 hover:text-orange-200">Código Fonte TV</button>
                                <button type="button" @click="selectedFrameCategory = 'youtube_br'; thumbnailFrameSearch = 'Mano Devyn'" class="text-[10px] px-2 py-1 rounded-full bg-zinc-800 text-zinc-300 hover:bg-red-950/50 hover:text-red-300">Mano Devyn</button>
                                <button type="button" @click="selectedFrameCategory = 'youtube_br'; thumbnailFrameSearch = 'Marcilio'" class="text-[10px] px-2 py-1 rounded-full bg-zinc-800 text-zinc-300 hover:bg-sky-900/50 hover:text-sky-200">Prof. Marcilio</button>
                                <button type="button" @click="selectedFrameCategory = 'youtube_br'; thumbnailFrameSearch = ''" class="text-[10px] px-2 py-1 rounded-full bg-amber-900/40 text-amber-200">Todos BR</button>
                            </div>

                            <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto overscroll-contain">
                                <button
                                    type="button"
                                    @click="selectedFrameCategory = 'all'"
                                    class="text-[10px] px-2 py-1 rounded-full"
                                    :class="selectedFrameCategory === 'all' ? 'bg-amber-700 text-white' : 'bg-zinc-800 text-zinc-400'"
                                >Todas</button>
                                <template x-for="(label, key) in thumbnailFrameCategories" :key="'fcat-' + key">
                                    <button
                                        type="button"
                                        @click="selectedFrameCategory = key"
                                        class="text-[10px] px-2 py-1 rounded-full"
                                        :class="selectedFrameCategory === key ? 'bg-amber-700 text-white' : 'bg-zinc-800 text-zinc-400'"
                                        x-text="label"
                                    ></button>
                                </template>
                            </div>

                            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-2 max-h-96 overflow-y-auto overscroll-contain pr-1">
                                <template x-for="frame in filteredThumbnailFrames" :key="frame.slug">
                                    <div class="relative group">
                                        <button
                                            type="button"
                                            @click="selectThumbnailFrame(frame.slug)"
                                            class="w-full group flex flex-col items-center gap-1.5 p-2 rounded-lg border transition text-center"
                                            :class="thumbnailSettings.frame_slug === frame.slug ? 'border-amber-500 bg-amber-950/50 ring-1 ring-amber-500/60' : 'border-zinc-700/80 bg-zinc-900/80 hover:border-zinc-600'"
                                            :title="frame.name"
                                        >
                                            <div
                                                class="w-full aspect-[4/3] rounded bg-zinc-950/80 bg-cover bg-center"
                                                :style="framePreviewStyle(frame)"
                                            ></div>
                                            <span class="text-[9px] text-zinc-300 leading-tight line-clamp-2 w-full" x-text="frame.name"></span>
                                            <span x-show="frame.is_custom" class="text-[8px] text-emerald-400">sua moldura</span>
                                        </button>
                                        <button
                                            type="button"
                                            x-show="frameManageMode && frame.can_delete && frame.slug !== 'none'"
                                            @click="deleteThumbnailFrame(frame.slug, $event)"
                                            class="absolute top-1 right-1 w-5 h-5 rounded-full bg-red-600/90 text-white text-xs leading-none hover:bg-red-500 z-10"
                                            title="Remover moldura"
                                        >×</button>
                                    </div>
                                </template>
                            </div>

                            <div x-show="thumbnailSettings.frame_slug && thumbnailSettings.frame_slug !== 'none'" class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 border-t border-zinc-800/80">
                                <label class="text-xs text-zinc-400">
                                    Cor moldura
                                    <input type="color" x-model="thumbnailSettings.frame_color" @input="scheduleThumbnailPreview()" class="w-full mt-1 h-9 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                                </label>
                                <label class="text-xs text-zinc-400">
                                    Cor secundária
                                    <input type="color" x-model="thumbnailSettings.frame_secondary_color" @input="scheduleThumbnailPreview()" class="w-full mt-1 h-9 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                                </label>
                                <label class="text-xs text-zinc-400">
                                    Espessura
                                    <input type="range" min="4" max="100" x-model.number="thumbnailSettings.frame_width" @input="scheduleThumbnailPreview()" class="w-full mt-2">
                                    <span class="text-[10px] text-zinc-500 tabular-nums" x-text="thumbnailSettings.frame_width"></span>
                                </label>
                                <label class="text-xs text-zinc-400">
                                    Recuo (inset)
                                    <input type="range" min="0" max="80" x-model.number="thumbnailSettings.frame_inset" @input="scheduleThumbnailPreview()" class="w-full mt-2">
                                    <span class="text-[10px] text-zinc-500 tabular-nums" x-text="thumbnailSettings.frame_inset + 'px'"></span>
                                </label>
                                <label class="text-xs text-zinc-400 sm:col-span-2">
                                    Opacidade moldura
                                    <input type="range" min="0" max="100" x-model.number="thumbnailSettings.frame_opacity" @input="scheduleThumbnailPreview()" class="w-full mt-2">
                                    <span class="text-[10px] text-zinc-500 tabular-nums" x-text="thumbnailSettings.frame_opacity + '%'"></span>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                            <label class="text-xs text-zinc-400">
                                Fonte
                                <select x-model="thumbnailSettings.font_family" @change="scheduleThumbnailPreview()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                                    <template x-for="group in thumbnailFontGroups" :key="'fg-' + group.name">
                                        <optgroup :label="group.name">
                                            <template x-for="font in group.fonts" :key="font.slug">
                                                <option :value="font.slug" x-text="font.label"></option>
                                            </template>
                                        </optgroup>
                                    </template>
                                </select>
                            </label>

                            <div class="text-xs text-zinc-400">
                                <span class="block mb-1">Alinhamento do texto</span>
                                <div class="rounded-lg border border-zinc-800 bg-zinc-950/50 p-2 space-y-2 mt-1">
                                    <div>
                                        <p class="text-[10px] text-zinc-500 mb-1">Horizontal</p>
                                        <div class="flex gap-1">
                                            <button type="button" @click="setThumbnailTextAlign('left')" class="flex-1 text-[11px] py-1.5 rounded border" :class="thumbnailSettings.text_align === 'left' ? 'border-violet-500 bg-violet-950/50 text-white' : 'border-zinc-700 text-zinc-400 hover:border-zinc-600'">◧ Esq.</button>
                                            <button type="button" @click="setThumbnailTextAlign('center')" class="flex-1 text-[11px] py-1.5 rounded border" :class="thumbnailSettings.text_align === 'center' ? 'border-violet-500 bg-violet-950/50 text-white' : 'border-zinc-700 text-zinc-400 hover:border-zinc-600'">▣ Centro</button>
                                            <button type="button" @click="setThumbnailTextAlign('right')" class="flex-1 text-[11px] py-1.5 rounded border" :class="thumbnailSettings.text_align === 'right' ? 'border-violet-500 bg-violet-950/50 text-white' : 'border-zinc-700 text-zinc-400 hover:border-zinc-600'">◨ Dir.</button>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-zinc-500 mb-1">Vertical</p>
                                        <div class="flex gap-1">
                                            <button type="button" @click="setThumbnailVerticalAlign('top')" class="flex-1 text-[11px] py-1.5 rounded border" :class="thumbnailSettings.vertical_align === 'top' ? 'border-violet-500 bg-violet-950/50 text-white' : 'border-zinc-700 text-zinc-400 hover:border-zinc-600'">▴ Topo</button>
                                            <button type="button" @click="setThumbnailVerticalAlign('center')" class="flex-1 text-[11px] py-1.5 rounded border" :class="thumbnailSettings.vertical_align === 'center' ? 'border-violet-500 bg-violet-950/50 text-white' : 'border-zinc-700 text-zinc-400 hover:border-zinc-600'">▣ Meio</button>
                                            <button type="button" @click="setThumbnailVerticalAlign('bottom')" class="flex-1 text-[11px] py-1.5 rounded border" :class="thumbnailSettings.vertical_align === 'bottom' ? 'border-violet-500 bg-violet-950/50 text-white' : 'border-zinc-700 text-zinc-400 hover:border-zinc-600'">▾ Base</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-xs text-zinc-400">
                                Título (opcional)
                                <input
                                    type="text"
                                    x-model="thumbnailSettings.title_text"
                                    @input="onThumbnailTextInput()"
                                    @blur="flushThumbnailTextSave()"
                                    @keydown.space.stop
                                    class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm"
                                    placeholder="Usa texto do slide se vazio"
                                    autocomplete="off"
                                    spellcheck="true"
                                >
                            </label>
                            <label class="text-xs text-zinc-400">
                                Subtítulo (opcional)
                                <input
                                    type="text"
                                    x-model="thumbnailSettings.subtitle_text"
                                    @input="onThumbnailTextInput()"
                                    @blur="flushThumbnailTextSave()"
                                    @keydown.space.stop
                                    class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm"
                                    autocomplete="off"
                                    spellcheck="true"
                                >
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-xs text-zinc-400">Cor título<input type="color" x-model="thumbnailSettings.title_color" @input="scheduleThumbnailPreview()" class="w-full mt-1 h-9 rounded bg-zinc-800 border border-zinc-700 cursor-pointer"></label>
                            <label class="text-xs text-zinc-400">Cor subtítulo<input type="color" x-model="thumbnailSettings.subtitle_color" @input="scheduleThumbnailPreview()" class="w-full mt-1 h-9 rounded bg-zinc-800 border border-zinc-700 cursor-pointer"></label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {{-- Destaque --}}
                            <div class="rounded-lg border border-zinc-800 bg-zinc-950/50 p-3 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-medium text-zinc-300">Destaque</p>
                                    <button
                                        type="button"
                                        @click="thumbnailSettings.accent_opacity > 0 ? disableThumbnailAccent() : enableThumbnailAccent()"
                                        class="text-[10px] px-2 py-1 rounded border"
                                        :class="thumbnailSettings.accent_opacity > 0 ? 'border-zinc-600 text-zinc-400' : 'border-violet-500 bg-violet-950/40 text-violet-200'"
                                        x-text="thumbnailSettings.accent_opacity > 0 ? 'Nenhum' : 'Ativar'"
                                    ></button>
                                </div>
                                <label class="text-xs text-zinc-400 block" x-show="thumbnailSettings.accent_opacity > 0">
                                    Cor
                                    <input type="color" x-model="thumbnailSettings.accent_color" @input="enableThumbnailAccent(); scheduleThumbnailPreview()" class="w-full mt-1 h-9 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                                </label>
                                <label class="text-xs text-zinc-400 block" x-show="thumbnailSettings.accent_opacity > 0">
                                    Transparência
                                    <input type="range" min="0" max="100" x-model.number="thumbnailSettings.accent_opacity" @input="scheduleThumbnailPreview()" class="w-full mt-2 accent-red-500">
                                    <span class="text-[10px] text-zinc-500 tabular-nums" x-text="thumbnailSettings.accent_opacity + '%'"></span>
                                </label>
                                <p x-show="thumbnailSettings.accent_opacity <= 0" class="text-[10px] text-zinc-600">Sem faixa de destaque na capa.</p>
                            </div>

                            {{-- Fundo --}}
                            <div class="rounded-lg border border-zinc-800 bg-zinc-950/50 p-3 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-medium text-zinc-300">Fundo</p>
                                    <button
                                        type="button"
                                        @click="thumbnailSettings.background_opacity > 0 ? disableThumbnailBackground() : enableThumbnailBackground()"
                                        class="text-[10px] px-2 py-1 rounded border"
                                        :class="thumbnailSettings.background_opacity > 0 ? 'border-zinc-600 text-zinc-400' : 'border-violet-500 bg-violet-950/40 text-violet-200'"
                                        x-text="thumbnailSettings.background_opacity > 0 ? 'Nenhum' : 'Ativar'"
                                    ></button>
                                </div>
                                <label class="text-xs text-zinc-400 block" x-show="thumbnailSettings.background_opacity > 0">
                                    Cor
                                    <input type="color" x-model="thumbnailSettings.background_color" @input="enableThumbnailBackground(); scheduleThumbnailPreview()" class="w-full mt-1 h-9 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                                </label>
                                <label class="text-xs text-zinc-400 block" x-show="thumbnailSettings.background_opacity > 0">
                                    Transparência
                                    <input type="range" min="0" max="100" x-model.number="thumbnailSettings.background_opacity" @input="scheduleThumbnailPreview()" class="w-full mt-2 accent-zinc-400">
                                    <span class="text-[10px] text-zinc-500 tabular-nums" x-text="thumbnailSettings.background_opacity + '%'"></span>
                                </label>
                                <p x-show="thumbnailSettings.background_opacity <= 0" class="text-[10px] text-zinc-600">Sem camada de fundo sobre a imagem.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <label class="text-xs text-zinc-400">
                                Tamanho título
                                <input type="number" min="18" max="120" x-model.number="thumbnailSettings.title_size" @input="scheduleThumbnailPreview()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                            </label>
                            <label class="text-xs text-zinc-400">
                                Tamanho subtítulo
                                <input type="number" min="14" max="72" x-model.number="thumbnailSettings.subtitle_size" @input="scheduleThumbnailPreview()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                            </label>
                            <label class="text-xs text-zinc-400">
                                Clarear / escurecer
                                <input type="range" min="-100" max="100" x-model.number="thumbnailSettings.brightness" @input="scheduleThumbnailPreview()" class="w-full mt-2">
                                <span class="text-[10px] text-zinc-500 tabular-nums" x-text="thumbnailSettings.brightness + '%'"></span>
                            </label>
                            <label class="text-xs text-zinc-400">
                                Contraste
                                <input type="range" min="-100" max="100" x-model.number="thumbnailSettings.contrast" @input="scheduleThumbnailPreview()" class="w-full mt-2">
                                <span class="text-[10px] text-zinc-500 tabular-nums" x-text="thumbnailSettings.contrast + '%'"></span>
                            </label>
                        </div>

                        <label class="text-xs text-zinc-400 block">
                            Overlay escuro
                            <input type="range" min="0" max="100" x-model.number="thumbnailSettings.overlay_opacity" @input="scheduleThumbnailPreview()" class="w-full mt-2">
                            <span class="text-[10px] text-zinc-500 tabular-nums" x-text="thumbnailSettings.overlay_opacity + '%'"></span>
                        </label>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="saveAndPreviewThumbnail()" class="px-4 py-2 rounded-lg bg-zinc-700 hover:bg-zinc-600 text-sm">Atualizar preview</button>
                            <button type="button" @click="generateThumbnailFinal(false)" class="px-4 py-2 rounded-lg bg-violet-700 hover:bg-violet-600 text-sm">Gerar capa desta plataforma</button>
                            <button type="button" @click="generateAllPlatformThumbnails()" class="px-4 py-2 rounded-lg bg-emerald-800 hover:bg-emerald-700 text-sm">Gerar todas as plataformas</button>
                        </div>
                    </div>

                    {{-- Preview sticky — rolagem vertical independente --}}
                    <div class="w-full sm:w-[min(100%,340px)] shrink-0 mx-auto lg:mx-0 lg:sticky lg:top-4 lg:self-start z-10">
                        <div class="rounded-xl border border-zinc-700/80 bg-zinc-900/95 backdrop-blur-sm p-3 shadow-lg shadow-black/20">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <p class="text-xs text-zinc-400">Preview da plataforma selecionada</p>
                                <span class="text-[10px] text-zinc-600 tabular-nums" x-text="selectedThumbnailPlatform"></span>
                            </div>

                            <label x-show="thumbnailPreviewUrl" class="text-[10px] text-zinc-500 block mb-2">
                                Rolagem vertical do preview
                                <input
                                    type="range"
                                    min="0"
                                    max="100"
                                    x-model.number="thumbnailPreviewPanY"
                                    @input="onThumbnailPreviewPanInput()"
                                    class="w-full mt-1 accent-violet-500"
                                >
                                <span class="text-[9px] text-zinc-600">Use a barra, o controle ou a roda do mouse sobre a capa</span>
                            </label>

                            <div
                                class="rounded-lg border border-zinc-700 bg-zinc-950 overflow-y-auto overflow-x-hidden overscroll-contain max-h-[min(75vh,780px)] scroll-smooth"
                                x-ref="thumbnailPreviewScroll"
                                @scroll="syncThumbnailPreviewPanFromScroll()"
                            >
                                <img
                                    x-show="thumbnailPreviewUrl"
                                    :src="thumbnailPreviewUrl"
                                    alt="Preview thumbnail"
                                    class="w-full h-auto block select-none rounded"
                                    draggable="false"
                                    @load="syncThumbnailPreviewPanFromScroll()"
                                >
                                <div x-show="!thumbnailPreviewUrl" class="w-full aspect-video min-h-[160px] flex items-center justify-center text-sm text-zinc-600 p-4 text-center">
                                    Escolha plataforma, modelo e clique em Atualizar preview
                                </div>
                            </div>

                            <p class="text-[9px] text-zinc-600 mt-2 leading-snug">
                                O preview acompanha você ao rolar as opções. Use a barra acima ou a rolagem desta caixa para ver a capa inteira.
                            </p>
                        </div>
                    </div>
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
