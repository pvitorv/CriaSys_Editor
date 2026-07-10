import { Canvas, FabricText, IText, FabricImage, Rect, Circle, Ellipse, Line, loadSVGFromURL, util, filters, Shadow } from 'fabric';
import { writePsdBuffer } from 'ag-psd';
import { jsPDF } from 'jspdf';
import {
    buildTextStyleFromFont,
    ensureFontLoaded,
    findFontBySlug,
    resolveFontWeight,
    fontCssFamily,
    isTextLikeType,
    preloadIconFontCdns,
    preloadStarterGoogleFonts,
    FALLBACK_FONTS,
} from './imageStudioTextFonts';

function normalizeFabricType(obj) {
    const t = String(obj?.type || '').toLowerCase();
    if (t === 'image') return 'image';
    if (isTextLikeType(t)) return 'text';
    return t;
}

function isFabricText(obj) {
    return !!obj && (obj instanceof IText || obj instanceof FabricText || isTextLikeType(obj?.type));
}

function isFabricImage(obj) {
    return !!obj && (obj instanceof FabricImage || normalizeFabricType(obj) === 'image');
}

const DEFAULT_FILTER_STATE = {
    brightness: 50,
    contrast: 50,
    saturation: 50,
    blur: 0,
    grayscale: 0,
    vignette: 0,
};

function loadHtmlImage(url) {
    return new Promise((resolve, reject) => {
        const el = new Image();
        el.onload = () => resolve(el);
        el.onerror = () => reject(new Error('Navegador não conseguiu decodificar a imagem'));
        el.src = url;
    });
}

async function compositeFrameOnCanvasDataUrl(canvas, frameUrl, frameVisible = true) {
    const w = canvas.getWidth();
    const h = canvas.getHeight();
    const baseUrl = canvas.toDataURL({ format: 'png', multiplier: 1 });
    if (!frameUrl || frameVisible === false) {
        return baseUrl;
    }
    const off = document.createElement('canvas');
    off.width = w;
    off.height = h;
    const ctx = off.getContext('2d');
    const base = await loadHtmlImage(baseUrl);
    ctx.drawImage(base, 0, 0, w, h);
    const frame = await loadHtmlImage(frameUrl);
    ctx.drawImage(frame, 0, 0, w, h);
    return off.toDataURL('image/png');
}

export class ImageStudioEngine {
    static readEmbeddedJson(elementId) {
        const el = document.getElementById(elementId);
        if (!el?.textContent?.trim()) {
            return null;
        }
        try {
            return JSON.parse(el.textContent);
        } catch {
            return null;
        }
    }

    constructor(canvasEl) {
        this.canvasEl = canvasEl;
        this.canvas = null;
        this.onChange = null;
        this.history = [];
        this.historyIndex = -1;
        this.historyPaused = false;
        this.historyTimeout = null;
        this.maxHistory = 40;
        this.showGrid = false;
        this.snapToGrid = false;
        this.gridSize = 20;
        this.designWidth = 1080;
        this.designHeight = 1080;
        this.viewportZoom = 1;
        this.scaleWrapper = null;
        this.showFormatGuides = true;
    }

    setScaleWrapper(el) {
        this.scaleWrapper = el || null;
        this.applyViewportZoom(this.viewportZoom || 1);
    }

    configureSelectableObject(obj) {
        if (!obj || obj.criasysGuide) {
            return;
        }
        const z = Math.max(0.08, this.viewportZoom || 1);
        const cornerSize = Math.min(48, Math.max(14, Math.round(14 / z)));
        obj.set({
            cornerStyle: 'circle',
            cornerColor: '#a78bfa',
            cornerStrokeColor: '#ffffff',
            borderColor: '#a78bfa',
            cornerSize,
            padding: 6,
            transparentCorners: false,
            hasControls: true,
            hasBorders: true,
            lockScalingFlip: false,
            lockRotation: false,
            lockScalingX: false,
            lockScalingY: false,
            selectable: true,
            evented: true,
        });
        if (typeof obj.setControlsVisibility === 'function') {
            obj.setControlsVisibility({
                tl: true, tr: true, bl: true, br: true,
                ml: true, mt: true, mr: true, mb: true, mtr: true,
            });
        }
        if (isFabricImage(obj)) {
            obj.set({
                objectCaching: false,
                lockScalingX: false,
                lockScalingY: false,
            });
        }
    }

    configureAllObjects() {
        this.canvas?.getObjects().forEach((obj) => this.configureSelectableObject(obj));
    }

    init(width, height, backgroundColor = '#ffffff') {
        if (this.canvas) {
            this.canvas.dispose();
        }
        this.designWidth = width;
        this.designHeight = height;
        this.viewportZoom = 1;
        this.canvas = new Canvas(this.canvasEl, {
            width,
            height,
            backgroundColor,
            preserveObjectStacking: true,
            selection: true,
            enableRetinaScaling: false,
            uniformScaling: false,
            stopContextMenu: true,
        });
        this.canvas.on('object:scaling', () => {
            this.canvas?.requestRenderAll();
            this.notifyChange();
        });
        this.canvas.on('object:modified', () => this.emitChange());
        this.canvas.on('object:added', (e) => {
            if (e.target) {
                this.configureSelectableObject(e.target);
            }
            this.emitChange();
        });
        this.canvas.on('object:removed', () => this.emitChange());
        this.canvas.on('selection:created', () => this.notifyChange());
        this.canvas.on('selection:updated', () => this.notifyChange());
        this.canvas.on('selection:cleared', () => this.notifyChange());
        this.canvas.on('object:moving', (e) => this.handleObjectMoving(e));
        this.canvas.on('text:changed', () => this.emitChange(false));
        this.canvas.on('mouse:down', () => this.canvas?.calcOffset());
        this.canvas.on('after:render', () => {
            this.drawGridOverlay();
            this.drawFormatGuidesOverlay();
        });
        this.applyViewportZoom(1);
        this.history = [];
        this.historyIndex = -1;
        return this.canvas;
    }

    notifyChange() {
        if (typeof this.onChange === 'function') {
            this.onChange();
        }
    }

    emitChange(recordHistory = true) {
        if (recordHistory && !this.historyPaused) {
            this.scheduleHistory();
        }
        this.notifyChange();
    }

    scheduleHistory() {
        clearTimeout(this.historyTimeout);
        this.historyTimeout = setTimeout(() => this.pushHistory(), 350);
    }

    pushHistory() {
        if (!this.canvas || this.historyPaused) {
            return;
        }
        const json = JSON.stringify(this.canvas.toJSON());
        if (this.historyIndex >= 0 && this.history[this.historyIndex] === json) {
            return;
        }
        this.history = this.history.slice(0, this.historyIndex + 1);
        this.history.push(json);
        if (this.history.length > this.maxHistory) {
            this.history.shift();
        } else {
            this.historyIndex += 1;
        }
    }

    async undo() {
        if (this.historyIndex <= 0 || !this.canvas) {
            return false;
        }
        this.historyIndex -= 1;
        await this.restoreHistoryState(this.history[this.historyIndex]);
        return true;
    }

    async redo() {
        if (this.historyIndex >= this.history.length - 1 || !this.canvas) {
            return false;
        }
        this.historyIndex += 1;
        await this.restoreHistoryState(this.history[this.historyIndex]);
        return true;
    }

    canUndo() {
        return this.historyIndex > 0;
    }

    canRedo() {
        return this.historyIndex < this.history.length - 1;
    }

    async restoreHistoryState(jsonStr) {
        this.historyPaused = true;
        await this.canvas.loadFromJSON(JSON.parse(jsonStr));
        this.canvas.getObjects().forEach((obj) => {
            this.configureSelectableObject(obj);
            if (obj.criasysFilters && isFabricImage(obj)) {
                this.applyFiltersToObject(obj, obj.criasysFilters);
            }
        });
        this.canvas.requestRenderAll();
        this.historyPaused = false;
        this.notifyChange();
    }

    setGridOptions({ showGrid, snapToGrid, gridSize } = {}) {
        if (showGrid !== undefined) {
            this.showGrid = !!showGrid;
        }
        if (snapToGrid !== undefined) {
            this.snapToGrid = !!snapToGrid;
        }
        if (gridSize !== undefined) {
            this.gridSize = Math.max(5, Math.min(100, Number(gridSize) || 20));
        }
        this.canvas?.requestRenderAll();
    }

    handleObjectMoving(e) {
        if (!this.snapToGrid || !e.target) {
            return;
        }
        const g = this.gridSize;
        e.target.set({
            left: Math.round(e.target.left / g) * g,
            top: Math.round(e.target.top / g) * g,
        });
    }

    drawGridOverlay() {
        if (!this.canvas || !this.showGrid) {
            return;
        }
        const ctx = this.canvas.contextTop;
        if (!ctx) {
            return;
        }
        const w = this.canvas.getWidth();
        const h = this.canvas.getHeight();
        const g = this.gridSize;
        const zoom = this.canvas.getZoom();
        ctx.save();
        ctx.strokeStyle = 'rgba(139, 92, 246, 0.25)';
        ctx.lineWidth = 1 / zoom;
        for (let x = 0; x <= w; x += g) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, h);
            ctx.stroke();
        }
        for (let y = 0; y <= h; y += g) {
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(w, y);
            ctx.stroke();
        }
        ctx.restore();
    }

    drawFormatGuidesOverlay() {
        if (!this.canvas || !this.showFormatGuides) {
            return;
        }
        const ctx = this.canvas.contextTop;
        if (!ctx) {
            return;
        }
        const w = this.designWidth || this.canvas.getWidth();
        const h = this.designHeight || this.canvas.getHeight();
        const zoom = this.canvas.getZoom() || 1;
        ctx.save();
        ctx.lineWidth = 2 / zoom;
        ctx.strokeStyle = '#8b5cf6';
        ctx.strokeRect(1, 1, w - 2, h - 2);
        const margin = Math.max(24, Math.round(Math.min(w, h) * 0.05));
        ctx.setLineDash([8 / zoom, 6 / zoom]);
        ctx.strokeStyle = 'rgba(251, 191, 36, 0.85)';
        ctx.strokeRect(margin, margin, w - margin * 2, h - margin * 2);
        ctx.setLineDash([]);
        if (h >= w * 1.4) {
            const safe = Math.min(250, Math.round(h * 0.12));
            ctx.strokeStyle = 'rgba(239, 68, 68, 0.55)';
            ctx.lineWidth = 1 / zoom;
            ctx.beginPath();
            ctx.moveTo(0, safe);
            ctx.lineTo(w, safe);
            ctx.moveTo(0, h - safe);
            ctx.lineTo(w, h - safe);
            ctx.stroke();
        }
        ctx.fillStyle = 'rgba(167, 139, 250, 0.9)';
        ctx.font = `${Math.max(10, Math.round(11 / zoom))}px sans-serif`;
        ctx.fillText(`${w} × ${h}px`, 8, h - 8);
        ctx.restore();
    }

    setFormatGuidesVisible(visible) {
        this.showFormatGuides = !!visible;
        this.canvas?.requestRenderAll();
    }

    applyViewportZoom(zoom) {
        if (!this.canvas) {
            return 1;
        }
        const z = Math.max(0.08, Math.min(4, zoom));
        this.viewportZoom = z;
        this.canvas.setZoom(1);
        this.canvas.setDimensions({ width: this.designWidth, height: this.designHeight });
        if (this.scaleWrapper) {
            this.scaleWrapper.style.width = `${this.designWidth}px`;
            this.scaleWrapper.style.height = `${this.designHeight}px`;
            this.scaleWrapper.style.transform = `scale(${z})`;
            this.scaleWrapper.style.transformOrigin = 'top center';
        }
        requestAnimationFrame(() => {
            this.configureAllObjects();
            this.canvas?.calcOffset();
            this.canvas?.requestRenderAll();
        });
        return z;
    }

    getActiveObjectScalePercent() {
        const obj = this.canvas?.getActiveObject();
        if (!obj) {
            return 100;
        }
        const sx = Math.abs(obj.scaleX ?? 1);
        const sy = Math.abs(obj.scaleY ?? 1);
        return Math.round(((sx + sy) / 2) * 100);
    }

    setActiveObjectScalePercent(percent) {
        const obj = this.canvas?.getActiveObject();
        if (!obj || obj.criasysGuide) {
            return;
        }
        const sx = Math.abs(obj.scaleX ?? 1) || 1;
        const sy = Math.abs(obj.scaleY ?? 1) || 1;
        const ratio = sy / sx;
        const p = Math.max(5, Math.min(400, Number(percent) || 100)) / 100;
        const signX = (obj.scaleX ?? 1) < 0 ? -1 : 1;
        const signY = (obj.scaleY ?? 1) < 0 ? -1 : 1;
        if (Math.abs(ratio - 1) < 0.02) {
            obj.set({ scaleX: signX * p, scaleY: signY * p });
        } else {
            obj.set({ scaleX: signX * p, scaleY: signY * p * ratio });
        }
        obj.setCoords();
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    nudgeActiveObjectScale(deltaPercent) {
        this.setActiveObjectScalePercent(this.getActiveObjectScalePercent() + deltaPercent);
    }

    alignActiveObject(mode) {
        const obj = this.canvas?.getActiveObject();
        if (!obj) {
            return;
        }
        const w = this.canvas.getWidth();
        const h = this.canvas.getHeight();
        const bounds = obj.getBoundingRect();
        if (mode === 'left') {
            obj.set('left', obj.left - bounds.left);
        } else if (mode === 'center-h') {
            obj.set('left', obj.left + (w / 2 - (bounds.left + bounds.width / 2)));
        } else if (mode === 'right') {
            obj.set('left', obj.left + (w - (bounds.left + bounds.width)));
        } else if (mode === 'top') {
            obj.set('top', obj.top - bounds.top);
        } else if (mode === 'center-v') {
            obj.set('top', obj.top + (h / 2 - (bounds.top + bounds.height / 2)));
        } else if (mode === 'bottom') {
            obj.set('top', obj.top + (h - (bounds.top + bounds.height)));
        }
        obj.setCoords();
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    setSize(width, height, backgroundColor) {
        if (!this.canvas) {
            return this.init(width, height, backgroundColor);
        }
        this.designWidth = width;
        this.designHeight = height;
        this.canvas.setDimensions({ width, height });
        if (backgroundColor) {
            this.canvas.backgroundColor = backgroundColor;
        }
        this.applyViewportZoom(this.viewportZoom || 1);
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    setBackgroundColor(color, opacity = 100) {
        if (!this.canvas) {
            return;
        }
        if (opacity <= 0) {
            this.canvas.backgroundColor = 'transparent';
        } else if (opacity >= 100) {
            this.canvas.backgroundColor = color;
        } else {
            const hex = color.replace('#', '');
            const r = parseInt(hex.substring(0, 2), 16);
            const g = parseInt(hex.substring(2, 4), 16);
            const b = parseInt(hex.substring(4, 6), 16);
            this.canvas.backgroundColor = `rgba(${r},${g},${b},${opacity / 100})`;
        }
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    async loadFromJSON(json) {
        if (!this.canvas || !json) {
            return;
        }
        this.historyPaused = true;
        await this.canvas.loadFromJSON(json);
        this.canvas.getObjects().forEach((obj) => {
            this.configureSelectableObject(obj);
            if (obj.criasysFilters && isFabricImage(obj)) {
                this.applyFiltersToObject(obj, obj.criasysFilters);
            }
        });
        this.canvas.requestRenderAll();
        this.historyPaused = false;
        this.pushHistory();
    }

    getFilterState(object) {
        return { ...DEFAULT_FILTER_STATE, ...(object?.criasysFilters || {}) };
    }

    applyFiltersToObject(object, state) {
        if (!isFabricImage(object)) {
            return;
        }

        const s = { ...DEFAULT_FILTER_STATE, ...state };
        object.criasysFilters = s;

        const strength = (v) => Math.max(0, Math.min(100, Number(v) || 0)) / 100;
        const list = [];

        const bri = (strength(s.brightness) - 0.5) * 0.6;
        if (Math.abs(bri) > 0.01) {
            list.push(new filters.Brightness({ brightness: bri }));
        }

        const con = (strength(s.contrast) - 0.5) * 0.8;
        if (Math.abs(con) > 0.01) {
            list.push(new filters.Contrast({ contrast: con }));
        }

        const sat = (strength(s.saturation) - 0.5) * 1.2;
        if (Math.abs(sat) > 0.01) {
            list.push(new filters.Saturation({ saturation: sat }));
        }

        const blurVal = strength(s.blur) * 0.35;
        if (blurVal > 0.01) {
            list.push(new filters.Blur({ blur: blurVal }));
        }

        if (strength(s.grayscale) > 0.05) {
            list.push(new filters.Grayscale());
        }

        object.filters = list;
        object.applyFilters();
        this.canvas?.requestRenderAll();
        this.emitChange();
    }

    clearFilters(object) {
        if (!isFabricImage(object)) {
            return;
        }
        object.criasysFilters = { ...DEFAULT_FILTER_STATE };
        object.filters = [];
        object.applyFilters();
        this.canvas?.requestRenderAll();
        this.emitChange();
    }

    toJSON() {
        return this.canvas?.toJSON() ?? null;
    }

    getLayers() {
        if (!this.canvas) {
            return [];
        }
        return [...this.canvas.getObjects()].reverse().map((obj, idx) => ({
            id: obj.criasysId || obj.type + '_' + idx,
            name: obj.name || obj.type || 'Camada',
            type: obj.type,
            visible: obj.visible !== false,
            locked: obj.selectable === false,
            object: obj,
        }));
    }

    selectLayer(object) {
        if (!this.canvas || !object) {
            return;
        }
        this.canvas.setActiveObject(object);
        this.canvas.requestRenderAll();
    }

    toggleLayerVisibility(object) {
        if (!object) {
            return;
        }
        object.visible = !object.visible;
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    toggleLayerLock(object) {
        if (!object) {
            return;
        }
        const locked = object.selectable !== false;
        object.selectable = !locked;
        object.evented = !locked;
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    moveLayer(object, direction) {
        if (!this.canvas || !object) {
            return;
        }
        if (direction === 'up') {
            this.canvas.bringObjectForward(object);
        } else if (direction === 'down') {
            this.canvas.sendObjectBackwards(object);
        } else if (direction === 'top') {
            this.canvas.bringObjectToFront(object);
        } else if (direction === 'bottom') {
            this.canvas.sendObjectToBack(object);
        }
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    removeLayer(object) {
        if (!this.canvas || !object) {
            return;
        }
        this.canvas.remove(object);
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    addText(text = 'Seu texto', options = {}) {
        const textObj = new IText(text, {
            left: (this.designWidth / 2) - 120,
            top: (this.designHeight / 2) - 30,
            fontFamily: options.fontFamily || 'Impact, Arial Black, sans-serif',
            fontSize: options.fontSize || 64,
            fill: options.fill || '#ffffff',
            fontWeight: options.fontWeight ?? 'bold',
            fontStyle: options.fontStyle || 'normal',
            underline: !!options.underline,
            linethrough: !!options.linethrough,
            stroke: options.strokeWidth > 0 ? (options.stroke || '#000000') : '',
            strokeWidth: options.strokeWidth || 0,
            textAlign: options.textAlign || 'left',
            lineHeight: options.lineHeight || 1.16,
            charSpacing: options.charSpacing || 0,
            editable: true,
            name: options.name || 'Texto',
            criasysId: options.criasysId || ('text_' + Date.now()),
            criasysFontSlug: options.fontSlug || null,
            criasysIconGlyph: options.iconGlyph || null,
        });
        if (options.shadow) {
            textObj.set('shadow', new Shadow({
                color: options.shadowColor || '#000000',
                blur: options.shadowBlur ?? 8,
                offsetX: 2,
                offsetY: 2,
            }));
        }
        this.configureSelectableObject(textObj);
        this.canvas.add(textObj);
        this.canvas.setActiveObject(textObj);
        this.canvas.requestRenderAll();
        this.emitChange();
        return textObj;
    }

    getTextStyleFromObject(obj) {
        if (!isFabricText(obj)) {
            return null;
        }
        const fw = obj.fontWeight;
        const bold = fw === 'bold' || fw === 700 || fw === '700' || Number(fw) >= 600;
        return {
            fontSlug: obj.criasysFontSlug || 'bebas_neue',
            content: obj.text || '',
            fontSize: obj.fontSize || 48,
            fill: obj.fill || '#ffffff',
            stroke: obj.stroke || '#000000',
            strokeWidth: obj.strokeWidth || 0,
            bold,
            italic: obj.fontStyle === 'italic',
            underline: !!obj.underline,
            linethrough: !!obj.linethrough,
            align: obj.textAlign || 'left',
            lineHeight: obj.lineHeight || 1.16,
            charSpacing: obj.charSpacing || 0,
            shadow: !!obj.shadow,
            shadowColor: obj.shadow?.color || '#000000',
            shadowBlur: obj.shadow?.blur ?? 8,
        };
    }

    async applyTextStyle(object, style, fontMap = {}) {
        if (!isFabricText(object) || !style) {
            return;
        }
        const fontMeta = fontMap[style.fontSlug]
            || findFontBySlug(Object.values(fontMap), style.fontSlug)
            || null;
        const isIcon = fontMeta?.source === 'icon';
        const fontStyle = !isIcon && style.italic ? 'italic' : 'normal';
        const fontWeight = fontMeta
            ? resolveFontWeight(fontMeta, style.bold)
            : (style.bold ? 'bold' : 'normal');

        if (fontMeta) {
            await ensureFontLoaded(fontMeta, { bold: style.bold, italic: style.italic });
            object.set(buildTextStyleFromFont(fontMeta, { bold: style.bold, italic: style.italic }));
            object.criasysFontSlug = style.fontSlug;
        }

        object.set({
            fontStyle,
            fontWeight,
            fill: style.fill,
            stroke: (style.strokeWidth || 0) > 0 ? (style.stroke || '#000000') : '',
            strokeWidth: style.strokeWidth || 0,
            fontSize: style.fontSize,
            textAlign: style.align || 'left',
            lineHeight: style.lineHeight || 1.16,
            charSpacing: style.charSpacing || 0,
            underline: !!style.underline,
            linethrough: !!style.linethrough,
        });
        if (style.content != null && style.content !== object.text) {
            object.set('text', style.content);
        }
        if (style.shadow) {
            object.set('shadow', new Shadow({
                color: style.shadowColor || '#000000',
                blur: style.shadowBlur ?? 8,
                offsetX: 2,
                offsetY: 2,
            }));
        } else {
            object.set('shadow', null);
        }
        if (typeof object.initDimensions === 'function') {
            object.initDimensions();
        }
        object.set('dirty', true);
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    addRect(color = '#ef4444', opacity = 80) {
        const rect = new Rect({
            left: this.designWidth * 0.15,
            top: this.designHeight * 0.2,
            width: this.designWidth * 0.7,
            height: this.designHeight * 0.25,
            fill: color,
            opacity: opacity / 100,
            name: 'Retângulo',
            criasysId: 'rect_' + Date.now(),
        });
        this.canvas.add(rect);
        this.canvas.setActiveObject(rect);
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    addCircle(color = '#3b82f6', opacity = 80) {
        const size = Math.min(this.designWidth, this.designHeight) * 0.25;
        const circle = new Circle({
            left: this.designWidth / 2 - size / 2,
            top: this.designHeight / 2 - size / 2,
            radius: size / 2,
            fill: color,
            opacity: opacity / 100,
            name: 'Círculo',
            criasysId: 'circle_' + Date.now(),
        });
        this.canvas.add(circle);
        this.canvas.setActiveObject(circle);
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    async addSvgIconFromSpec(spec) {
        let url = spec.icon_url || spec.url;
        if (!url || !this.canvas) {
            return;
        }
        if (url.startsWith('/')) {
            url = `${window.location.origin}${url}`;
        }
        const { objects, options } = await loadSVGFromURL(url);
        const grouped = util.groupSVGElements(objects, options);
        const color = spec.fill || '#ffffff';
        const applyFill = (obj) => {
            if (!obj) return;
            if (obj._objects?.length) obj._objects.forEach(applyFill);
            else if (obj.fill && obj.fill !== 'none') obj.set('fill', color);
        };
        applyFill(grouped);
        const size = spec.size || 100;
        const base = Math.max(grouped.width || 16, grouped.height || 16, 1);
        const scale = size / base;
        grouped.set({
            left: (this.designWidth - size) / 2,
            top: (this.designHeight - size) / 2,
            scaleX: scale,
            scaleY: scale,
            name: spec.name || 'Ícone',
            criasysId: 'icon_' + Date.now(),
        });
        this.canvas.add(grouped);
        this.configureSelectableObject(grouped);
        this.canvas.setActiveObject(grouped);
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    async addElementFromCatalog(spec) {
        if (!spec || !this.canvas) return;
        const type = spec.type || spec.kind;
        if (type === 'svg_icon') return this.addSvgIconFromSpec(spec);
        if (type === 'rect') {
            this.addRect(spec.fill || '#ef4444', spec.opacity ?? 80);
            return;
        }
        if (type === 'rounded_rect') {
            const rect = new Rect({
                left: this.designWidth * 0.2,
                top: this.designHeight * 0.25,
                width: this.designWidth * 0.6,
                height: this.designHeight * 0.2,
                fill: spec.fill || '#8b5cf6',
                opacity: (spec.opacity ?? 100) / 100,
                rx: spec.rx || 24,
                ry: spec.ry || spec.rx || 24,
                name: spec.name || 'Retângulo arredondado',
                criasysId: 'rect_r_' + Date.now(),
            });
            this.canvas.add(rect);
            this.configureSelectableObject(rect);
            this.canvas.setActiveObject(rect);
            this.canvas.requestRenderAll();
            this.emitChange();
            return;
        }
        if (type === 'circle') {
            this.addCircle(spec.fill || '#3b82f6', spec.opacity ?? 80);
            return;
        }
        if (type === 'ellipse') {
            const ew = this.designWidth * 0.35;
            const eh = this.designHeight * 0.18;
            const ellipse = new Ellipse({
                left: (this.designWidth - ew) / 2,
                top: (this.designHeight - eh) / 2,
                rx: ew / 2,
                ry: eh / 2,
                fill: spec.fill || '#8b5cf6',
                opacity: (spec.opacity ?? 100) / 100,
                name: spec.name || 'Elipse',
                criasysId: 'ellipse_' + Date.now(),
            });
            this.canvas.add(ellipse);
            this.configureSelectableObject(ellipse);
            this.canvas.setActiveObject(ellipse);
            this.canvas.requestRenderAll();
            this.emitChange();
            return;
        }
        if (type === 'line') {
            const y = this.designHeight / 2;
            const line = new Line([this.designWidth * 0.12, y, this.designWidth * 0.88, y], {
                stroke: spec.stroke || spec.fill || '#fff',
                strokeWidth: spec.strokeWidth || 6,
                name: spec.name || 'Linha',
                criasysId: 'line_' + Date.now(),
            });
            this.canvas.add(line);
            this.configureSelectableObject(line);
            this.canvas.setActiveObject(line);
            this.canvas.requestRenderAll();
            this.emitChange();
        } else if (type === 'sticker' || type === 'emoji') {
            this.addText(spec.char || '★', { fontSize: spec.fontSize || 80, fill: spec.fill || '#fff', name: spec.name || 'Ícone' });
        }
    }

    applyTemplate(template) {
        if (!this.canvas || !template) {
            return;
        }
        const width = this.canvas.getWidth();
        const height = this.canvas.getHeight();
        this.canvas.clear();
        const bg = template.background || {};
        this.setBackgroundColor(bg.color || '#ffffff', bg.opacity ?? 100);

        (template.objects || []).forEach((spec, idx) => {
            const kind = spec.kind || spec.type;
            if (kind === 'rect') {
                const rect = new Rect({
                    left: (spec.x ?? 0) * width,
                    top: (spec.y ?? 0) * height,
                    width: (spec.w ?? 0.5) * width,
                    height: (spec.h ?? 0.5) * height,
                    fill: spec.fill || '#ef4444',
                    opacity: (spec.opacity ?? 100) / 100,
                    name: spec.name || 'Retângulo',
                    criasysId: 'tpl_rect_' + idx + '_' + Date.now(),
                });
                this.canvas.add(rect);
            } else if (kind === 'circle') {
                const r = (spec.r ?? 0.1) * Math.min(width, height);
                const circle = new Circle({
                    left: (spec.x ?? 0.5) * width - r,
                    top: (spec.y ?? 0.5) * height - r,
                    radius: r,
                    fill: spec.fill || '#3b82f6',
                    opacity: (spec.opacity ?? 100) / 100,
                    name: spec.name || 'Círculo',
                    criasysId: 'tpl_circle_' + idx + '_' + Date.now(),
                });
                this.canvas.add(circle);
            } else if (kind === 'text') {
                const textObj = new IText(spec.text || 'Texto', {
                    left: (spec.x ?? 0.5) * width,
                    top: (spec.y ?? 0.5) * height,
                    fontFamily: spec.fontFamily || 'Impact, Arial Black, sans-serif',
                    fontSize: spec.fontSize || 48,
                    fill: spec.fill || '#ffffff',
                    originX: spec.originX || 'left',
                    originY: spec.originY || 'top',
                    fontWeight: spec.fontWeight || 'bold',
                    name: spec.name || 'Texto',
                    criasysId: 'tpl_text_' + idx + '_' + Date.now(),
                });
                this.canvas.add(textObj);
            }
        });
        this.configureAllObjects();
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    async replaceActiveImageSource(url) {
        const obj = this.canvas?.getActiveObject();
        if (!isFabricImage(obj)) {
            return null;
        }
        const props = {
            left: obj.left,
            top: obj.top,
            scaleX: obj.scaleX,
            scaleY: obj.scaleY,
            angle: obj.angle,
            name: obj.name,
            criasysFilters: obj.criasysFilters,
        };
        this.canvas.remove(obj);
        const img = await FabricImage.fromURL(url, { crossOrigin: 'anonymous' });
        img.set({ ...props, criasysId: 'img_' + Date.now() });
        this.configureSelectableObject(img);
        if (props.criasysFilters) {
            this.applyFiltersToObject(img, props.criasysFilters);
        }
        this.canvas.add(img);
        this.canvas.setActiveObject(img);
        this.canvas.requestRenderAll();
        this.emitChange();
        return img;
    }

    async removeBackgroundFromBlob(blob) {
        const { removeBackground } = await import('@imgly/background-removal');
        const result = await removeBackground(blob);
        return URL.createObjectURL(result);
    }

    async addImageFromUrl(url, name = 'Imagem') {
        const img = await FabricImage.fromURL(url, { crossOrigin: 'anonymous' });
        const maxW = this.designWidth * 0.85;
        const maxH = this.designHeight * 0.85;
        const scale = Math.min(maxW / (img.width || 1), maxH / (img.height || 1), 1);
        img.set({
            left: (this.designWidth - (img.width || 1) * scale) / 2,
            top: (this.designHeight - (img.height || 1) * scale) / 2,
            scaleX: scale,
            scaleY: scale,
            name,
            criasysId: 'img_' + Date.now(),
        });
        this.configureSelectableObject(img);
        this.canvas.add(img);
        this.canvas.setActiveObject(img);
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    applyObjectOpacity(object, opacity) {
        if (!object) {
            return;
        }
        object.set('opacity', Math.max(0, Math.min(1, opacity / 100)));
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    applyObjectFill(object, color) {
        if (!object) {
            return;
        }
        object.set('fill', color);
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    getActiveObject() {
        return this.canvas?.getActiveObject() ?? null;
    }

    async exportBlob(format = 'png', quality = 0.92, options = {}) {
        if (!this.canvas) {
            return null;
        }
        const { frameOverlayUrl = null, frameVisible = true } = options;
        if (format === 'svg') {
            const svg = this.canvas.toSVG();
            return new Blob([svg], { type: 'image/svg+xml' });
        }
        if (format === 'json') {
            return new Blob([JSON.stringify(this.toJSON(), null, 2)], { type: 'application/json' });
        }
        if (format === 'psd') {
            return await this.exportPsdBlob(frameOverlayUrl, frameVisible);
        }
        if (format === 'pdf') {
            return this.exportPdfBlob(quality, frameOverlayUrl, frameVisible);
        }
        const mime = format === 'jpg' ? 'image/jpeg' : 'image/png';
        let dataUrl;
        if (frameOverlayUrl && frameVisible !== false) {
            dataUrl = await compositeFrameOnCanvasDataUrl(this.canvas, frameOverlayUrl, frameVisible);
            if (format === 'jpg') {
                const jpegOff = document.createElement('canvas');
                jpegOff.width = this.canvas.getWidth();
                jpegOff.height = this.canvas.getHeight();
                const ctx = jpegOff.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, jpegOff.width, jpegOff.height);
                const img = await loadHtmlImage(dataUrl);
                ctx.drawImage(img, 0, 0);
                dataUrl = jpegOff.toDataURL('image/jpeg', quality);
            }
        } else {
            dataUrl = this.canvas.toDataURL({
                format: format === 'jpg' ? 'jpeg' : 'png',
                quality,
                multiplier: 1,
            });
        }
        const res = await fetch(dataUrl);
        return new Blob([await res.blob()], { type: mime });
    }

    async exportPsdBlob(frameOverlayUrl = null, frameVisible = true) {
        const w = this.canvas.getWidth();
        const h = this.canvas.getHeight();
        const layers = [];

        const bg = this.canvas.backgroundColor;
        if (bg && bg !== 'transparent') {
            const bgCanvas = document.createElement('canvas');
            bgCanvas.width = w;
            bgCanvas.height = h;
            const ctx = bgCanvas.getContext('2d');
            ctx.fillStyle = bg;
            ctx.fillRect(0, 0, w, h);
            layers.push({ name: 'Fundo', canvas: bgCanvas });
        }

        [...this.canvas.getObjects()].reverse().forEach((obj, idx) => {
            if (obj.visible === false) {
                return;
            }
            const bounds = obj.getBoundingRect();
            let el;
            try {
                el = obj.toCanvasElement({ multiplier: 1 });
            } catch {
                return;
            }
            layers.push({
                name: obj.name || obj.type || `Camada ${idx + 1}`,
                left: Math.round(bounds.left),
                top: Math.round(bounds.top),
                opacity: obj.opacity ?? 1,
                canvas: el,
            });
        });

        if (frameOverlayUrl && frameVisible !== false) {
            const frameCanvas = document.createElement('canvas');
            frameCanvas.width = w;
            frameCanvas.height = h;
            const fctx = frameCanvas.getContext('2d');
            const frameImg = await loadHtmlImage(frameOverlayUrl);
            fctx.drawImage(frameImg, 0, 0, w, h);
            layers.unshift({
                name: 'Moldura',
                canvas: frameCanvas,
            });
        }

        const buffer = writePsdBuffer({ width: w, height: h, children: layers });
        return new Blob([buffer], { type: 'application/vnd.adobe.photoshop' });
    }

    async exportPdfBlob(quality = 0.92, frameOverlayUrl = null, frameVisible = true) {
        const w = this.canvas.getWidth();
        const h = this.canvas.getHeight();
        let dataUrl;
        if (frameOverlayUrl && frameVisible !== false) {
            const pngUrl = await compositeFrameOnCanvasDataUrl(this.canvas, frameOverlayUrl, frameVisible);
            const jpegOff = document.createElement('canvas');
            jpegOff.width = w;
            jpegOff.height = h;
            const ctx = jpegOff.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, w, h);
            const img = await loadHtmlImage(pngUrl);
            ctx.drawImage(img, 0, 0);
            dataUrl = jpegOff.toDataURL('image/jpeg', quality);
        } else {
            dataUrl = this.canvas.toDataURL({
                format: 'jpeg',
                quality,
                multiplier: 1,
            });
        }
        const orientation = w >= h ? 'landscape' : 'portrait';
        const pdf = new jsPDF({
            orientation,
            unit: 'px',
            format: [w, h],
            hotfixes: ['px_scaling'],
        });
        pdf.addImage(dataUrl, 'JPEG', 0, 0, w, h);
        return pdf.output('blob');
    }

    zoomToFit(containerWidth, containerHeight) {
        if (!this.canvas || !containerWidth || !containerHeight) {
            return this.viewportZoom || 1;
        }
        const pad = 32;
        const scale = Math.min(
            (containerWidth - pad) / this.designWidth,
            (containerHeight - pad) / this.designHeight
        );
        return this.applyViewportZoom(scale);
    }
}

export function imageStudioMethods() {
    return {
        imageStudioReady: false,
        imageStudioPresets: [],
        imageStudioGroups: {},
        imageStudioExportFormats: [],
        imageStudioTemplates: [],
        imageStudioFonts: [],
        imageStudioFontGroups: {},
        imageStudioIconGlyphs: [],
        imageStudioFontMap: {},
        imageStudioFontFilter: '',
        imageStudioIconFilter: '',
        imageStudioTextFontSlug: 'bebas_neue',
        imageStudioTextContent: 'Seu título aqui',
        imageStudioTextSize: 64,
        imageStudioTextFill: '#ffffff',
        imageStudioTextStroke: '#000000',
        imageStudioTextStrokeWidth: 0,
        imageStudioTextBold: true,
        imageStudioTextItalic: false,
        imageStudioTextUnderline: false,
        imageStudioTextLinethrough: false,
        imageStudioTextAlign: 'center',
        imageStudioTextLineHeight: 1.16,
        imageStudioTextCharSpacing: 0,
        imageStudioTextShadow: false,
        imageStudioTextShadowColor: '#000000',
        imageStudioTextShadowBlur: 8,
        _syncingTextUi: false,
        imageStudioBgRemoval: false,
        imageStudioPreset: 'ig_feed_square',
        imageStudioPresetFilter: '',
        imageStudioEngine: null,
        imageStudioLayers: [],
        imageStudioSaving: false,
        imageStudioLastExport: null,
        imageStudioBgColor: '#ffffff',
        imageStudioBgOpacity: 100,
        imageStudioSelectedObject: null,
        imageStudioObjectScale: 100,
        imageStudioZoom: 100,
        imageStudioShowFormatGuides: true,
        imageStudioBgRemoving: false,
        imageStudioFilters: { ...DEFAULT_FILTER_STATE },
        imageStudioShowGrid: false,
        imageStudioSnapGrid: false,
        imageStudioGridSize: 20,
        imageStudioCanUndo: false,
        imageStudioCanRedo: false,
        imageStudioFrameSlug: 'none',
        imageStudioFrameColor: '#ffffff',
        imageStudioFrameSecondaryColor: '#ef4444',
        imageStudioFrameWidth: 56,
        imageStudioFrameOpacity: 100,
        imageStudioFrameInset: 12,
        imageStudioFrameOverlayUrl: null,
        imageStudioFrameVisible: true,
        imageStudioFrameApplyTimeout: null,
        imageStudioFrames: [],
        imageStudioElements: [],
        imageStudioElementGroups: {},
        imageStudioElementFilter: '',
        imageStudioLocalWatch: null,

        get filteredImageStudioPresets() {
            const q = (this.imageStudioPresetFilter || '').trim().toLowerCase();
            let list = this.imageStudioPresets || [];
            if (q) {
                list = list.filter(
                    (p) =>
                        (p.name || '').toLowerCase().includes(q)
                        || (p.group_label || '').toLowerCase().includes(q)
                );
            }
            return list;
        },

        get imageStudioPresetGroups() {
            const groups = {};
            (this.filteredImageStudioPresets || []).forEach((p) => {
                const key = p.group_label || p.group || 'Outros';
                if (!groups[key]) groups[key] = [];
                groups[key].push(p);
            });
            return groups;
        },

        get imageStudioElementsByGroup() {
            const q = (this.imageStudioElementFilter || '').trim().toLowerCase();
            const groups = {};
            (this.imageStudioElements || []).forEach((el) => {
                if (q) {
                    const hay = `${el.name || ''} ${el.group || ''}`.toLowerCase();
                    if (!hay.includes(q)) return;
                }
                const key = this.imageStudioElementGroups[el.group] || el.group || 'Outros';
                if (!groups[key]) groups[key] = [];
                groups[key].push(el);
            });
            return groups;
        },

        get imageStudioCurrentPreset() {
            return (this.imageStudioPresets || []).find((p) => p.slug === this.imageStudioPreset) || null;
        },

        get filteredImageStudioFonts() {
            const q = (this.imageStudioFontFilter || '').trim().toLowerCase();
            let list = this.imageStudioFonts || [];
            if (q) {
                list = list.filter((f) =>
                    (f.label || '').toLowerCase().includes(q)
                    || (f.group_label || '').toLowerCase().includes(q)
                    || (f.slug || '').toLowerCase().includes(q)
                );
            }
            return list;
        },

        get imageStudioFontsGrouped() {
            const groups = {};
            (this.filteredImageStudioFonts || []).forEach((f) => {
                const key = f.group_label || f.group || 'Outros';
                if (!groups[key]) {
                    groups[key] = [];
                }
                groups[key].push(f);
            });
            return groups;
        },

        get filteredImageStudioIcons() {
            const q = (this.imageStudioIconFilter || '').trim().toLowerCase();
            let list = this.imageStudioIconGlyphs || [];
            if (q) {
                list = list.filter((g) =>
                    (g.label || '').toLowerCase().includes(q)
                    || (g.group || '').toLowerCase().includes(q)
                );
            }
            return list;
        },

        imageStudioTextStylePayload() {
            return {
                fontSlug: this.imageStudioTextFontSlug,
                content: this.imageStudioTextContent,
                fontSize: this.imageStudioTextSize,
                fill: this.imageStudioTextFill,
                stroke: this.imageStudioTextStroke,
                strokeWidth: this.imageStudioTextStrokeWidth,
                bold: this.imageStudioTextBold,
                italic: this.imageStudioTextItalic,
                underline: this.imageStudioTextUnderline,
                linethrough: this.imageStudioTextLinethrough,
                align: this.imageStudioTextAlign,
                lineHeight: this.imageStudioTextLineHeight,
                charSpacing: this.imageStudioTextCharSpacing,
                shadow: this.imageStudioTextShadow,
                shadowColor: this.imageStudioTextShadowColor,
                shadowBlur: this.imageStudioTextShadowBlur,
            };
        },

        buildImageStudioFontMap() {
            const map = {};
            (this.imageStudioFonts || []).forEach((f) => {
                if (f.slug) {
                    map[f.slug] = f;
                }
            });
            this.imageStudioFontMap = map;
        },

        seedImageStudioFromEmbedded(meta = {}) {
            let fonts = meta.imageStudioFonts;
            let icons = meta.imageStudioIconGlyphs;
            let iconFonts = meta.imageStudioIconFonts;

            if (!Array.isArray(fonts) || !fonts.length) {
                fonts = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-fonts');
            }
            if (!Array.isArray(icons) || !icons.length) {
                icons = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-icons');
            }
            if (!Array.isArray(iconFonts) || !iconFonts.length) {
                iconFonts = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-icon-fonts');
            }

            if (Array.isArray(fonts) && fonts.length) {
                this.imageStudioFonts = fonts;
            } else if (!this.imageStudioFonts?.length) {
                this.imageStudioFonts = FALLBACK_FONTS;
            }
            if (Array.isArray(icons) && icons.length) {
                this.imageStudioIconGlyphs = icons;
            }
            this.buildImageStudioFontMap();
            preloadIconFontCdns(iconFonts || []);
            preloadStarterGoogleFonts(this.imageStudioFonts);
            if (!this.imageStudioFontMap[this.imageStudioTextFontSlug]) {
                this.imageStudioTextFontSlug = this.imageStudioFonts[0]?.slug || 'bebas_neue';
            }
        },

        imageStudioFilterFontList() {
            const q = (this.imageStudioFontFilter || '').trim().toLowerCase();
            const list = this.$refs.imageStudioFontList;
            if (!list) {
                return;
            }
            list.querySelectorAll('.is-font-row').forEach((btn) => {
                const label = (btn.dataset.fontLabel || '').toLowerCase();
                const group = (btn.dataset.fontGroup || '').toLowerCase();
                const slug = (btn.dataset.fontSlug || '').toLowerCase();
                const show = !q || label.includes(q) || group.includes(q) || slug.includes(q);
                btn.style.display = show ? '' : 'none';
            });
        },

        async loadImageStudioCatalog() {
            try {
                const { data } = await api.get('/image-studio/catalog');
                this.imageStudioPresets = data.presets || [];
                this.imageStudioGroups = data.groups || {};
                this.imageStudioExportFormats = data.export_formats || [];
                this.imageStudioTemplates = data.templates || [];
                this.imageStudioFrames = data.frames || [];
                this.imageStudioElements = data.elements || [];
                this.imageStudioElementGroups = data.element_groups || {};
                this.imageStudioFonts = data.fonts?.length ? data.fonts : FALLBACK_FONTS;
                this.imageStudioFontGroups = data.font_groups || {};
                this.imageStudioIconGlyphs = data.icon_glyphs || [];
                this.buildImageStudioFontMap();
                preloadIconFontCdns(data.icon_fonts || []);
                preloadStarterGoogleFonts(this.imageStudioFonts);
                this.imageStudioBgRemoval = true;
                if (data.defaults?.preset) {
                    this.imageStudioPreset = data.defaults.preset;
                }
                if (!this.imageStudioFontMap[this.imageStudioTextFontSlug]) {
                    this.imageStudioTextFontSlug = this.imageStudioFonts[0]?.slug || 'bebas_neue';
                }
            } catch (e) {
                console.error('Image Studio catalog:', e);
                if (!this.imageStudioFonts?.length) {
                    this.imageStudioFonts = FALLBACK_FONTS;
                    this.buildImageStudioFontMap();
                }
                this.error = e.response?.data?.message || 'Não foi possível carregar catálogo do Image Studio';
            }
        },

        async initImageStudio() {
            await this.loadImageStudioCatalog();
            if (this.imageStudioReady && this.imageStudioEngine?.canvas) {
                this.imageStudioEngine.setScaleWrapper(this.$refs.imageStudioCanvasScaler);
                this.imageStudioEngine.setFormatGuidesVisible(this.imageStudioShowFormatGuides);
                this.refreshImageStudioLayers();
                this.fitImageStudioCanvas();
                return;
            }
            await this.$nextTick();

            const el = this.$refs.imageStudioCanvas;
            if (!el) {
                return;
            }

            if (!this.imageStudioEngine) {
                this.imageStudioEngine = new ImageStudioEngine(el);
                this.imageStudioEngine.onChange = () => {
                    this.refreshImageStudioLayers();
                    this.scheduleImageStudioSave();
                };
            }

            this.imageStudioEngine.setScaleWrapper(this.$refs.imageStudioCanvasScaler);
            this.imageStudioEngine.setFormatGuidesVisible(this.imageStudioShowFormatGuides);

            await this.loadImageStudioDesign();

            if (!this.imageStudioEngine?.canvas) {
                const p = this.imageStudioPresets.find((x) => x.slug === this.imageStudioPreset)
                    || { width: 1080, height: 1080 };
                this.imageStudioEngine.init(p.width, p.height, this.imageStudioBgColor);
                this.imageStudioEngine.setBackgroundColor(this.imageStudioBgColor, this.imageStudioBgOpacity);
            }

            this.imageStudioEngine.pushHistory();
            this.imageStudioReady = true;
            this.setupImageStudioKeyboard();
            this.setupImageStudioLocalWatch();
            this.setupImageStudioWheelZoom();
            this.fitImageStudioCanvas();
        },

        async loadImageStudioDesign() {
            const fallback = this.imageStudioPresets.find((p) => p.slug === this.imageStudioPreset)
                || { width: 1080, height: 1080 };
            let w = fallback.width || 1080;
            let h = fallback.height || 1080;
            let canvasJson = null;
            try {
                const { data } = await api.get(`/projects/${this.projectId}/image-studio`, {
                    params: { preset: this.imageStudioPreset },
                });
                const preset = this.imageStudioPresets.find((p) => p.slug === data.preset) || fallback;
                w = preset.width || data.width || w;
                h = preset.height || data.height || h;
                canvasJson = data.canvas;
            } catch (e) {
                this.error = e.response?.data?.message || null;
            }
            this.imageStudioEngine.init(w, h, this.imageStudioBgColor);
            this.imageStudioEngine.setBackgroundColor(this.imageStudioBgColor, this.imageStudioBgOpacity);
            if (canvasJson) {
                try {
                    await this.imageStudioEngine.loadFromJSON(canvasJson);
                } catch {
                    /* canvas vazio ok */
                }
            }
            this.refreshImageStudioLayers();
        },

        fitImageStudioCanvas() {
            const wrap = this.$refs.imageStudioCanvasWrap;
            if (!wrap || !this.imageStudioEngine?.canvas) {
                return;
            }
            const z = this.imageStudioEngine.zoomToFit(wrap.clientWidth, wrap.clientHeight);
            this.imageStudioZoom = Math.round(z * 100);
        },

        imageStudioSetZoomPercent(percent) {
            if (!this.imageStudioEngine?.canvas) {
                return;
            }
            const z = this.imageStudioEngine.applyViewportZoom(percent / 100);
            this.imageStudioZoom = Math.round(z * 100);
        },

        imageStudioZoomIn() {
            this.imageStudioSetZoomPercent(Math.min(400, this.imageStudioZoom + 10));
        },

        imageStudioZoomOut() {
            this.imageStudioSetZoomPercent(Math.max(8, this.imageStudioZoom - 10));
        },

        imageStudioZoomReset() {
            this.imageStudioSetZoomPercent(100);
        },

        setupImageStudioWheelZoom() {
            const wrap = this.$refs.imageStudioCanvasWrap;
            if (!wrap || wrap._criasysWheelZoom) {
                return;
            }
            wrap._criasysWheelZoom = true;
            wrap.addEventListener('wheel', (e) => {
                if (this.activeTab !== 'image_studio') {
                    return;
                }
                e.preventDefault();
                const delta = e.deltaY > 0 ? -8 : 8;
                this.imageStudioSetZoomPercent(this.imageStudioZoom + delta);
            }, { passive: false });
        },

        onImageStudioFormatGuidesChange() {
            this.imageStudioEngine?.setFormatGuidesVisible(this.imageStudioShowFormatGuides);
        },

        refreshImageStudioLayers() {
            let layers = this.imageStudioEngine?.getLayers() || [];
            if (this.imageStudioFrameOverlayUrl) {
                layers = [{
                    id: 'criasys_frame_overlay',
                    name: 'Moldura',
                    type: 'image',
                    visible: this.imageStudioFrameVisible !== false,
                    locked: true,
                    isFrameLayer: true,
                    object: { criasysFrameLayer: true },
                }, ...layers];
            }
            this.imageStudioLayers = layers;
            const raw = this.imageStudioEngine?.getActiveObject();
            this.imageStudioSelectedObject = raw
                ? { type: normalizeFabricType(raw), opacity: raw.opacity ?? 1 }
                : null;
            if (raw && isFabricText(raw)) {
                this._syncingTextUi = true;
                const st = this.imageStudioEngine.getTextStyleFromObject(raw);
                if (st) {
                    this.imageStudioTextFontSlug = st.fontSlug;
                    this.imageStudioTextContent = st.content;
                    this.imageStudioTextSize = st.fontSize;
                    this.imageStudioTextFill = st.fill;
                    this.imageStudioTextStroke = st.stroke;
                    this.imageStudioTextStrokeWidth = st.strokeWidth;
                    this.imageStudioTextBold = st.bold;
                    this.imageStudioTextItalic = st.italic;
                    this.imageStudioTextUnderline = st.underline;
                    this.imageStudioTextLinethrough = st.linethrough;
                    this.imageStudioTextAlign = st.align;
                    this.imageStudioTextLineHeight = st.lineHeight;
                    this.imageStudioTextCharSpacing = st.charSpacing;
                    this.imageStudioTextShadow = st.shadow;
                    this.imageStudioTextShadowColor = st.shadowColor;
                    this.imageStudioTextShadowBlur = st.shadowBlur;
                }
                this.$nextTick(() => {
                    this._syncingTextUi = false;
                });
            }
            this.imageStudioObjectScale = this.imageStudioEngine?.getActiveObjectScalePercent() ?? 100;
            this.imageStudioCanUndo = this.imageStudioEngine?.canUndo() ?? false;
            this.imageStudioCanRedo = this.imageStudioEngine?.canRedo() ?? false;
            if (raw && isFabricImage(raw)) {
                this.imageStudioFilters = this.imageStudioEngine.getFilterState(raw);
            }
        },

        imageStudioSetObjectScale(percent) {
            this.imageStudioEngine?.setActiveObjectScalePercent(percent);
            this.imageStudioObjectScale = this.imageStudioEngine?.getActiveObjectScalePercent() ?? 100;
            this.refreshImageStudioLayers();
        },

        imageStudioNudgeObjectScale(delta) {
            this.imageStudioEngine?.nudgeActiveObjectScale(delta);
            this.imageStudioObjectScale = this.imageStudioEngine?.getActiveObjectScalePercent() ?? 100;
            this.refreshImageStudioLayers();
        },

        imageStudioApplyFilters() {
            const obj = this.imageStudioEngine?.getActiveObject();
            if (isFabricImage(obj)) {
                this.imageStudioEngine.applyFiltersToObject(obj, this.imageStudioFilters);
            }
        },

        imageStudioClearFilters() {
            const obj = this.imageStudioEngine?.getActiveObject();
            if (isFabricImage(obj)) {
                this.imageStudioEngine.clearFilters(obj);
                this.imageStudioFilters = { ...DEFAULT_FILTER_STATE };
            }
        },

        setupImageStudioKeyboard() {
            if (this._imageStudioKeyHandler) {
                return;
            }
            this._imageStudioKeyHandler = (e) => {
                if (this.activeTab !== 'image_studio') {
                    return;
                }
                const mod = e.ctrlKey || e.metaKey;
                if (mod && e.key === 'z' && !e.shiftKey) {
                    e.preventDefault();
                    this.imageStudioUndo();
                } else if (mod && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
                    e.preventDefault();
                    this.imageStudioRedo();
                }
            };
            window.addEventListener('keydown', this._imageStudioKeyHandler);
        },

        async imageStudioUndo() {
            const ok = await this.imageStudioEngine?.undo();
            if (ok) {
                this.refreshImageStudioLayers();
            }
        },

        async imageStudioRedo() {
            const ok = await this.imageStudioEngine?.redo();
            if (ok) {
                this.refreshImageStudioLayers();
            }
        },

        onImageStudioGridChange() {
            this.imageStudioEngine?.setGridOptions({
                showGrid: this.imageStudioShowGrid,
                snapToGrid: this.imageStudioSnapGrid,
                gridSize: this.imageStudioGridSize,
            });
        },

        imageStudioAlignObject(mode) {
            this.imageStudioEngine?.alignActiveObject(mode);
            this.refreshImageStudioLayers();
        },

        async imageStudioApplyFrame() {
            if (this.imageStudioFrameSlug === 'none' || !this.imageStudioFrameSlug) {
                this.imageStudioFrameOverlayUrl = null;
                this.imageStudioFrameVisible = true;
                this.refreshImageStudioLayers();
                return;
            }
            await this.initImageStudio();
            if (!this.imageStudioEngine?.canvas) {
                this.error = 'Canvas não carregou — aguarde ou recarregue a página';
                return;
            }
            const preset = this.imageStudioCurrentPreset;
            if (!preset) {
                this.error = 'Selecione um formato de canvas antes de aplicar a moldura';
                return;
            }
            try {
                this.error = null;
                this.message = 'Gerando moldura…';
                const { data } = await api.get(`/projects/${this.projectId}/image-studio/frame-preview`, {
                    params: {
                        slug: this.imageStudioFrameSlug,
                        width: preset.width,
                        height: preset.height,
                        color: this.imageStudioFrameColor,
                        secondary_color: this.imageStudioFrameSecondaryColor,
                        frame_width: this.imageStudioFrameWidth,
                        opacity: this.imageStudioFrameOpacity,
                        inset: this.imageStudioFrameInset,
                    },
                });
                let imageSrc = data?.data_url || null;
                if (!imageSrc && data?.url) {
                    const fetchUrl = data.url.startsWith('http')
                        ? data.url.replace(/^https?:\/\/[^/]+/, '')
                        : data.url;
                    const fileRes = await fetch(fetchUrl, { credentials: 'same-origin' });
                    if (!fileRes.ok) {
                        throw new Error('Não foi possível carregar o PNG da moldura (HTTP ' + fileRes.status + ')');
                    }
                    const blob = await fileRes.blob();
                    imageSrc = await new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = () => resolve(reader.result);
                        reader.onerror = () => reject(new Error('Erro ao ler PNG da moldura'));
                        reader.readAsDataURL(blob);
                    });
                }
                if (!imageSrc) {
                    this.error = 'Servidor não retornou a imagem da moldura';
                    return;
                }
                this.imageStudioFrameOverlayUrl = imageSrc;
                this.imageStudioFrameVisible = true;
                this.refreshImageStudioLayers();
                const frameName = (this.imageStudioFrames || []).find((f) => f.slug === this.imageStudioFrameSlug)?.name
                    || this.imageStudioFrameSlug;
                this.message = `Moldura "${frameName}" aplicada — visível sobre o canvas`;
            } catch (e) {
                this.error = e.response?.data?.message || e.message || 'Erro ao aplicar moldura';
            }
        },

        onImageStudioFrameSlugChange() {
            if (this.imageStudioFrameSlug === 'none') {
                this.imageStudioFrameOverlayUrl = null;
                this.imageStudioFrameVisible = true;
                this.refreshImageStudioLayers();
            }
        },

        scheduleImageStudioFrameReapply() {
            clearTimeout(this.imageStudioFrameApplyTimeout);
            this.imageStudioFrameApplyTimeout = setTimeout(() => {
                if (this.imageStudioFrameOverlayUrl && this.imageStudioFrameSlug !== 'none') {
                    this.imageStudioApplyFrame();
                }
            }, 450);
        },

        async imageStudioPickLocalFolder() {
            if (!window.criasys?.pickWatchFolder) {
                this.error = 'Disponível apenas no app desktop (Electron)';
                return;
            }
            const folder = await window.criasys.pickWatchFolder();
            if (!folder) {
                return;
            }
            await window.criasys.watchFolder(folder);
            this.imageStudioLocalWatch = folder;
            this.message = `Monitorando: ${folder}`;
        },

        setupImageStudioLocalWatch() {
            if (!window.criasys?.onFolderChanged || this._imageStudioWatchSetup) {
                return;
            }
            this._imageStudioWatchSetup = true;
            window.criasys.onFolderChanged(async (data) => {
                if (this.activeTab !== 'image_studio' || !data?.filePath) {
                    return;
                }
                const ext = (data.filePath.split('.').pop() || '').toLowerCase();
                if (!['png', 'jpg', 'jpeg', 'webp', 'gif'].includes(ext)) {
                    return;
                }
                if (!window.criasys.readLocalFile) {
                    return;
                }
                try {
                    const file = await window.criasys.readLocalFile(data.filePath);
                    if (file?.dataUrl) {
                        const name = data.filePath.split(/[/\\]/).pop();
                        await this.imageStudioEngine?.addImageFromUrl(file.dataUrl, name);
                        this.refreshImageStudioLayers();
                        this.message = `Importado: ${name}`;
                    }
                } catch {
                    /* arquivo pode ainda estar sendo gravado */
                }
            });
        },

        scheduleImageStudioSave() {
            clearTimeout(this.imageStudioSaveTimeout);
            this.imageStudioSaveTimeout = setTimeout(() => this.saveImageStudioDesign(), 1500);
        },

        async saveImageStudioDesign() {
            if (!this.imageStudioEngine?.canvas) {
                return;
            }
            this.imageStudioSaving = true;
            try {
                const json = this.imageStudioEngine.toJSON();
                await api.put(`/projects/${this.projectId}/image-studio`, {
                    preset: this.imageStudioPreset,
                    canvas: json,
                });
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar design';
            } finally {
                this.imageStudioSaving = false;
            }
        },

        async switchImageStudioPreset(slug) {
            if (slug === this.imageStudioPreset) {
                return;
            }
            if (!this.imageStudioEngine?.canvas) {
                await this.initImageStudio();
            }
            await this.saveImageStudioDesign();
            this.imageStudioPreset = slug;
            const preset = this.imageStudioPresets.find((p) => p.slug === slug);
            if (preset && this.imageStudioEngine?.canvas) {
                this.imageStudioEngine.setSize(preset.width, preset.height);
                this.fitImageStudioCanvas();
                this.message = `Formato: ${preset.name} (${preset.width}×${preset.height})`;
                if (this.imageStudioFrameOverlayUrl && this.imageStudioFrameSlug !== 'none') {
                    await this.imageStudioApplyFrame();
                }
            }
            this.refreshImageStudioLayers();
        },

        onImageStudioBgChange() {
            this.imageStudioEngine?.setBackgroundColor(this.imageStudioBgColor, this.imageStudioBgOpacity);
        },

        async imageStudioSelectFont(slug) {
            this.imageStudioTextFontSlug = slug;
            const fontMeta = this.imageStudioFontMap[slug];
            if (fontMeta) {
                await ensureFontLoaded(fontMeta, {
                    bold: this.imageStudioTextBold,
                    italic: this.imageStudioTextItalic,
                });
            }
            await this.imageStudioOnTextControlChange();
        },

        async imageStudioOnTextControlChange() {
            if (this._syncingTextUi) {
                return;
            }
            const obj = this.imageStudioEngine?.getActiveObject();
            if (isFabricText(obj)) {
                await this.imageStudioEngine.applyTextStyle(
                    obj,
                    this.imageStudioTextStylePayload(),
                    this.imageStudioFontMap
                );
                this.refreshImageStudioLayers();
            }
        },

        async imageStudioAddText() {
            if (!this.imageStudioEngine?.canvas) {
                this.error = 'Abra o Image Studio e aguarde o canvas carregar';
                return;
            }
            const fontMeta = this.imageStudioFontMap[this.imageStudioTextFontSlug]
                || findFontBySlug(this.imageStudioFonts, this.imageStudioTextFontSlug)
                || findFontBySlug(this.imageStudioFonts, 'bebas_neue')
                || this.imageStudioFonts[0];
            if (fontMeta) {
                await ensureFontLoaded(fontMeta, {
                    bold: this.imageStudioTextBold,
                    italic: this.imageStudioTextItalic,
                });
            }
            const style = buildTextStyleFromFont(fontMeta, {
                bold: this.imageStudioTextBold,
                italic: this.imageStudioTextItalic,
            });
            const text = this.imageStudioTextContent || 'Seu título aqui';
            this.imageStudioEngine.addText(text, {
                ...style,
                fontSize: this.imageStudioTextSize,
                fill: this.imageStudioTextFill,
                stroke: this.imageStudioTextStroke,
                strokeWidth: this.imageStudioTextStrokeWidth,
                underline: this.imageStudioTextUnderline,
                linethrough: this.imageStudioTextLinethrough,
                textAlign: this.imageStudioTextAlign,
                lineHeight: this.imageStudioTextLineHeight,
                charSpacing: this.imageStudioTextCharSpacing,
                fontSlug: fontMeta?.slug || this.imageStudioTextFontSlug,
                shadow: this.imageStudioTextShadow,
                shadowColor: this.imageStudioTextShadowColor,
                shadowBlur: this.imageStudioTextShadowBlur,
            });
            this.refreshImageStudioLayers();
            this.message = `Texto adicionado — ${fontMeta?.label || 'fonte'}`;
        },

        async imageStudioAddIconGlyph(glyph) {
            if (!glyph?.char) {
                return;
            }
            if (!this.imageStudioEngine?.canvas) {
                await this.initImageStudio();
            }
            if (!this.imageStudioEngine?.canvas) {
                this.error = 'Canvas não carregou';
                return;
            }
            const fontMeta = this.imageStudioFontMap[glyph.font]
                || findFontBySlug(this.imageStudioFonts, glyph.font);
            if (fontMeta) {
                await ensureFontLoaded(fontMeta);
            }
            const style = buildTextStyleFromFont(fontMeta, { bold: false, italic: false });
            this.imageStudioEngine.addText(glyph.char, {
                ...style,
                fontSize: this.imageStudioTextSize || 96,
                fill: this.imageStudioTextFill || '#ffffff',
                stroke: this.imageStudioTextStroke,
                strokeWidth: this.imageStudioTextStrokeWidth,
                textAlign: 'center',
                fontSlug: glyph.font,
                iconGlyph: glyph.slug,
                name: glyph.label || 'Ícone',
            });
            this.refreshImageStudioLayers();
            this.message = `Ícone "${glyph.label || glyph.slug}" adicionado`;
        },

        imageStudioAddIconGlyphBySlug(slug) {
            if (!slug) {
                return;
            }
            let glyph = (this.imageStudioIconGlyphs || []).find((g) => g.slug === slug);
            if (!glyph) {
                const embedded = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-icons');
                if (Array.isArray(embedded)) {
                    this.imageStudioIconGlyphs = embedded;
                    glyph = embedded.find((g) => g.slug === slug);
                }
            }
            if (!glyph) {
                this.error = `Ícone "${slug}" não encontrado no catálogo`;
                return;
            }
            void this.imageStudioAddIconGlyph(glyph);
        },

        imageStudioFilterIconList() {
            const q = (this.imageStudioIconFilter || '').trim().toLowerCase();
            const list = this.$refs.imageStudioIconList;
            if (!list) {
                return;
            }
            list.querySelectorAll('.is-icon-row').forEach((btn) => {
                const label = (btn.dataset.iconLabel || '').toLowerCase();
                const group = (btn.dataset.iconGroup || '').toLowerCase();
                const show = !q || label.includes(q) || group.includes(q);
                btn.style.display = show ? '' : 'none';
            });
        },

        imageStudioToggleTextBold() {
            this.imageStudioTextBold = !this.imageStudioTextBold;
            void this.imageStudioOnTextControlChange();
        },

        imageStudioToggleTextItalic() {
            this.imageStudioTextItalic = !this.imageStudioTextItalic;
            void this.imageStudioOnTextControlChange();
        },

        imageStudioToggleTextUnderline() {
            this.imageStudioTextUnderline = !this.imageStudioTextUnderline;
            void this.imageStudioOnTextControlChange();
        },

        imageStudioToggleTextLinethrough() {
            this.imageStudioTextLinethrough = !this.imageStudioTextLinethrough;
            void this.imageStudioOnTextControlChange();
        },

        imageStudioSetTextAlign(align) {
            this.imageStudioTextAlign = align;
            this.imageStudioOnTextControlChange();
        },

        imageStudioAddShape(type) {
            if (!this.imageStudioEngine?.canvas) {
                this.error = 'Canvas não carregou — clique Image Studio de novo';
                return;
            }
            if (type === 'circle') {
                this.imageStudioEngine.addCircle();
            } else {
                this.imageStudioEngine.addRect();
            }
            this.refreshImageStudioLayers();
            this.message = 'Forma adicionada';
        },

        async imageStudioAddElement(el) {
            if (!this.imageStudioEngine?.canvas) {
                await this.initImageStudio();
            }
            if (!this.imageStudioEngine?.canvas) {
                this.error = 'Canvas não carregou';
                return;
            }
            try {
                await this.imageStudioEngine.addElementFromCatalog(el);
                this.refreshImageStudioLayers();
                this.message = (el.name || 'Elemento') + ' adicionado';
            } catch (e) {
                this.error = e.message || 'Erro ao adicionar elemento';
            }
        },

        async imageStudioUploadImage(event) {
            const file = event?.target?.files?.[0];
            if (!file) return;
            if (!this.imageStudioEngine?.canvas) {
                await this.initImageStudio();
            }
            if (!this.imageStudioEngine?.canvas) {
                this.error = 'Canvas não carregou — recarregue a página (F5)';
                event.target.value = '';
                return;
            }
            try {
                const localUrl = URL.createObjectURL(file);
                await this.imageStudioEngine.addImageFromUrl(localUrl, file.name);
                this.refreshImageStudioLayers();
                this.message = 'Imagem adicionada ao canvas';
            } catch (e) {
                this.error = e.message || 'Erro ao adicionar imagem';
            } finally {
                event.target.value = '';
            }
        },

        async imageStudioRemoveBackground(event) {
            const file = event?.target?.files?.[0];
            if (!file) return;
            if (!this.imageStudioEngine?.canvas) await this.initImageStudio();
            try {
                this.imageStudioBgRemoving = true;
                this.message = 'Removendo fundo…';
                const url = await this.imageStudioEngine.removeBackgroundFromBlob(file);
                await this.imageStudioEngine.addImageFromUrl(url, 'Sem fundo');
                this.refreshImageStudioLayers();
                this.message = 'Fundo removido — imagem adicionada';
            } catch (e) {
                this.error = e.message || 'Erro ao remover fundo';
            } finally {
                this.imageStudioBgRemoving = false;
                if (event?.target) event.target.value = '';
            }
        },

        async imageStudioRemoveBgFromSelection() {
            const obj = this.imageStudioEngine?.getActiveObject();
            if (!isFabricImage(obj)) {
                this.error = 'Selecione uma imagem no canvas (clique nela primeiro)';
                return;
            }
            try {
                this.imageStudioBgRemoving = true;
                this.message = 'Removendo fundo da imagem selecionada…';
                const dataUrl = obj.toDataURL({ format: 'png', multiplier: 1 });
                const res = await fetch(dataUrl);
                const blob = await res.blob();
                const url = await this.imageStudioEngine.removeBackgroundFromBlob(blob);
                await this.imageStudioEngine.replaceActiveImageSource(url);
                this.refreshImageStudioLayers();
                this.message = 'Fundo removido da imagem selecionada';
            } catch (e) {
                this.error = e.message || 'Erro ao remover fundo';
            } finally {
                this.imageStudioBgRemoving = false;
            }
        },

        imageStudioSelectLayer(layer) {
            if (layer?.isFrameLayer) {
                return;
            }
            this.imageStudioEngine?.selectLayer(layer.object);
            this.refreshImageStudioLayers();
        },

        imageStudioLayerAction(layer, action) {
            if (layer?.isFrameLayer) {
                if (action === 'visibility') {
                    this.imageStudioFrameVisible = !this.imageStudioFrameVisible;
                } else if (action === 'delete') {
                    this.imageStudioFrameOverlayUrl = null;
                    this.imageStudioFrameVisible = true;
                    this.imageStudioFrameSlug = 'none';
                }
                this.refreshImageStudioLayers();
                return;
            }
            if (action === 'visibility') {
                this.imageStudioEngine?.toggleLayerVisibility(layer.object);
            } else if (action === 'lock') {
                this.imageStudioEngine?.toggleLayerLock(layer.object);
            } else if (action === 'delete') {
                this.imageStudioEngine?.removeLayer(layer.object);
            } else {
                this.imageStudioEngine?.moveLayer(layer.object, action);
            }
            this.refreshImageStudioLayers();
        },

        imageStudioObjectOpacity(value) {
            const obj = this.imageStudioEngine?.getActiveObject();
            if (obj) {
                this.imageStudioEngine.applyObjectOpacity(obj, value);
            }
        },

        async imageStudioExport(format) {
            if (!this.imageStudioEngine) {
                return;
            }
            try {
                const blob = await this.imageStudioEngine.exportBlob(format, 0.92, {
                    frameOverlayUrl: this.imageStudioFrameOverlayUrl,
                    frameVisible: this.imageStudioFrameVisible,
                });
                if (!blob) {
                    return;
                }
                const form = new FormData();
                const ext = format === 'jpeg' ? 'jpg' : format;
                form.append('file', blob, `design.${ext}`);
                form.append('format', format);
                form.append('preset', this.imageStudioPreset);
                const { data } = await api.post(`/projects/${this.projectId}/image-studio/export`, form, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                this.imageStudioLastExport = data.export;
                this.message = `Exportado: ${format.toUpperCase()}`;
                if (format === 'png' || format === 'jpg') {
                    window.open(data.export.url, '_blank');
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao exportar';
            }
        },

        async imageStudioPushThumbnail() {
            if (!this.imageStudioLastExport?.filename) {
                await this.imageStudioExport('png');
            }
            if (!this.imageStudioLastExport?.filename) {
                return;
            }
            try {
                const { data } = await api.post(`/projects/${this.projectId}/image-studio/push-thumbnail`, {
                    filename: this.imageStudioLastExport.filename,
                    platform: this.selectedThumbnailPlatform,
                });
                this.message = 'Enviado para aba Thumbnail';
                if (data.thumbnail?.url) {
                    this.thumbnailPreviewUrl = data.thumbnail.url;
                }
                this.switchTab('thumbnail');
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao enviar para thumbnail';
            }
        },

        async imageStudioPushLibrary() {
            if (!this.imageStudioLastExport?.filename) {
                await this.imageStudioExport('png');
            }
            if (!this.imageStudioLastExport?.filename) {
                return;
            }
            try {
                await api.post(`/projects/${this.projectId}/image-studio/push-library`, {
                    filename: this.imageStudioLastExport.filename,
                });
                this.message = 'Imagem adicionada à biblioteca do projeto';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao enviar para biblioteca';
            }
        },

        async imageStudioApplyTemplate(template) {
            if (!template?.slug) {
                return;
            }
            if (!this.imageStudioEngine?.canvas) {
                await this.initImageStudio();
            }
            const full = (this.imageStudioTemplates || []).find((t) => t.slug === template.slug) || template;
            if (this.imageStudioLayers?.length && !confirm('Aplicar layout substitui o conteúdo do canvas. Continuar?')) {
                return;
            }
            if (full.preset && full.preset !== this.imageStudioPreset) {
                const presetMeta = this.imageStudioPresets.find((p) => p.slug === full.preset);
                this.imageStudioPreset = full.preset;
                if (presetMeta) {
                    this.imageStudioEngine.setSize(presetMeta.width, presetMeta.height);
                }
            }
            this.imageStudioEngine.applyTemplate(full);
            if (full.background?.color) {
                this.imageStudioBgColor = full.background.color;
                this.imageStudioBgOpacity = full.background.opacity ?? 100;
            }
            this.fitImageStudioCanvas();
            this.refreshImageStudioLayers();
            this.scheduleImageStudioSave();
            this.message = `Layout "${full.name}" aplicado — veja contorno e sangrias no canvas`;
        },

        async imageStudioImportFromLibrary(item) {
            if (!item?.preview_url && !item?.download_url) {
                return;
            }
            const url = item.preview_url || item.download_url;
            await this.imageStudioEngine?.addImageFromUrl(url, item.title || 'Biblioteca');
            this.refreshImageStudioLayers();
        },

        async imageStudioImportFromLibraryItem(item) {
            if (!this.imageStudioReady) {
                this.switchTab('image_studio');
                await this.$nextTick();
                if (!this.imageStudioReady) {
                    await this.initImageStudio();
                }
            } else {
                this.switchTab('image_studio');
            }
            try {
                await this.imageStudioImportFromLibrary(item);
                this.message = 'Imagem adicionada ao Image Studio';
            } catch (e) {
                this.error = e.message || 'Erro ao importar para o Image Studio';
            }
        },

        async imageStudioImportFromAsset(asset) {
            if (!asset?.id) {
                return;
            }
            const url = `/api/projects/${this.projectId}/assets/${asset.id}`;
            if (!this.imageStudioReady) {
                this.switchTab('image_studio');
                await this.$nextTick();
                if (!this.imageStudioReady) {
                    await this.initImageStudio();
                }
            }
            await this.imageStudioEngine?.addImageFromUrl(url, asset.item_title || 'Asset');
            this.refreshImageStudioLayers();
            this.message = 'Asset adicionado ao canvas';
        },
    };
}
