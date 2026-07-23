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
    <div class="space-y-2 pb-2 border-b border-zinc-800">
        <p class="text-[10px] text-violet-400 font-medium">Tamanho / escala</p>
        <div class="flex items-center gap-1">
            <button type="button" @click="imageStudioNudgeObjectScale(-10)" class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-violet-800" title="Diminuir 10%">−10%</button>
            <button type="button" @click="imageStudioNudgeObjectScale(-5)" class="text-[10px] px-1.5 py-1 rounded bg-zinc-800 hover:bg-zinc-700" title="Diminuir 5%">−5%</button>
            <button type="button" @click="imageStudioSetObjectScale(100)" class="text-[10px] px-1.5 py-1 rounded bg-zinc-800 hover:bg-zinc-700" title="Resetar escala">100%</button>
            <button type="button" @click="imageStudioNudgeObjectScale(5)" class="text-[10px] px-1.5 py-1 rounded bg-zinc-800 hover:bg-violet-700" title="Aumentar 5%">+5%</button>
            <button type="button" @click="imageStudioNudgeObjectScale(10)" class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-violet-800" title="Aumentar 10%">+10%</button>
        </div>
        <label class="text-[10px] text-zinc-400 block">
            Escala
            <input type="range" min="5" max="600" step="1" x-model.number="imageStudioObjectScale" @input="imageStudioSetObjectScale(imageStudioObjectScale)" class="w-full mt-1 accent-violet-500">
        </label>
        <p class="text-[10px] text-zinc-500"><span x-text="imageStudioObjectScale"></span>% — arraste os cantos violetas ou use os botões</p>
    </div>
    <div class="space-y-2 pb-2 border-b border-zinc-800">
        <p class="text-[10px] text-violet-400 font-medium">Rotação</p>
        <div class="flex items-center gap-1 flex-wrap">
            <button type="button" @click="imageStudioNudgeObjectAngle(-90)" class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-violet-800" title="Girar -90°">↺ 90°</button>
            <button type="button" @click="imageStudioNudgeObjectAngle(-15)" class="text-[10px] px-1.5 py-1 rounded bg-zinc-800 hover:bg-violet-700" title="Girar -15°">−15°</button>
            <button type="button" @click="imageStudioSetObjectAngle(0)" class="text-[10px] px-1.5 py-1 rounded bg-zinc-800 hover:bg-zinc-700" title="Resetar rotação">0°</button>
            <button type="button" @click="imageStudioNudgeObjectAngle(15)" class="text-[10px] px-1.5 py-1 rounded bg-zinc-800 hover:bg-violet-700" title="Girar +15°">+15°</button>
            <button type="button" @click="imageStudioNudgeObjectAngle(90)" class="text-[10px] px-2 py-1 rounded bg-zinc-800 hover:bg-violet-800" title="Girar +90°">↻ 90°</button>
        </div>
        <label class="text-[10px] text-zinc-400 block">
            Ângulo
            <input type="range" min="0" max="359" step="1" x-model.number="imageStudioObjectAngle" @input="imageStudioSetObjectAngle(imageStudioObjectAngle)" class="w-full mt-1 accent-violet-500">
        </label>
        <p class="text-[10px] text-zinc-500"><span x-text="imageStudioObjectAngle"></span>° — arraste o círculo violeta acima do objeto ou use <kbd class="px-1 rounded bg-zinc-800">[</kbd> <kbd class="px-1 rounded bg-zinc-800">]</kbd></p>
    </div>
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
    <template x-if="imageStudioSelectedObject && imageStudioSelectedObject.type !== 'text' && imageStudioSelectedObject.type !== 'image'">
        <div class="space-y-2 pt-2 border-t border-zinc-800">
            <p class="text-[10px] text-violet-400 font-medium">Cor & contorno</p>
            <div class="grid grid-cols-2 gap-2" x-show="!imageStudioShapeIsLine">
                <label class="text-[10px] text-zinc-400 block">
                    Preenchimento
                    <input type="color" x-model="imageStudioShapeFill" @input="imageStudioOnShapeFillChange()" class="w-full mt-1 h-8 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
                </label>
                <div class="flex flex-col justify-end">
                    <button type="button" @click="imageStudioClearShapeFill()" class="text-[10px] px-2 py-1.5 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300">Sem preenchimento</button>
                </div>
            </div>
            <label class="text-[10px] text-zinc-400 block">
                Cor do contorno
                <input type="color" x-model="imageStudioShapeStroke" @input="imageStudioOnShapeStrokeChange()" class="w-full mt-1 h-8 rounded bg-zinc-800 border border-zinc-700 cursor-pointer">
            </label>
            <label class="text-[10px] text-zinc-400 block">
                Espessura do contorno
                <input type="range" min="0" max="80" step="1" x-model.number="imageStudioShapeStrokeWidth" @input="imageStudioOnShapeStrokeWidthChange()" class="w-full mt-1 accent-violet-500">
            </label>
        </div>
    </template>
    <template x-if="imageStudioSelectedObject?.type === 'text'">
        <p class="text-[10px] text-violet-400 pt-1">Texto selecionado — ajuste fonte/cor na barra lateral ou duplo-clique no canvas.</p>
    </template>
    <template x-if="imageStudioSelectedObject?.type === 'image'">
        <div class="space-y-2 pt-2 border-t border-zinc-800">
            <button type="button" @click="imageStudioRemoveBgFromSelection()" :disabled="imageStudioBgRemoving" class="w-full text-[10px] py-1.5 rounded bg-emerald-900/60 hover:bg-emerald-800 disabled:opacity-40 inline-flex items-center justify-center gap-1.5">
                <span x-show="!imageStudioBgRemoving">✂ Remover fundo desta imagem</span>
                <span x-show="imageStudioBgRemoving" x-cloak class="inline-flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 animate-spin shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processando…
                </span>
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
    <button type="button" @click="imageStudioPushThumbnail()" class="w-full text-xs py-2 rounded-lg bg-violet-800 hover:bg-violet-700">Enviar para Thumbnail (plataforma compatível)</button>
    <button type="button" @click="imageStudioPushLibrary()" class="w-full text-xs py-2 rounded-lg bg-emerald-800 hover:bg-emerald-700">Salvar na biblioteca do projeto</button>
</div>

<div x-show="typeof window !== 'undefined' && window.criasys?.isDesktop" class="rounded-xl border border-sky-900/40 bg-sky-950/20 p-3 space-y-2">
    <p class="text-xs font-medium text-sky-200">Pasta local (Electron)</p>
    <button type="button" @click="imageStudioPickLocalFolder()" class="w-full text-xs py-2 rounded-lg bg-sky-800 hover:bg-sky-700">Monitorar pasta de imagens</button>
    <p x-show="imageStudioLocalWatch" class="text-[9px] text-zinc-500 break-all" x-text="imageStudioLocalWatch"></p>
</div>
