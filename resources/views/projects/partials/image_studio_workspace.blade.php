<div
    :class="imageStudioExpanded ? 'fixed inset-0 z-[260] flex items-center justify-center bg-black/90 p-[3vh_3vw]' : ''"
    @keydown.escape.window="imageStudioExpanded && !imageStudioElementsModalOpen && !imageStudioDimensionsModalOpen && closeImageStudioExpanded()"
>
    <div
        class="flex gap-3 min-h-0 w-full min-w-0"
        :class="imageStudioExpanded ? 'w-[94vw] h-[94vh] rounded-xl border border-zinc-700 bg-zinc-950 p-3 shadow-2xl overflow-hidden' : ''"
    >
        {{-- Barra lateral esquerda --}}
        <aside
            class="w-[268px] shrink-0 overflow-y-auto overscroll-contain space-y-3 pr-1 border-r border-zinc-800/80"
            :class="imageStudioExpanded ? 'max-h-full' : 'max-h-[min(82vh,920px)]'"
        >
            <div class="rounded-xl border border-zinc-800 bg-zinc-950/50 p-3 space-y-2">
                <p class="text-xs font-medium text-zinc-300">Ferramentas</p>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" @click="imageStudioUndo()" :disabled="!imageStudioCanUndo" class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 disabled:opacity-40" title="Ctrl+Z">↶ Desfazer</button>
                    <button type="button" @click="imageStudioRedo()" :disabled="!imageStudioCanRedo" class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 disabled:opacity-40" title="Ctrl+Y">↷ Refazer</button>
                    <button type="button" @click="imageStudioAddText()" class="text-[10px] px-2 py-1 rounded bg-violet-800 hover:bg-violet-700">+ Texto</button>
                    <button type="button" @click="imageStudioOpenElementsModal()" class="text-[10px] px-2 py-1 rounded bg-violet-900 hover:bg-violet-800 border border-violet-700">★ Elementos</button>
                    <label class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-zinc-700 cursor-pointer">
                        + Imagem
                        <input type="file" accept="image/*" @change="imageStudioUploadImage($event)" class="hidden">
                    </label>
                </div>
                <div class="flex flex-wrap gap-1.5 pt-1 border-t border-zinc-800">
                    <label class="text-[10px] px-2 py-1 rounded bg-emerald-900/60 hover:bg-emerald-800 cursor-pointer inline-flex items-center gap-1.5 min-w-[10rem]" :class="imageStudioBgRemoving ? 'opacity-70 pointer-events-none' : ''">
                        <span x-show="!imageStudioBgRemoving">✂ Remover fundo (arquivo)</span>
                        <span x-show="imageStudioBgRemoving" x-cloak class="inline-flex items-center gap-1.5 text-emerald-100">
                            <svg class="h-3 w-3 animate-spin shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processando…
                        </span>
                        <input type="file" accept="image/*" @change="imageStudioRemoveBackground($event)" class="hidden" :disabled="imageStudioBgRemoving">
                    </label>
                    <button type="button" @click="imageStudioRemoveBgFromSelection()" :disabled="imageStudioBgRemoving || imageStudioSelectedObject?.type !== 'image'" class="text-[10px] px-2 py-1 rounded bg-emerald-800 hover:bg-emerald-700 disabled:opacity-40 inline-flex items-center gap-1.5 min-w-[10rem] justify-center" title="Remove fundo da imagem selecionada">
                        <span x-show="!imageStudioBgRemoving">✂ Remover fundo da seleção</span>
                        <span x-show="imageStudioBgRemoving" x-cloak class="inline-flex items-center gap-1.5">
                            <svg class="h-3 w-3 animate-spin shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processando…
                        </span>
                    </button>
                </div>
                <div class="flex flex-wrap gap-1.5 pt-1 border-t border-zinc-800">
                    <label class="text-[10px] px-2 py-1 rounded bg-zinc-800 flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" x-model="imageStudioShowFormatGuides" @change="onImageStudioFormatGuidesChange()" class="rounded"> Sangrias
                    </label>
                    <label class="text-[10px] px-2 py-1 rounded bg-zinc-800 flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" x-model="imageStudioShowGrid" @change="onImageStudioGridChange()" class="rounded"> Grid
                    </label>
                    <label class="text-[10px] px-2 py-1 rounded bg-zinc-800 flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" x-model="imageStudioSnapGrid" @change="onImageStudioGridChange()" class="rounded"> Snap
                    </label>
                    <select x-model.number="imageStudioGridSize" @change="onImageStudioGridChange()" class="text-[10px] px-2 py-1 rounded bg-zinc-800 border border-zinc-700">
                        <option :value="10">10px</option>
                        <option :value="20">20px</option>
                        <option :value="40">40px</option>
                    </select>
                </div>
            </div>

            <div class="rounded-xl border border-violet-900/50 bg-violet-950/20 p-3 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-violet-200">Texto</p>
                    <button type="button" @click="imageStudioAddText()" class="text-[10px] px-2 py-1 rounded bg-violet-700 hover:bg-violet-600 text-white">+ Adicionar</button>
                </div>
                <p class="text-[9px] text-zinc-500">Itálico/negrito aplicam no texto <strong class="text-zinc-400">selecionado no canvas</strong>. Ícones estão em <button type="button" @click="imageStudioOpenElementsModal()" class="text-violet-400 hover:text-violet-200 underline">Elementos → Ícones</button>.</p>
                <label class="text-[10px] text-zinc-400 block">
                    Conteúdo
                    <textarea x-model="imageStudioTextContent" @input="imageStudioOnTextControlChange()" rows="2" class="w-full mt-1 text-xs px-2 py-1.5 rounded bg-zinc-900 border border-zinc-700 resize-y"></textarea>
                </label>
                <input type="search" x-model="imageStudioFontFilter" @input="imageStudioFilterFontList()" placeholder="Buscar fonte…" class="w-full text-xs px-2 py-1.5 rounded bg-zinc-900 border border-zinc-700">
                <p class="text-[10px] text-zinc-500 flex justify-between gap-2">
                    <span class="text-emerald-400">{{ count($imageStudioCatalog['fonts'] ?? []) }} fontes</span>
                    <span class="text-violet-400 truncate" x-text="imageStudioFontMap[imageStudioTextFontSlug]?.label || imageStudioTextFontSlug || '—'"></span>
                </p>
                <div x-ref="imageStudioFontList" class="max-h-52 overflow-y-auto rounded-lg border border-zinc-700 bg-zinc-900">
                    @forelse($imageStudioCatalog['fonts'] ?? [] as $font)
                        <button
                            type="button"
                            data-font-slug="{{ $font['slug'] }}"
                            data-font-label="{{ $font['label'] ?? $font['slug'] }}"
                            data-font-group="{{ $font['group_label'] ?? $font['group'] ?? '' }}"
                            @click="imageStudioSelectFont('{{ $font['slug'] }}')"
                            class="is-font-row w-full text-left text-xs px-2 py-1.5 border-b border-zinc-800/60 hover:bg-violet-950/40 transition flex items-center justify-between gap-2 text-zinc-300"
                            :class="imageStudioTextFontSlug === '{{ $font['slug'] }}' ? 'bg-violet-900/50 text-violet-100' : ''"
                        >
                            <span class="truncate">
                                {{ $font['label'] ?? $font['slug'] }}
                                <span class="text-[9px] text-zinc-500 ml-1">· {{ $font['group_label'] ?? $font['group'] ?? '' }}</span>
                            </span>
                            @php($src = $font['source'] ?? 'system')
                            <span class="text-[8px] shrink-0 px-1 rounded {{ $src === 'google' ? 'bg-emerald-900/60 text-emerald-300' : ($src === 'icon' ? 'bg-sky-900/60 text-sky-300' : 'bg-zinc-800 text-zinc-500') }}">
                                {{ $src === 'google' ? 'Google' : ($src === 'icon' ? 'Ícone' : 'Win') }}
                            </span>
                        </button>
                    @empty
                        <p class="text-[10px] text-red-400 p-3">Catálogo PHP vazio — rode <code class="text-red-300">php artisan config:clear</code></p>
                    @endforelse
                </div>
                <button type="button" @click="loadImageStudioCatalog()" class="text-[10px] text-violet-400 hover:text-violet-200 underline">Atualizar catálogo via API</button>
                <div class="flex flex-wrap gap-1">
                    <button type="button" @click="imageStudioToggleTextBold()" class="text-xs px-2 py-1 rounded border" :class="imageStudioTextBold ? 'bg-violet-700 border-violet-500 text-white' : 'bg-zinc-800 border-zinc-700 text-zinc-400'" title="Negrito"><strong>B</strong></button>
                    <button type="button" @click="imageStudioToggleTextItalic()" class="text-xs px-2 py-1 rounded border italic" :class="imageStudioTextItalic ? 'bg-violet-700 border-violet-500 text-white' : 'bg-zinc-800 border-zinc-700 text-zinc-400'" title="Itálico">I</button>
                    <button type="button" @click="imageStudioToggleTextUnderline()" class="text-xs px-2 py-1 rounded border underline" :class="imageStudioTextUnderline ? 'bg-violet-700 border-violet-500 text-white' : 'bg-zinc-800 border-zinc-700 text-zinc-400'" title="Sublinhado">U</button>
                    <button type="button" @click="imageStudioToggleTextLinethrough()" class="text-xs px-2 py-1 rounded border line-through" :class="imageStudioTextLinethrough ? 'bg-violet-700 border-violet-500 text-white' : 'bg-zinc-800 border-zinc-700 text-zinc-400'" title="Tachado">S</button>
                    <span class="w-px bg-zinc-700 mx-0.5"></span>
                    <button type="button" @click="imageStudioSetTextAlign('left')" class="text-xs px-2 py-1 rounded" :class="imageStudioTextAlign === 'left' ? 'bg-violet-700 text-white' : 'bg-zinc-800 text-zinc-400'">⬅</button>
                    <button type="button" @click="imageStudioSetTextAlign('center')" class="text-xs px-2 py-1 rounded" :class="imageStudioTextAlign === 'center' ? 'bg-violet-700 text-white' : 'bg-zinc-800 text-zinc-400'">↔</button>
                    <button type="button" @click="imageStudioSetTextAlign('right')" class="text-xs px-2 py-1 rounded" :class="imageStudioTextAlign === 'right' ? 'bg-violet-700 text-white' : 'bg-zinc-800 text-zinc-400'">➡</button>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-[10px] text-zinc-400 block">
                        Cor do texto
                        <input type="color" x-model="imageStudioTextFill" @input="imageStudioOnTextFillChange()" @change="imageStudioOnTextFillChange()" class="w-full mt-1 h-8 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                    </label>
                    <label class="text-[10px] text-zinc-400 block">
                        Cor do contorno
                        <input type="color" x-model="imageStudioTextStroke" @input="imageStudioOnTextStrokeChange()" @change="imageStudioOnTextStrokeChange()" class="w-full mt-1 h-8 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                        <span class="text-[9px] text-zinc-600" x-show="imageStudioTextStrokeWidth <= 0">Escolher cor ativa contorno fino (2px)</span>
                    </label>
                </div>
                <label class="text-[10px] text-zinc-400 block">
                    Tamanho <span class="text-zinc-500 tabular-nums" x-text="imageStudioTextSize + 'px'"></span>
                    <input type="range" min="12" max="320" step="1" x-model.number="imageStudioTextSize" @input="imageStudioOnTextControlChange()" class="w-full mt-1 accent-violet-500">
                </label>
                <div class="flex flex-wrap items-end gap-2">
                    <label class="text-[10px] text-zinc-400 block flex-1 min-w-[10rem]">
                        Espessura contorno
                        <span class="text-zinc-500 tabular-nums" x-text="imageStudioTextStrokeWidth <= 0 ? ' (sem contorno)' : ' (' + imageStudioTextStrokeWidth + 'px)'"></span>
                        <input type="range" min="0" max="24" step="1" x-model.number="imageStudioTextStrokeWidth" @input="imageStudioOnTextControlChange()" class="w-full mt-1 accent-violet-500">
                    </label>
                    <button type="button" @click="imageStudioRemoveTextOutline()" class="text-[10px] px-2 py-1.5 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300 shrink-0" title="Zera espessura e remove contorno">Sem contorno</button>
                </div>
                <label class="text-[10px] text-zinc-400 block">
                    Espaçamento letras
                    <input type="range" min="-50" max="400" step="5" x-model.number="imageStudioTextCharSpacing" @input="imageStudioOnTextControlChange()" class="w-full mt-1 accent-violet-500">
                </label>
                <label class="text-[10px] text-zinc-400 flex items-center gap-2">
                    <input type="checkbox" x-model="imageStudioTextShadow" @change="imageStudioOnTextControlChange()" class="rounded"> Sombra
                </label>
                <div x-show="imageStudioTextShadow" class="grid grid-cols-2 gap-2">
                    <input type="color" x-model="imageStudioTextShadowColor" @input="imageStudioOnTextControlChange()" class="w-full h-8 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                    <input type="range" min="0" max="40" x-model.number="imageStudioTextShadowBlur" @input="imageStudioOnTextControlChange()" class="w-full accent-violet-500" title="Desfoque sombra">
                </div>
            </div>

            <div class="rounded-xl border border-zinc-800 bg-zinc-950/50 p-3 space-y-2">
                <p class="text-xs font-medium text-zinc-300">Fundo do canvas</p>
                <label class="text-xs text-zinc-400 block">
                    Cor de fundo
                    <input type="color" x-model="imageStudioBgColor" @input="onImageStudioBgChange()" class="w-full mt-1 h-9 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                </label>
                <label class="text-xs text-zinc-400 block">
                    Transparência
                    <input type="range" min="0" max="100" x-model.number="imageStudioBgTransparency" @input="onImageStudioBgChange()" class="w-full mt-2">
                    <span class="text-[10px] text-zinc-500" x-text="imageStudioBgTransparency + '%'"></span>
                </label>
                <div class="pt-2 border-t border-zinc-800 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[10px] font-medium text-zinc-300">Slide por trás</p>
                        <label class="text-[10px] text-zinc-400 inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" x-model="imageStudioUnderlayEnabled" @change="onImageStudioUnderlayChange()" class="rounded">
                            Ativo
                        </label>
                    </div>
                    <label class="text-[10px] text-zinc-400 block" x-show="imageStudioUnderlayEnabled">
                        Slide visível
                        <select x-model.number="imageStudioUnderlaySlideIndex" @change="onImageStudioUnderlayChange()" class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-sm">
                            <option :value="-1">Slide selecionado no editor</option>
                            <template x-for="(slide, idx) in slides" :key="'is-underlay-' + slide.id">
                                <option :value="idx" x-text="'Slide ' + (idx + 1) + (slide.video_path ? ' (vídeo)' : slide.image_url ? ' (imagem)' : '')"></option>
                            </template>
                        </select>
                    </label>
                    <p x-show="imageStudioUnderlayEnabled && !imageStudioShowUnderlayMedia()" class="text-[10px] text-amber-500/90">Este slide não tem imagem ou vídeo.</p>
                </div>
            </div>

            @include('projects.partials.image_studio_sidebar_panels')
        </aside>

        {{-- Área principal: toolbar mínima + canvas --}}
        <div class="flex-1 min-w-0 flex flex-col gap-2 min-h-0">
            <div class="flex flex-wrap items-end gap-2 shrink-0 rounded-xl border border-zinc-800 bg-zinc-950/60 px-3 py-2">
                <div class="rounded-lg border border-violet-700/50 bg-violet-950/25 px-3 py-2 flex flex-wrap items-end gap-2 flex-1 min-w-[16rem]">
                    <p class="text-[10px] text-violet-300 w-full font-medium">Dimensões (px)</p>
                    <label class="text-[10px] text-zinc-400">
                        Largura
                        <input type="number" min="100" max="8000" step="1" x-model.number="imageStudioCustomWidth" class="block w-24 mt-0.5 text-sm px-2 py-1 rounded bg-zinc-900 border border-zinc-700 tabular-nums">
                    </label>
                    <label class="text-[10px] text-zinc-400">
                        Altura
                        <input type="number" min="100" max="8000" step="1" x-model.number="imageStudioCustomHeight" class="block w-24 mt-0.5 text-sm px-2 py-1 rounded bg-zinc-900 border border-zinc-700 tabular-nums">
                    </label>
                    <button type="button" @click="applyImageStudioCustomDimensions()" class="text-xs px-3 py-1.5 rounded-lg bg-violet-700 hover:bg-violet-600 text-white">Aplicar</button>
                    <span class="text-[10px] text-zinc-500 tabular-nums" x-text="imageStudioCanvasAspectLabel()"></span>
                </div>
                <div class="flex items-center gap-1.5 border border-zinc-700 rounded-lg px-2 py-1 bg-zinc-900/80">
                    <button type="button" @click="imageStudioZoomOut()" class="text-xs px-1.5 py-0.5 rounded hover:bg-zinc-700" title="Diminuir zoom">−</button>
                    <input type="range" min="8" max="400" step="1" x-model.number="imageStudioZoom" @input="imageStudioSetZoomPercent(imageStudioZoom)" class="w-24 accent-violet-500">
                    <button type="button" @click="imageStudioZoomIn()" class="text-xs px-1.5 py-0.5 rounded hover:bg-zinc-700" title="Aumentar zoom">+</button>
                    <span class="text-[10px] text-zinc-400 w-9 text-center" x-text="imageStudioZoom + '%'"></span>
                    <button type="button" @click="imageStudioZoomReset()" class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800 hover:bg-zinc-700">100%</button>
                    <button type="button" @click="fitImageStudioCanvas()" class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800 hover:bg-zinc-700">Ajustar</button>
                </div>
                <button
                    type="button"
                    @click="toggleImageStudioExpanded()"
                    class="text-xs px-3 py-1.5 rounded-lg border shrink-0"
                    :class="imageStudioExpanded ? 'border-amber-600 bg-amber-950/50 text-amber-200 hover:bg-amber-900/50' : 'border-zinc-600 bg-zinc-800 hover:bg-zinc-700 text-zinc-200'"
                    :title="imageStudioExpanded ? 'Recolher (Esc)' : 'Expandir para 94% da tela'"
                >
                    <span x-text="imageStudioExpanded ? '⤡ Recolher' : '⤢ Expandir'"></span>
                </button>
            </div>

            <div
                class="rounded-xl border border-zinc-700 bg-zinc-950 p-4 overflow-auto min-h-[320px] flex justify-center items-start relative flex-1 min-h-0"
                :class="imageStudioExpanded ? 'max-h-none' : 'max-h-[min(85vh,920px)]'"
                x-ref="imageStudioCanvasWrap"
            >
                <div
                    x-show="imageStudioBgRemoving"
                    x-cloak
                    class="absolute inset-0 z-50 flex items-center justify-center rounded-xl bg-zinc-950/85 backdrop-blur-[2px]"
                    role="status"
                    aria-live="polite"
                    aria-busy="true"
                >
                    <div class="flex flex-col items-center gap-3 px-6 py-5 rounded-xl border border-emerald-700/50 bg-zinc-900/95 shadow-xl max-w-xs text-center">
                        <svg class="h-11 w-11 animate-spin text-emerald-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-95" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-white">Removendo fundo com IA…</p>
                            <p class="text-[11px] text-zinc-400 mt-1">Isso costuma levar de 2 a 5 segundos. Não feche esta aba.</p>
                        </div>
                    </div>
                </div>
                <div class="inline-block shrink-0" :style="imageStudioCanvasViewportStyle()">
                    <div
                        x-ref="imageStudioCanvasScaler"
                        class="relative shadow-2xl shadow-black/40 ring-2 ring-violet-500/40 inline-block"
                        :style="imageStudioCanvasScalerStyle()"
                    >
                        <img
                            x-show="imageStudioUnderlayEnabled && getImageStudioUnderlayImageUrl()"
                            x-cloak
                            :src="getImageStudioUnderlayImageUrl()"
                            alt=""
                            class="absolute inset-0 w-full h-full object-cover pointer-events-none select-none"
                        >
                        <video
                            x-show="imageStudioUnderlayEnabled && getImageStudioUnderlayVideoUrl()"
                            x-cloak
                            :src="getImageStudioUnderlayVideoUrl()"
                            muted
                            playsinline
                            preload="metadata"
                            class="absolute inset-0 w-full h-full object-cover pointer-events-none select-none"
                            @loadeddata="$event.target.currentTime = Math.min(0.5, ($event.target.duration || 1) * 0.05)"
                        ></video>
                        <canvas x-ref="imageStudioCanvas" class="relative block"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
