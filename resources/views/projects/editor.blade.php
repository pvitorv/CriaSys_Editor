@extends('layouts.app')

@section('main-class', 'w-full max-w-none px-0 py-4')

@section('title', $project->name . ' — Editor')

@section('header-actions')
    <a href="{{ route('dashboard') }}" class="text-zinc-400 hover:text-white text-sm">← Dashboard</a>
@endsection

@section('content')
<script type="application/json" id="criasys-image-studio-presets">@json($imageStudioCatalog['presets'] ?? [], JSON_UNESCAPED_UNICODE)</script>
<script type="application/json" id="criasys-image-studio-defaults">@json($imageStudioCatalog['defaults'] ?? [], JSON_UNESCAPED_UNICODE)</script>
<script type="application/json" id="criasys-image-studio-primary-formats">@json($imageStudioCatalog['primary_formats'] ?? [], JSON_UNESCAPED_UNICODE)</script>
<script type="application/json" id="criasys-image-studio-group-order">@json($imageStudioCatalog['group_order'] ?? [], JSON_UNESCAPED_UNICODE)</script>
<script type="application/json" id="criasys-image-studio-fonts">@json($imageStudioCatalog['fonts'] ?? [], JSON_UNESCAPED_UNICODE)</script>
<script type="application/json" id="criasys-image-studio-icons">@json($imageStudioCatalog['icon_glyphs'] ?? [], JSON_UNESCAPED_UNICODE)</script>
<script type="application/json" id="criasys-image-studio-icon-fonts">@json($imageStudioCatalog['icon_fonts'] ?? [], JSON_UNESCAPED_UNICODE)</script>
<script type="application/json" id="criasys-image-studio-elements">@json(($imageStudioCatalog['elements'] ?? collect())->values(), JSON_UNESCAPED_UNICODE)</script>
<script type="application/json" id="criasys-image-studio-element-groups">@json($imageStudioCatalog['element_groups'] ?? [], JSON_UNESCAPED_UNICODE)</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap">
<div
    x-data="editorApp({{ $project->id }}, @js([
        'description' => $project->description ?? '',
        'name' => $project->name,
        'status' => $project->status,
        'deployment' => $deployment,
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
                        type="button"
                        x-show="slides.length"
                        @click="togglePreviewSubtitles()"
                        class="text-[10px] px-2 py-1 rounded border"
                        :class="previewShowSubtitles ? 'border-sky-700/50 text-sky-300 bg-sky-950/40' : 'border-zinc-600 text-zinc-400 bg-zinc-800'"
                        x-text="previewShowSubtitles ? 'CC ON' : 'CC OFF'"
                        :title="previewShowSubtitles ? 'Com legendas no preview' : 'Sem legendas no preview'"
                    ></button>
                    <button
                        x-show="canPlayPreview"
                        @click="previewPlaying ? stopSlideshow() : playSlideshow(true)"
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
                                :key="'pv-' + previewSlide?.id"
                                :src="previewSlide.video_url"
                                class="absolute inset-0 w-full h-full object-cover opacity-90"
                                autoplay
                                muted
                                playsinline
                                controls
                                x-ref="previewVideo"
                                @pause="onPreviewVideoPause()"
                            ></video>
                        </template>
                        <template x-if="previewSlide && !previewSlide?.video_url && previewSlide?.image_url">
                            <img :src="previewSlide.image_url" class="absolute inset-0 w-full h-full object-cover opacity-80">
                        </template>
                        <div
                            class="absolute inset-0 flex flex-col items-center text-center p-4 sm:p-6 overflow-hidden"
                            :class="[
                                previewVerticalAlignClass(),
                                !previewShowSubtitles && previewHasMediaBackground ? 'pointer-events-none' : '',
                            ]"
                            :style="previewOverlayStyle()"
                        >
                            <p
                                x-show="previewShowSubtitles || !previewHasMediaBackground"
                                class="font-medium leading-relaxed whitespace-pre-line max-w-prose w-full"
                                :style="previewTextStyle()"
                                x-text="previewVisibleText || (canPlayPreview ? '' : 'Cole o roteiro ou adicione slides')"
                            ></p>
                            <p x-show="!previewVisibleText && !slides.length" class="text-xs text-zinc-500 mt-4">Tela preta — texto e narração aparecem aqui</p>
                            <p x-show="!previewShowSubtitles && previewHasMediaBackground && slides.length" class="absolute bottom-2 left-2 right-2 text-[10px] text-zinc-500/80 text-center">Preview sem legenda · exporte com «Queimar legendas» desmarcado</p>
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
                    x-show="slides.length"
                    @click="toggleTimelineExpanded()"
                    class="text-[10px] px-2 py-1 rounded"
                    :class="timelineExpanded ? 'bg-violet-700 text-white hover:bg-violet-600' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-400'"
                    :title="timelineExpanded ? 'Voltar ao modo com rolagem' : 'Ver toda a timeline sem barras de rolagem'"
                    x-text="timelineExpanded ? '⛶ Recolher' : '⛶ Expandir'"
                ></button>
                <button
                    type="button"
                    x-show="slides.length"
                    @click="togglePreviewSubtitles()"
                    class="text-[10px] px-2 py-1.5 rounded-lg border"
                    :class="previewShowSubtitles ? 'bg-sky-950/50 border-sky-700/60 text-sky-200 hover:bg-sky-900/50' : 'bg-zinc-800 border-zinc-600 text-zinc-300 hover:bg-zinc-700'"
                    :title="previewShowSubtitles ? 'Ocultar texto/legendas no preview (como vídeo limpo)' : 'Mostrar texto/legendas no preview'"
                    x-text="previewShowSubtitles ? 'CC Com legenda' : 'CC Sem legenda'"
                ></button>
                <button
                    type="button"
                    x-show="slides.length"
                    @click="previewPlaying ? stopSlideshow() : playSlideshow(true)"
                    class="ml-2 text-xs px-3 py-1.5 rounded-lg"
                    :class="previewPlaying ? 'bg-red-900/50 text-red-300' : 'bg-violet-700 text-white hover:bg-violet-600'"
                    x-text="previewPlaying ? 'Parar' : '▶ Play timeline'"
                ></button>
            </div>
        </div>

        {{-- Mix de volumes (preview) --}}
        <div x-show="showTimelineAudioLanes" class="px-4 py-2 border-b border-zinc-800/80 bg-zinc-950/40">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="text-[10px] text-zinc-500 uppercase tracking-wide shrink-0">Mix preview</span>
                <div class="flex items-center gap-2 min-w-[140px]">
                    <span class="text-[9px] text-emerald-400 w-14 shrink-0">Narração</span>
                    <input type="range" min="0" max="1" step="0.05" x-model.number="mixVolumes.narration" @input="updatePreviewMixVolumes()" class="flex-1 min-w-[60px]">
                    <span class="text-[9px] text-zinc-400 w-8 tabular-nums shrink-0" x-text="Math.round((mixVolumes.narration || 0) * 100) + '%'"></span>
                </div>
                <div class="flex items-center gap-2 min-w-[140px]">
                    <span class="text-[9px] text-amber-400 w-14 shrink-0">Trilhas</span>
                    <input type="range" min="0" max="1" step="0.05" x-model.number="mixVolumes.music" @input="updatePreviewMixVolumes()" class="flex-1 min-w-[60px]">
                    <span class="text-[9px] text-zinc-400 w-8 tabular-nums shrink-0" x-text="Math.round((mixVolumes.music || 0) * 100) + '%'"></span>
                </div>
                <div class="flex items-center gap-2 min-w-[140px]">
                    <span class="text-[9px] text-rose-400 w-14 shrink-0">Efeitos</span>
                    <input type="range" min="0" max="1" step="0.05" x-model.number="mixVolumes.sfx" @input="updatePreviewMixVolumes()" class="flex-1 min-w-[60px]">
                    <span class="text-[9px] text-zinc-400 w-8 tabular-nums shrink-0" x-text="Math.round((mixVolumes.sfx || 0) * 100) + '%'"></span>
                </div>
                <button type="button" @click="openAudioTab()" class="text-[9px] text-violet-400 hover:text-violet-300 ml-auto">Volumes por trilha →</button>
            </div>
        </div>

        <div x-show="!slides.length" class="px-4 py-8 text-center text-sm text-zinc-500">
            Adicione slides para montar a linha do tempo.
        </div>

        <div
            x-show="slides.length"
            x-ref="timelineScroll"
            class="px-4 py-4 w-full"
            :class="timelineExpanded ? 'overflow-hidden' : 'overflow-x-auto overflow-y-auto'"
            :style="timelineExpanded ? '' : 'max-height: min(380px, 32vh);'"
        >
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

            <div
                class="relative"
                :style="'width: ' + timelineTrackWidthPx + 'px; min-width: ' + (timelineExpanded ? '100%' : timelineViewportWidthPx() + 'px')"
                x-ref="timelineTrackArea"
                @click="setPlayheadFromTimelineEvent($event)"
            >
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
                            @dragenter.prevent="dragEnterSlide(index)"
                            @dragover.prevent="dragEnterSlide(index)"
                            @drop.prevent="dropSlide(index)"
                            @click="timelineSlideClick(slide)"
                            class="flex-shrink-0 rounded-lg border-2 overflow-hidden transition-all flex flex-col group"
                            :class="{
                                'border-violet-400 ring-2 ring-violet-500/30 bg-violet-950/40': selectedSlide?.id === slide.id,
                                'border-zinc-600 bg-zinc-800/90 hover:border-zinc-500': selectedSlide?.id !== slide.id,
                                'border-emerald-500/90 ring-2 ring-emerald-500/25': previewPlaying && previewIndex === index,
                                'border-amber-400/80 ring-2 ring-amber-500/20 scale-[1.02]': dragOverIndex === index && dragFromIndex !== null && dragFromIndex !== index,
                            }"
                            :style="'width: ' + timelineClipWidth(slide) + 'px'"
                            :title="slidePreviewText(slide, index) + ' · ' + formatTimelineTime(slide.duration_seconds) + ' — arraste ⋮⋮ para reordenar'"
                        >
                            <div
                                draggable="true"
                                @dragstart.stop="dragStart(index)"
                                @dragend.stop="dragEndSlide()"
                                class="h-5 bg-zinc-950/90 border-b border-zinc-700/60 flex items-center justify-center cursor-grab active:cursor-grabbing shrink-0 text-zinc-500 hover:text-zinc-300 hover:bg-zinc-900"
                                title="Arrastar para reordenar"
                            >
                                <span class="text-[10px] select-none tracking-widest">⋮⋮</span>
                            </div>
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
                    <p class="text-[9px] text-zinc-600 mb-2">Arraste clipes na faixa com o mouse (precisão 0,5s · segure <kbd class="px-0.5 rounded bg-zinc-800">Shift</kbd> para 0,1s). Solte da biblioteca na faixa desejada.</p>

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
                            <div
                                class="relative h-7 rounded border border-zinc-800 bg-zinc-950/60 transition-colors"
                                :data-timeline-lane="'music:' + slot"
                                :class="audioDropHover?.lane === ('music:' + slot) || timelinePointerDrag?.laneKey === ('music:' + slot) ? 'ring-1 ring-amber-400/70 bg-amber-950/30' : ''"
                                :style="'width: ' + Math.max(timelineTrackWidthPx, 120) + 'px'"
                                @dragover.prevent="timelineAudioDragOver($event, 'music:' + slot)"
                                @dragleave="timelineAudioDragLeave($event, 'music:' + slot)"
                                @drop.prevent="timelineAudioDrop($event, 'music:' + slot)"
                            >
                                <div
                                    x-show="audioDropHover?.lane === ('music:' + slot)"
                                    class="absolute -top-5 z-40 text-[10px] font-medium tabular-nums text-amber-100 bg-amber-900/95 border border-amber-600/60 px-1.5 py-0.5 rounded pointer-events-none whitespace-nowrap"
                                    :style="'left: ' + Math.max(0, timelineSecondsToPx(audioDropHover?.sec || 0) - 18) + 'px'"
                                    x-text="formatTimelineTime(audioDropHover?.sec || 0)"
                                ></div>
                                <div
                                    x-show="audioDropHover?.lane === ('music:' + slot)"
                                    class="absolute top-0 bottom-0 w-0.5 bg-amber-300 shadow-[0_0_8px_rgba(252,211,77,0.9)] z-30 pointer-events-none"
                                    :style="'left: ' + timelineSecondsToPx(audioDropHover?.sec || 0) + 'px'"
                                >
                                    <div class="absolute -top-1 -left-1 w-2 h-2 bg-amber-300 rotate-45"></div>
                                </div>
                                <div
                                    x-show="track.file_path && musicTrackNeedsLoop(track)"
                                    class="absolute inset-y-0 rounded border border-amber-700/20 pointer-events-none"
                                    :style="'left: ' + timelineSecondsToPx(track.start_at || 0) + 'px; width: ' + timelineMusicClipWidth(track) + 'px; background: repeating-linear-gradient(90deg, rgba(245,158,11,0.08) 0, rgba(245,158,11,0.08) 8px, rgba(245,158,11,0.02) 8px, rgba(245,158,11,0.02) 16px)'"
                                    title="Repetição automática até o fim dos slides"
                                ></div>
                                <template x-for="(seg, segIdx) in musicTrackSegments(track)" :key="'tl-mseg-' + slot + '-' + segIdx">
                                    <div
                                        @mousedown.stop="startTimelinePointerDrag($event, musicSegmentDragPayload(slot, segIdx))"
                                        @click.stop="selectTimelineMusic(slot)"
                                        class="absolute inset-y-0 rounded border cursor-grab active:cursor-grabbing hover:brightness-110 select-none"
                                        :class="{
                                            'bg-amber-800/55 border-amber-400 ring-2 ring-amber-300 z-40 opacity-95': timelinePointerDragActive(slot, segIdx),
                                            'bg-amber-800/55 border-amber-400 ring-1 ring-amber-400 z-[1]': !timelinePointerDragActive(slot, segIdx) && timelineSelectedClip?.kind === 'music' && timelineSelectedClip?.slot === slot,
                                            'bg-amber-900/35 border-amber-700/40 z-[1]': !timelinePointerDragActive(slot, segIdx) && !(timelineSelectedClip?.kind === 'music' && timelineSelectedClip?.slot === slot),
                                        }"
                                        :style="'left: ' + timelineSecondsToPx(timelinePointerDragSec(slot, segIdx)) + 'px; width: ' + timelineMusicSegmentWidth(seg) + 'px'"
                                        :title="(seg.label || track.label) + ' · ' + formatTimelineTime(timelinePointerDragSec(slot, segIdx)) + ' — arraste horizontalmente'"
                                    >
                                        <span class="absolute inset-0 flex items-center px-1 text-[8px] text-amber-100/90 truncate pointer-events-none" x-text="formatTimelineTime(timelinePointerDragSec(slot, segIdx))"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="flex items-start gap-2 min-h-[26px]" @click.stop>
                        <span class="w-[4.5rem] shrink-0 text-[9px] text-rose-400/90 pt-1">Efeitos</span>
                        <div
                            data-timeline-lane="sfx"
                            class="relative h-7 rounded border border-zinc-800 bg-zinc-950/60 transition-colors"
                            :class="audioDropHover?.lane === 'sfx' || timelinePointerDrag?.laneKey === 'sfx' ? 'ring-1 ring-rose-400/70 bg-rose-950/30' : ''"
                            :style="'width: ' + Math.max(timelineTrackWidthPx, 120) + 'px'"
                            @dragover.prevent="timelineAudioDragOver($event, 'sfx')"
                            @dragleave="timelineAudioDragLeave($event, 'sfx')"
                            @drop.prevent="timelineAudioDrop($event, 'sfx')"
                        >
                            <div
                                x-show="audioDropHover?.lane === 'sfx'"
                                class="absolute -top-5 z-40 text-[10px] font-medium tabular-nums text-rose-100 bg-rose-900/95 border border-rose-600/60 px-1.5 py-0.5 rounded pointer-events-none whitespace-nowrap"
                                :style="'left: ' + Math.max(0, timelineSecondsToPx(audioDropHover?.sec || 0) - 18) + 'px'"
                                x-text="formatTimelineTime(audioDropHover?.sec || 0)"
                            ></div>
                            <div
                                x-show="audioDropHover?.lane === 'sfx'"
                                class="absolute top-0 bottom-0 w-0.5 bg-rose-300 shadow-[0_0_8px_rgba(253,164,175,0.9)] z-30 pointer-events-none"
                                :style="'left: ' + timelineSecondsToPx(audioDropHover?.sec || 0) + 'px'"
                            >
                                <div class="absolute -top-1 -left-1 w-2 h-2 bg-rose-300 rotate-45"></div>
                            </div>
                            <template x-for="(fx, fxIdx) in soundEffects" :key="'tl-fx-' + fx.id">
                                <div
                                    @mousedown.stop="startTimelinePointerDrag($event, sfxDragPayload(fx))"
                                    @click.stop="selectTimelineSfx(fx)"
                                    @dblclick.stop="testSoundEffect(fx)"
                                    class="absolute inset-y-0 rounded border cursor-grab active:cursor-grabbing hover:brightness-110 select-none group/fx"
                                    :class="{
                                        'bg-rose-800/55 border-rose-400 ring-2 ring-rose-300 z-40 opacity-95': timelinePointerDragActive(null, null, fx.id),
                                        'bg-rose-800/55 border-rose-400 ring-1 ring-rose-400 z-[1]': !timelinePointerDragActive(null, null, fx.id) && timelineSelectedClip?.kind === 'sfx' && timelineSelectedClip?.id === fx.id,
                                        'bg-rose-900/35 border-rose-600/40 z-[1]': !timelinePointerDragActive(null, null, fx.id) && !(timelineSelectedClip?.kind === 'sfx' && timelineSelectedClip?.id === fx.id),
                                    }"
                                    :style="'left: ' + timelineSecondsToPx(timelinePointerDragFxSec(fx.id)) + 'px; width: ' + timelineSfxSegmentWidth(fx) + 'px'"
                                    :title="(fx.label || 'Efeito') + ' · ' + formatTimelineTime(timelinePointerDragFxSec(fx.id)) + ' — arraste · duplo-clique para testar'"
                                >
                                    <button
                                        type="button"
                                        @mousedown.stop
                                        @click.stop="testSoundEffect(fx)"
                                        class="absolute right-0.5 top-0.5 z-10 hidden group-hover/fx:flex items-center justify-center w-4 h-4 rounded bg-rose-900/90 text-[8px] text-rose-100 hover:bg-rose-700"
                                        title="Testar efeito"
                                    >▶</button>
                                    <span class="absolute inset-0 flex items-center px-1 text-[8px] text-rose-100/90 truncate pointer-events-none" x-text="formatTimelineTime(timelinePointerDragFxSec(fx.id))"></span>
                                </div>
                            </template>
                            <span x-show="!soundEffects.length" class="absolute inset-0 flex items-center justify-center text-[9px] text-zinc-600 pointer-events-none">
                                solte efeitos aqui ou use Trilhas & FX
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
                        <div class="flex items-center gap-2 mb-2">
                            <label class="text-[10px] text-zinc-500 shrink-0 w-14">Volume</label>
                            <input type="range" min="0" max="1" step="0.05" x-model.number="mixVolumes.narration" @input="updatePreviewMixVolumes()" class="flex-1">
                            <span class="text-[10px] text-zinc-400 w-10 tabular-nums" x-text="Math.round((mixVolumes.narration || 0) * 100) + '%'"></span>
                        </div>
                        <audio :src="narration.audio_url" controls class="w-full" preload="auto"></audio>
                    </div>
                </div>
            </div>

            {{-- Áudio --}}
            <div x-show="activeTab === 'audio'" class="space-y-5">
                <div>
                    <h3 class="text-sm font-medium text-white mb-1">Trilhas sonoras (até 3)</h3>
                    <p class="text-[11px] text-zinc-500 mb-3">Mix de trilhas com volume e início independentes. Trilhas curtas repetem automaticamente até cobrir todos os slides. <strong class="text-zinc-400">Arraste</strong> segmentos abaixo para a timeline na faixa e segundo desejados.</p>
                    <p class="text-[10px] text-zinc-600 mb-3">Atalhos: <kbd class="px-1 py-0.5 rounded bg-zinc-800 border border-zinc-700">Espaço</kbd> play/pause · <kbd class="px-1 py-0.5 rounded bg-zinc-800 border border-zinc-700">←</kbd><kbd class="px-1 py-0.5 rounded bg-zinc-800 border border-zinc-700">→</kbd> ±1s na timeline</p>
                    <button type="button" @click="openLibraryForMusic(selectedMusicSlot ?? 0)" class="mb-3 text-xs px-3 py-1.5 rounded-lg bg-amber-900/40 border border-amber-700/40 text-amber-200 hover:bg-amber-900/60">
                        🔍 Buscar trilha na biblioteca
                    </button>
                    <div class="space-y-3">
                        <template x-for="(track, slot) in audioTracks" :key="'music-' + slot">
                            <div class="rounded-lg border border-zinc-800 bg-zinc-950/50 p-3 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <button type="button" @click="selectedMusicSlot = slot" class="text-xs font-medium text-zinc-200 hover:text-amber-200" :class="selectedMusicSlot === slot ? 'text-amber-300' : ''" x-text="track.label"></button>
                                    <span x-show="track.file_path" class="text-[10px] text-emerald-400">ativa</span>
                                    <button x-show="track.id" type="button" @click="removeMusicTrack(slot)" class="text-[10px] text-red-400 hover:text-red-300">Remover</button>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <div>
                                        <label class="text-[10px] text-zinc-500">Volume <span class="text-zinc-600" x-text="Math.round((track.volume || 0) * 100) + '%'"></span></label>
                                        <input type="range" min="0" max="1" step="0.05" x-model.number="track.volume" @input="onMusicVolumeInput(slot)" @change="saveMusicTrack(slot)" class="block w-full mt-1">
                                    </div>
                                    <div>
                                        <label class="text-[10px] text-zinc-500">Início (s)</label>
                                        <input type="number" min="0" step="0.1" x-model.number="track.start_at" @change="saveMusicTrack(slot)" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1 text-xs">
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center gap-1 text-[10px] text-zinc-400">
                                            <input type="checkbox" x-model="track.loop_enabled" @change="saveMusicTrack(slot)">
                                            Repetir até fim
                                        </label>
                                    </div>
                                    <div x-show="slot === 0" class="flex items-end">
                                        <label class="flex items-center gap-1 text-[10px] text-zinc-400">
                                            <input type="checkbox" x-model="track.ducking_enabled" @change="saveMusicTrack(slot)">
                                            Ducking
                                        </label>
                                    </div>
                                    <div class="flex items-end gap-2 col-span-2 sm:col-span-4">
                                        <label class="text-[10px] text-violet-400 cursor-pointer hover:text-violet-300">
                                            <input type="file" accept="audio/*" class="hidden" @change="selectedMusicSlot = slot; uploadAudio($event)">
                                            Importar MP3/WAV
                                        </label>
                                        <button x-show="track.file_path" type="button" @click="selectedMusicSlot = slot; openLibraryForMusic(slot)" class="text-[10px] text-amber-400 hover:text-amber-300">+ Encadear trilha</button>
                                    </div>
                                </div>
                                <div x-show="track.file_path && musicTrackSegments(track).length" class="space-y-1">
                                    <p class="text-[10px] text-zinc-500">Segmentos na timeline (<span x-text="formatTimelineTime(timelineTotalSeconds)"></span> total dos slides)</p>
                                    <template x-for="(seg, segIdx) in musicTrackSegments(track)" :key="'mseg-' + slot + '-' + segIdx">
                                        <div
                                            draggable="true"
                                            @dragstart.stop="startAudioDrag(musicSegmentDragPayload(slot, segIdx), $event)"
                                            @dragend.stop="endAudioDrag()"
                                            class="flex items-center gap-2 text-[10px] text-zinc-400 rounded px-1 py-0.5 cursor-grab active:cursor-grabbing hover:bg-amber-950/40 border border-transparent hover:border-amber-800/50"
                                            :title="'Arraste para a timeline · ' + formatTimelineTime(seg.start_at)"
                                        >
                                            <span class="text-amber-500/80 select-none">⋮⋮</span>
                                            <span class="text-amber-500/80" x-text="segIdx + 1 + '.'"></span>
                                            <span class="truncate flex-1" x-text="seg.label"></span>
                                            <span class="tabular-nums text-zinc-500" x-text="formatTimelineTime(seg.start_at) + ' · ' + formatTimelineTime(seg.duration)"></span>
                                        </div>
                                    </template>
                                    <p x-show="musicTrackNeedsLoop(track)" class="text-[10px] text-amber-500/80">↻ Repete automaticamente após o último segmento</p>
                                </div>
                                <audio x-show="track.audio_url" :src="track.audio_url" controls class="w-full h-8 mt-1" preload="metadata"></audio>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="border-t border-zinc-800 pt-4">
                    <h3 class="text-sm font-medium text-white mb-1">Efeitos sonoros</h3>
                    <p class="text-[11px] text-zinc-500 mb-3">Posicione efeitos na faixa FX da timeline. <strong class="text-zinc-400">Arraste</strong> o bloco na timeline (0,5s · segure <kbd class="px-0.5 rounded bg-zinc-800 border border-zinc-700">Shift</kbd> para 0,1s) ou solte da biblioteca no segundo desejado.</p>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button type="button" @click="openLibraryForSfx(timelinePlayheadSec)" class="text-xs px-3 py-1.5 rounded-lg bg-rose-900/40 border border-rose-700/40 text-rose-200 hover:bg-rose-900/60">
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
                                <div
                                    draggable="true"
                                    @dragstart.stop="startAudioDrag(sfxDragPayload(fx), $event)"
                                    @dragend.stop="endAudioDrag()"
                                    class="flex items-center gap-2 mb-2 px-1 py-1 rounded cursor-grab active:cursor-grabbing hover:bg-rose-950/30 border border-transparent hover:border-rose-800/40"
                                    :title="'Arraste para a faixa FX · ' + formatTimelineTime(fx.start_at)"
                                >
                                    <span class="text-rose-400/80 select-none text-[10px]">⋮⋮</span>
                                    <span class="text-xs font-medium text-zinc-200 truncate flex-1" x-text="fx.label || 'Efeito'"></span>
                                    <span class="text-[10px] text-zinc-500 tabular-nums" x-text="formatTimelineTime(fx.start_at) + ' · ' + formatTimelineTime(timelineEffectiveDuration(fx, fx.clip_duration || fx.source_duration || 2))"></span>
                                </div>
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
                                        <label class="text-[10px] text-zinc-500">Volume <span class="text-zinc-600" x-text="Math.round((fx.volume || 0) * 100) + '%'"></span></label>
                                        <input type="range" min="0" max="1" step="0.05" x-model.number="fx.volume" @input="onSfxVolumeInput(fx)" @change="saveSoundEffect(fx)" class="block w-full mt-1">
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" @click.stop="testSoundEffect(fx)" class="text-[10px] text-rose-300 hover:text-rose-200">▶ Testar</button>
                                        <button type="button" @click="removeSoundEffect(fx)" class="text-[10px] text-red-400">Remover</button>
                                    </div>
                                </div>
                                <audio x-show="fx.audio_url" :src="fx.audio_url" controls class="w-full h-8 mt-2" preload="metadata"></audio>
                            </div>
                        </template>
                    </div>
                </div>

                <p class="text-[10px] text-zinc-600">No preview, narração + trilhas + efeitos tocam juntos ao pressionar ▶ Play timeline. Use <strong>▶ Testar</strong> em cada efeito antes de inserir. Coloque o playhead <em>antes</em> do efeito na timeline para ouvi-lo no play.</p>
            </div>

            {{-- Biblioteca --}}
            <div x-show="activeTab === 'biblioteca'" class="space-y-3">
                <div class="flex flex-wrap gap-2 border-b border-zinc-800 pb-3">
                    <button type="button" @click="setMediaLibraryMode('visual')" class="text-xs px-3 py-1.5 rounded-lg" :class="mediaType === 'image' || mediaType === 'video' ? 'bg-violet-700 text-white' : 'bg-zinc-800 text-zinc-400'">Visual</button>
                    <button type="button" @click="setMediaLibraryMode('music')" class="text-xs px-3 py-1.5 rounded-lg" :class="mediaType === 'music' ? 'bg-amber-800 text-amber-100' : 'bg-zinc-800 text-zinc-400'">🎵 Trilhas</button>
                    <button type="button" @click="setMediaLibraryMode('sfx')" class="text-xs px-3 py-1.5 rounded-lg" :class="mediaType === 'sfx' ? 'bg-rose-800 text-rose-100' : 'bg-zinc-800 text-zinc-400'">💥 Efeitos</button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="mediaPanel = 'search'" class="text-[11px] px-3 py-1.5 rounded-lg border" :class="mediaPanel === 'search' ? 'border-violet-500 bg-violet-950/40 text-violet-200' : 'border-zinc-700 text-zinc-400 hover:border-zinc-600'">🔍 Buscar online</button>
                    <button type="button" @click="mediaPanel = 'upload'; resetMediaUploadMeta()" class="text-[11px] px-3 py-1.5 rounded-lg border" :class="mediaPanel === 'upload' ? 'border-emerald-500 bg-emerald-950/40 text-emerald-200' : 'border-zinc-700 text-zinc-400 hover:border-zinc-600'">📁 Meu arquivo</button>
                    <button type="button" @click="mediaPanel = 'url'; mediaImportPreview = null" class="text-[11px] px-3 py-1.5 rounded-lg border" :class="mediaPanel === 'url' ? 'border-sky-500 bg-sky-950/40 text-sky-200' : 'border-zinc-700 text-zinc-400 hover:border-zinc-600'">🔗 Por link</button>
                </div>

                {{-- Cadastro externo: upload --}}
                <div x-show="mediaPanel === 'upload'" x-cloak class="rounded-xl border border-emerald-800/50 bg-emerald-950/20 p-4 space-y-3">
                    <h3 class="text-sm font-medium text-emerald-200">Enviar arquivo do seu computador</h3>
                    <p class="text-[11px] text-zinc-500">Cadastre licença, créditos ao autor e origem — entra na exportação de descrições automaticamente.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] text-zinc-500">Título / descrição</label>
                            <input type="text" x-model="mediaUploadMeta.item_title" class="w-full mt-1 rounded bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm" placeholder="Nome da mídia">
                        </div>
                        <div>
                            <label class="text-[10px] text-zinc-500">Autor / artista</label>
                            <input type="text" x-model="mediaUploadMeta.author" class="w-full mt-1 rounded bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm" placeholder="Quem criou">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-[10px] text-zinc-500">Crédito / licença (texto para YouTube, TikTok…)</label>
                            <textarea x-model="mediaUploadMeta.attribution_text" rows="2" class="w-full mt-1 rounded bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm" placeholder="Ex.: Foto por João Silva — licença Envato Elements, projeto XYZ"></textarea>
                        </div>
                        <div>
                            <label class="text-[10px] text-zinc-500">Link original (opcional)</label>
                            <input type="url" x-model="mediaUploadMeta.original_url" class="w-full mt-1 rounded bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm" placeholder="https://...">
                        </div>
                        <div>
                            <label class="text-[10px] text-zinc-500">Licença paga cadastrada</label>
                            <select x-model.number="mediaUploadMeta.stock_license_id" class="w-full mt-1 rounded bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm">
                                <option :value="null">Nenhuma (usar crédito manual acima)</option>
                                <template x-for="reg in stockLicenses" :key="'up-lic-' + reg.id">
                                    <option :value="reg.id" x-text="(reg.provider || 'licença') + ' — ' + (reg.project_title || 'projeto')"></option>
                                </template>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-[11px] text-zinc-400">
                                <input type="checkbox" x-model="mediaUploadMeta.requires_attribution" class="rounded border-zinc-600">
                                Exigir crédito na descrição
                            </label>
                        </div>
                        <div x-show="mediaType === 'image' || mediaType === 'video'" class="flex items-end">
                            <label class="flex items-center gap-2 text-[11px] text-zinc-400">
                                <input type="checkbox" x-model="mediaUploadAttachToSlide" class="rounded border-zinc-600">
                                Inserir no slide selecionado
                            </label>
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-700 hover:bg-emerald-600 text-sm font-medium cursor-pointer">
                        <span x-text="mediaType === 'music' ? 'Enviar trilha (MP3/WAV)' : (mediaType === 'sfx' ? 'Enviar efeito sonoro' : (mediaType === 'video' ? 'Enviar vídeo' : 'Enviar imagem'))"></span>
                        <input type="file" :accept="mediaUploadAcceptTypes()" class="hidden" @change="submitMediaUpload($event)">
                    </label>
                    <p x-show="mediaType === 'music'" class="text-[10px] text-zinc-600">Vai para a trilha <span x-text="(selectedMusicSlot ?? 0) + 1"></span>. Ajuste o slot no painel de busca se precisar.</p>
                    <p x-show="mediaType === 'sfx'" class="text-[10px] text-zinc-600">Posição na timeline: <span x-text="formatTimelineTime(timelinePlayheadSec)"></span></p>
                </div>

                {{-- Importação por link --}}
                <div x-show="mediaPanel === 'url'" x-cloak class="rounded-xl border border-sky-800/50 bg-sky-950/20 p-4 space-y-3">
                    <h3 class="text-sm font-medium text-sky-200">Importar por link</h3>
                    <p class="text-[11px] text-zinc-500">Cole a URL da página (Pixabay, Pexels, Unsplash, Mixkit) ou link direto do arquivo (.jpg, .mp4, .mp3). O sistema busca licença e créditos.</p>
                    <div class="flex flex-wrap gap-2">
                        <input type="url" x-model="mediaImportUrl" @keydown.enter.prevent="resolveMediaUrl()" class="flex-1 min-w-[220px] rounded bg-zinc-900 border border-zinc-700 px-3 py-2 text-sm" placeholder="https://pixabay.com/photos/... ou link direto">
                        <button type="button" @click="resolveMediaUrl()" :disabled="mediaImportLoading" class="px-3 py-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-sm disabled:opacity-50">Analisar</button>
                        <button type="button" @click="importMediaFromUrl()" :disabled="mediaImportLoading" class="px-4 py-2 rounded-lg bg-sky-700 hover:bg-sky-600 text-sm disabled:opacity-50">
                            <span x-text="mediaImportLoading ? 'Importando…' : 'Importar'"></span>
                        </button>
                    </div>
                    <div x-show="mediaImportPreview" class="rounded-lg border border-zinc-700 bg-zinc-900/80 p-3 space-y-2">
                        <p class="text-xs font-medium text-zinc-200" x-text="mediaImportPreview?.title || 'Prévia'"></p>
                        <p class="text-[10px] text-zinc-500">
                            <span x-text="mediaImportPreview?.source"></span>
                            <span x-show="mediaImportPreview?.author"> · por <span x-text="mediaImportPreview?.author"></span></span>
                            <span x-show="mediaImportPreview?.license_type"> · <span x-text="mediaImportPreview?.license_type"></span></span>
                        </p>
                        <p x-show="mediaImportPreview?.attribution_text" class="text-[10px] text-yellow-500/90" x-text="mediaImportPreview?.attribution_text"></p>
                        <img x-show="mediaImportPreview?.preview_url && (mediaImportPreview?.type === 'image' || mediaImportPreview?.type === 'video')" :src="mediaImportPreview?.preview_url" class="max-h-32 rounded border border-zinc-800" alt="Prévia">
                        <audio x-show="mediaImportPreview?.download_url && (mediaImportPreview?.type === 'audio' || mediaImportPreview?.type === 'sfx')" :src="mediaImportPreview?.download_url" controls class="w-full h-8" preload="none"></audio>
                    </div>
                </div>

                <p class="text-xs text-zinc-500" x-show="mediaType === 'image' || mediaType === 'video'">Imagens e vídeos — Openverse grátis + Pexels/Pixabay/Unsplash com chave.</p>

                {{-- Arquivos já salvos neste projeto (Image Studio, uploads, imports) --}}
                <div x-show="mediaType === 'image' || mediaType === 'video'" class="rounded-xl border border-emerald-900/40 bg-emerald-950/15 p-3 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-medium text-emerald-200">Biblioteca deste projeto</h3>
                        <div class="flex items-center gap-2">
                            <span x-show="projectLibraryVisualAssets().length" class="text-[10px] text-zinc-500 tabular-nums" x-text="projectLibraryVisualAssets().length + ' arquivo(s)'"></span>
                            <button
                                type="button"
                                x-show="projectLibraryVisualAssets().length"
                                @click="toggleProjectLibraryExpanded()"
                                class="text-[10px] px-2 py-1 rounded border border-zinc-700 hover:bg-zinc-800 text-zinc-400"
                                x-text="projectLibraryExpanded ? '⛶ Recolher' : '⛶ Expandir'"
                            ></button>
                            <button type="button" @click="loadProjectLibraryAssets()" class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-400">Atualizar</button>
                        </div>
                    </div>
                    <p class="text-[10px] text-zinc-500">Arquivos salvos aqui (Image Studio, upload, importação). Use no slide ou reabra no Studio.</p>
                    <p x-show="projectLibraryLoading" class="text-xs text-zinc-500">Carregando…</p>
                    <p x-show="!projectLibraryLoading && !projectLibraryVisualAssets().length" class="text-xs text-zinc-600">Nenhum arquivo ainda — salve do Image Studio ou importe mídia.</p>
                    <div x-show="projectLibraryVisualAssets().length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2" :class="projectLibraryScrollClass()">
                        <template x-for="asset in projectLibraryVisualAssets()" :key="'proj-asset-' + asset.id">
                            <div class="rounded-lg border border-zinc-700 bg-zinc-900/80 overflow-hidden group relative">
                                <div class="relative cursor-pointer" @click="insertProjectAssetToSlide(asset)">
                                    <img :src="asset.preview_url || asset.url" :alt="asset.item_title || 'Arquivo'" class="w-full h-20 object-cover" loading="lazy">
                                    <span class="absolute bottom-0 left-0 right-0 bg-black/75 text-[9px] px-1 py-0.5 truncate" x-text="asset.source === 'image_studio' ? 'Image Studio' : (asset.source || 'projeto')"></span>
                                    <div class="absolute inset-0 bg-violet-900/60 opacity-0 group-hover:opacity-100 flex items-center justify-center text-[10px] font-medium text-center px-1">Inserir no slide</div>
                                </div>
                                <button
                                    type="button"
                                    @click.stop="deleteProjectLibraryAsset(asset)"
                                    class="absolute top-1 left-1 z-10 text-[9px] px-1.5 py-0.5 rounded bg-red-900/90 hover:bg-red-700 text-white opacity-0 group-hover:opacity-100 transition-opacity"
                                    title="Remover da biblioteca"
                                >✕</button>
                                <button
                                    type="button"
                                    x-show="asset.type === 'image'"
                                    @click.stop="imageStudioImportFromAsset(asset)"
                                    class="absolute top-1 right-1 z-10 text-[9px] px-1.5 py-0.5 rounded bg-violet-700/90 hover:bg-violet-600 text-white opacity-0 group-hover:opacity-100 transition-opacity"
                                    title="Abrir no Image Studio"
                                >🎨</button>
                                <p class="text-[9px] text-zinc-500 px-1 py-1 truncate" x-text="asset.item_title || 'Sem título'"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <p class="text-xs text-zinc-500" x-show="mediaType === 'music'">Biblioteca de trilhas — Mixkit grátis + Freesound/Pixabay (com chave). Créditos vão para a descrição automaticamente.</p>
                <p class="text-xs text-zinc-500" x-show="mediaType === 'sfx'">Biblioteca de efeitos sonoros — Mixkit grátis + Freesound (CC, crédito ao autor obrigatório).</p>

                <div class="flex flex-wrap gap-2" x-show="mediaPanel === 'search'">
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
                        @input="_mediaAutoSearchToken++; _mediaSearchSeq++; mediaResults = []; mediaHasMore = false"
                        @keydown.enter.prevent="searchMedia()"
                        :placeholder="mediaType === 'video' ? 'Objeto ou cena — gato, bola, praia, cidade…' : (mediaType === 'sfx' ? 'Buscar efeito — impacto, risada, whoosh, notification…' : (mediaType === 'music' ? 'Buscar trilha — ambient, cinematic, upbeat…' : 'Buscar em português — praia, futebol, cidade…'))"
                        class="flex-1 min-w-[200px] rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm"
                    >
                    <button x-show="mediaType === 'image'" @click="searchFromSlideBody()" :disabled="!selectedSlide?.body_text?.trim()" class="px-3 py-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 disabled:opacity-40 text-sm">Texto slide</button>
                    <button @click="searchMedia()" :disabled="mediaSearching" class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-sm">
                        <span x-text="mediaSearching ? 'Buscando...' : 'Buscar'"></span>
                    </button>
                </div>

                <div x-show="mediaLibraryProviders && (mediaType === 'music' || mediaType === 'sfx') && mediaPanel === 'search'" class="flex flex-wrap gap-2 text-[10px]">
                    <template x-for="provider in (mediaType === 'music' ? mediaLibraryProviders?.music : mediaLibraryProviders?.sfx) || []" :key="provider.id">
                        <span class="px-2 py-1 rounded border" :class="provider.configured ? 'border-emerald-700/50 text-emerald-400 bg-emerald-950/30' : 'border-zinc-700 text-zinc-500'">
                            <span x-text="provider.label"></span>
                            <span x-show="provider.attribution" title="Requer crédito na descrição"> ©</span>
                            <span x-show="!provider.configured"> (sem chave)</span>
                        </span>
                    </template>
                </div>

                <p x-show="mediaErrors.length && !mediaResults.length && mediaPanel === 'search'" class="text-xs text-yellow-400" x-text="mediaErrors.join(' ')"></p>
                <p x-show="mediaResults.length && (mediaType === 'image') && mediaPanel === 'search'" class="text-xs text-emerald-400">Clique na imagem para inserir no slide — ou use <strong>Image Studio</strong> para editar a arte.</p>
                <p x-show="mediaResults.length && mediaType === 'video' && mediaPanel === 'search'" class="text-xs text-emerald-400">Clique no vídeo para inserir como B-roll no slide selecionado.</p>
                <p x-show="mediaResults.length && mediaType === 'music' && mediaPanel === 'search'" class="text-xs text-amber-400">Ouça o preview — clique em Inserir ou <strong>arraste ⋮⋮</strong> para a faixa desejada na timeline.</p>
                <p x-show="mediaResults.length && mediaType === 'sfx' && mediaPanel === 'search'" class="text-xs text-rose-400">Ouça e clique em Inserir ou <strong>arraste ⋮⋮</strong> para a faixa FX na timeline.</p>

                <div x-show="publishAuto && projectCreditsCount" class="rounded border border-emerald-800/40 bg-emerald-950/30 p-2">
                    <p class="text-xs text-emerald-300">
                        ✓ <strong>Licenças ativas</strong> — ao importar, créditos e descrições (YouTube, TikTok, Instagram) são gerados automaticamente nos arquivos de exportação.
                    </p>
                </div>

                <div x-show="mediaPanel === 'search' && mediaResults.length" class="flex items-center justify-between gap-2">
                    <span class="text-[11px] text-zinc-500 tabular-nums" x-text="mediaResults.length + ' resultado(s)' + (mediaHasMore ? ' · há mais' : '')"></span>
                    <button
                        type="button"
                        @click="toggleMediaSearchExpanded()"
                        class="text-[10px] px-2 py-1 rounded border border-zinc-700 hover:bg-zinc-800 text-zinc-400"
                        x-text="mediaSearchExpanded ? '⛶ Recolher lista' : '⛶ Expandir lista'"
                    ></button>
                </div>

                <div x-show="mediaPanel === 'search'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3" :class="mediaResultsScrollClass()">
                    <template x-for="(item, itemIndex) in mediaResults" :key="item.source + '-' + item.id">
                        <div class="rounded-lg border border-zinc-700 bg-zinc-800/80 overflow-hidden group">
                            <template x-if="item.type === 'audio' || item.type === 'sfx'">
                                <div class="p-3 space-y-2">
                                    <div
                                        x-show="mediaType === 'music' || mediaType === 'sfx'"
                                        draggable="true"
                                        @dragstart.stop="startAudioDrag(mediaType === 'music' ? libraryMusicDragPayload(item, itemIndex) : librarySfxDragPayload(item, itemIndex), $event)"
                                        @dragend.stop="endAudioDrag()"
                                        class="flex items-center gap-2 px-1 py-1 -mx-1 rounded cursor-grab active:cursor-grabbing hover:bg-zinc-700/50 border border-transparent hover:border-violet-700/40"
                                        :title="mediaType === 'music' ? 'Arraste para uma faixa de trilha na timeline' : 'Arraste para a faixa FX na timeline'"
                                    >
                                        <span class="text-violet-400/80 select-none text-[10px]">⋮⋮</span>
                                        <p class="text-xs font-medium text-zinc-200 line-clamp-2 flex-1" x-text="item.title || item.author || 'Áudio'"></p>
                                        <span class="text-[9px] uppercase shrink-0 px-1.5 py-0.5 rounded bg-zinc-900 text-zinc-400" x-text="item.source"></span>
                                    </div>
                                    <div x-show="mediaType !== 'music' && mediaType !== 'sfx'" class="flex items-start justify-between gap-2">
                                        <p class="text-xs font-medium text-zinc-200 line-clamp-2" x-text="item.title || item.author || 'Áudio'"></p>
                                        <span class="text-[9px] uppercase shrink-0 px-1.5 py-0.5 rounded bg-zinc-900 text-zinc-400" x-text="item.source"></span>
                                    </div>
                                    <audio x-show="item.preview_url || item.download_url" :src="item.preview_url || item.download_url" controls class="w-full h-8" preload="none" @click.stop></audio>
                                    <p x-show="item.duration_seconds" class="text-[10px] text-zinc-500" x-text="item.duration_seconds + 's'"></p>
                                    <p x-show="item.attribution_text" class="text-[9px] text-zinc-500 line-clamp-2" x-text="item.attribution_text"></p>
                                    <div class="flex items-center justify-between gap-2 pt-1">
                                        <span x-show="item.requires_attribution || item.attribution_text" class="text-[10px] text-yellow-500" title="Crédito na descrição">© crédito</span>
                                        <span x-show="item.license_type" class="text-[9px] text-zinc-600 truncate" x-text="item.license_type"></span>
                                        <button type="button" @click.stop="previewLibraryAudio(item)" class="text-xs px-2 py-1 rounded bg-zinc-700 hover:bg-zinc-600 shrink-0">▶ Testar</button>
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

                <div x-show="mediaPanel === 'search' && mediaHasMore" class="flex justify-center pt-2">
                    <button
                        type="button"
                        @click="loadMoreMediaResults()"
                        :disabled="mediaSearching"
                        class="text-xs px-4 py-2 rounded-lg border border-violet-700/50 bg-violet-950/40 text-violet-200 hover:bg-violet-900/50 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        <span x-text="mediaSearching ? 'Carregando…' : 'Ver mais resultados'"></span>
                    </button>
                </div>
            </div>

            {{-- Image Studio --}}
            <div x-show="activeTab === 'image_studio'" class="space-y-4" x-cloak>
                <style>
                    .is-ic-fa-solid { font-family: "Font Awesome 6 Free"; font-weight: 900; font-style: normal; }
                    .is-ic-fa-regular { font-family: "Font Awesome 6 Free"; font-weight: 400; font-style: normal; }
                    .is-ic-fa-brands { font-family: "Font Awesome 6 Brands"; font-weight: 400; font-style: normal; }
                    .is-ic-material { font-family: "Material Symbols Outlined"; font-weight: 400; font-style: normal; font-size: 22px; line-height: 1; font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
                </style>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-violet-200">Image Studio</h3>
                        <p class="text-[11px] text-zinc-500 mt-0.5">Defina largura e altura do canvas · consulte medidas oficiais no modal · desenhe livremente.</p>
                        <p class="text-[10px] mt-1 text-emerald-400 tabular-nums" x-show="imageStudioReady" x-text="'Canvas ativo: ' + (imageStudioEngine?.designWidth || imageStudioCustomWidth) + '×' + (imageStudioEngine?.designHeight || imageStudioCustomHeight) + ' px · ' + imageStudioCanvasAspectLabel()"></p>
                    </div>
                    <div class="flex flex-wrap gap-2 items-center">
                        <button type="button" @click="openImageStudioDimensionsModal()" class="text-xs px-3 py-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 border border-zinc-600">📐 Medidas oficiais</button>
                        <span x-show="imageStudioSaving" class="text-[10px] text-zinc-500">Salvando…</span>
                        <button type="button" @click="saveImageStudioDesign()" class="text-xs px-3 py-1.5 rounded-lg bg-zinc-700 hover:bg-zinc-600">Salvar</button>
                    </div>
                </div>

                @include('projects.partials.image_studio_workspace')

                {{-- Modal medidas oficiais (só referência — você define o tamanho) --}}
                <div
                    x-show="imageStudioDimensionsModalOpen"
                    x-cloak
                    class="fixed inset-0 z-[280] flex items-center justify-center p-3 sm:p-6 bg-black/75"
                    @keydown.escape.window="closeImageStudioDimensionsModal()"
                >
                    <div
                        class="w-full max-w-3xl max-h-[88vh] flex flex-col rounded-2xl border border-zinc-700 bg-zinc-950 shadow-2xl overflow-hidden"
                        @click.outside="closeImageStudioDimensionsModal()"
                    >
                        <div class="flex items-start justify-between gap-3 px-4 py-3 border-b border-zinc-800 bg-zinc-900/80">
                            <div>
                                <h3 class="text-sm font-semibold text-violet-100">Medidas oficiais das plataformas</h3>
                                <p class="text-[11px] text-zinc-500 mt-0.5">Consulta apenas. Clique em <strong>Usar</strong> para copiar largura×altura para o seu canvas.</p>
                            </div>
                            <button type="button" @click="closeImageStudioDimensionsModal()" class="text-zinc-400 hover:text-white text-xl leading-none px-2">×</button>
                        </div>
                        <div class="px-4 py-3 border-b border-zinc-800">
                            <input type="search" x-model="imageStudioDimensionsFilter" placeholder="Buscar — YouTube, 1920, 16:9…" class="w-full text-sm px-3 py-2 rounded-lg bg-zinc-900 border border-zinc-700">
                        </div>
                        <div class="flex-1 overflow-y-auto p-4 space-y-4 min-h-[40vh]">
                            <template x-for="(presets, groupName) in imageStudioReferencePresetGroups()" :key="'isdim-' + groupName">
                                <div>
                                    <p class="text-[10px] uppercase tracking-wide text-zinc-500 mb-2" x-text="groupName"></p>
                                    <div class="rounded-lg border border-zinc-800 overflow-hidden">
                                        <table class="w-full text-xs">
                                            <thead class="bg-zinc-900/80 text-zinc-500">
                                                <tr>
                                                    <th class="text-left px-3 py-2 font-medium">Formato</th>
                                                    <th class="text-left px-3 py-2 font-medium">Proporção</th>
                                                    <th class="text-right px-3 py-2 font-medium tabular-nums">Largura</th>
                                                    <th class="text-right px-3 py-2 font-medium tabular-nums">Altura</th>
                                                    <th class="text-right px-3 py-2 font-medium"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="preset in presets" :key="'isdimrow-' + preset.slug">
                                                    <tr class="border-t border-zinc-800/80 hover:bg-zinc-900/50">
                                                        <td class="px-3 py-2 text-zinc-200" x-text="preset.name"></td>
                                                        <td class="px-3 py-2 text-zinc-400 tabular-nums" x-text="preset.aspect || '—'"></td>
                                                        <td class="px-3 py-2 text-right text-zinc-300 tabular-nums" x-text="preset.width"></td>
                                                        <td class="px-3 py-2 text-right text-zinc-300 tabular-nums" x-text="preset.height"></td>
                                                        <td class="px-3 py-2 text-right">
                                                            <button type="button" @click="pickImageStudioReferenceDimensions(preset)" class="text-[10px] px-2 py-1 rounded bg-violet-800 hover:bg-violet-700 text-white">Usar</button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>
                            <p x-show="!Object.keys(imageStudioReferencePresetGroups()).length" class="text-sm text-zinc-500 text-center py-8">Nenhuma medida encontrada.</p>
                        </div>
                    </div>
                </div>

                {{-- Modal Elementos --}}
                <div
                    x-show="imageStudioElementsModalOpen"
                    x-cloak
                    class="fixed inset-0 z-[280] flex items-center justify-center p-3 sm:p-6 bg-black/75"
                    @keydown.escape.window="imageStudioCloseElementsModal()"
                >
                    <div
                        class="w-full max-w-4xl max-h-[88vh] flex flex-col rounded-2xl border border-zinc-700 bg-zinc-950 shadow-2xl overflow-hidden"
                        @click.outside="imageStudioCloseElementsModal()"
                    >
                        <div class="flex items-start justify-between gap-3 px-4 py-3 border-b border-zinc-800 bg-zinc-900/80">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-violet-100">Elementos, ícones & stickers</h3>
                                <p class="text-[11px] text-zinc-500 mt-0.5">
                                    <span x-text="imageStudioElementsFilteredCount()"></span> de <span x-text="imageStudioElements.length"></span> — clique para inserir no canvas
                                </p>
                            </div>
                            <button type="button" @click="imageStudioCloseElementsModal()" class="text-zinc-400 hover:text-white text-xl leading-none px-2" title="Fechar (Esc)">×</button>
                        </div>
                        <div class="px-4 py-3 border-b border-zinc-800 space-y-2">
                            <input
                                type="search"
                                x-model="imageStudioElementFilter"
                                placeholder="Buscar emoji, ícone, forma, slime…"
                                class="w-full text-sm px-3 py-2 rounded-lg bg-zinc-900 border border-zinc-700 focus:border-violet-500 outline-none"
                            >
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="chip in imageStudioElementQuickGroups()" :key="'elchip-' + (chip.id || 'all')">
                                    <button
                                        type="button"
                                        @click="imageStudioElementFilterGroup = chip.id"
                                        class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                        :class="imageStudioElementFilterGroup === chip.id ? 'border-violet-500 bg-violet-900/50 text-violet-100' : 'border-zinc-700 text-zinc-400 hover:border-zinc-500'"
                                        x-text="chip.label"
                                    ></button>
                                </template>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto p-4 min-h-[50vh]">
                            <p x-show="imageStudioElements.length && !imageStudioElementsFilteredCount()" class="text-sm text-amber-400 mb-3">Nada encontrado — limpe a busca ou clique em <strong>Todos</strong>.</p>
                            <div class="grid grid-cols-5 sm:grid-cols-6 md:grid-cols-8 gap-2">
                                <template x-for="el in filterImageStudioElements()" :key="el.slug">
                                    <button
                                        type="button"
                                        @click="imageStudioAddElementFromModal(el)"
                                        class="aspect-square rounded-lg border border-zinc-800 hover:border-violet-500 hover:bg-violet-950/30 flex flex-col items-center justify-center p-1.5 gap-0.5 transition"
                                        :class="el.type === 'emoji' ? 'bg-zinc-950' : 'bg-zinc-900/40'"
                                        :title="el.name"
                                    >
                                        <img x-show="el.type === 'svg_icon' && el.icon_url" :src="el.icon_url" alt="" class="w-6 h-6 invert opacity-90 pointer-events-none">
                                        <span x-show="el.type === 'icon_glyph'" class="text-2xl leading-none select-none pointer-events-none" :class="imageStudioIconGlyphClass(el.font)" x-text="el.char"></span>
                                        <span x-show="el.type === 'emoji' || el.type === 'sticker'" class="text-3xl leading-none select-none pointer-events-none" x-text="el.char || el.icon"></span>
                                        <span x-show="el.type === 'blob'" class="w-8 h-8 rounded-full pointer-events-none shadow-inner" :style="'background:' + (el.fill || '#4ade80')"></span>
                                        <span x-show="el.type !== 'svg_icon' && el.type !== 'emoji' && el.type !== 'sticker' && el.type !== 'blob' && el.type !== 'icon_glyph'" class="text-xl leading-none select-none pointer-events-none" :style="el.fill ? 'color:' + el.fill : ''" x-text="el.icon || '•'"></span>
                                        <span class="text-[8px] text-zinc-500 truncate w-full text-center pointer-events-none" x-text="el.name"></span>
                                    </button>
                                </template>
                            </div>
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
                <div x-show="deployment.is_online" class="rounded-lg border border-amber-800/50 bg-amber-950/20 p-4 space-y-3">
                    <h3 class="text-sm font-medium text-amber-200">Finalizar projeto (modo online)</h3>
                    <ol class="text-xs text-zinc-400 space-y-1 list-decimal list-inside">
                        <li>Gere o <strong class="text-zinc-300">Publish Kit</strong> ou <strong class="text-zinc-300">Bundle completo</strong> e baixe os arquivos</li>
                        <li>Marque como exportado quando tiver tudo salvo no seu computador</li>
                        <li>Exclua o projeto no dashboard para liberar a criação/importação do próximo</li>
                    </ol>
                    <div class="flex flex-wrap gap-2">
                        <button @click="exportPublishKit()" class="px-3 py-1.5 rounded-lg bg-amber-700 hover:bg-amber-600 text-xs text-white">Gerar Publish Kit</button>
                        <button @click="exportBundle()" class="px-3 py-1.5 rounded-lg bg-sky-700 hover:bg-sky-600 text-xs text-white">Bundle completo</button>
                        <button
                            x-show="projectStatus !== 'exported'"
                            @click="markProjectExported()"
                            class="px-3 py-1.5 rounded-lg bg-emerald-800 hover:bg-emerald-700 text-xs text-white"
                        >Marcar como exportado</button>
                        <span x-show="projectStatus === 'exported'" class="text-xs text-emerald-400 self-center">✓ Projeto marcado como exportado</span>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-zinc-300 mb-2">Render vídeo</h3>
                    <label class="flex items-center gap-2 text-sm text-zinc-400 mb-1">
                        <input type="checkbox" x-model="burnSubtitles" @change="previewShowSubtitles = burnSubtitles">
                        Queimar legendas no vídeo (burn-in via SRT)
                    </label>
                    <p class="text-[10px] text-zinc-600 mb-3">Use <strong class="text-zinc-500">CC Sem legenda</strong> na timeline para ver o vídeo limpo antes de exportar.</p>
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
                    <button @click="exportBundle()" class="px-4 py-2 rounded-lg bg-sky-700 hover:bg-sky-600 text-sm">Bundle completo (ZIP)</button>
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
                                @click="selectedPlatformDesc = key; syncPlatformDescDraft()"
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
                                <span x-show="platformDescriptions[selectedPlatformDesc].is_custom" class="text-violet-400 ml-2">· editado manualmente</span>
                            </p>
                            <textarea
                                x-model="platformDescDraft"
                                rows="12"
                                class="w-full rounded bg-zinc-900 border border-zinc-700 px-3 py-2 text-xs font-mono text-zinc-300"
                            ></textarea>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <button @click="saveCustomPlatformDescription()" type="button" class="text-xs px-2 py-1 rounded bg-violet-700 hover:bg-violet-600 text-white">Salvar descrição</button>
                                <button @click="resetCustomPlatformDescription()" type="button" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300">Restaurar automática</button>
                                <button @click="copyPlatformDescription()" type="button" class="text-xs px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300">Copiar</button>
                                <button @click="exportPublishKit()" type="button" class="text-xs px-2 py-1 rounded bg-emerald-800 hover:bg-emerald-700 text-white">Gerar Publish Kit</button>
                            </div>
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
