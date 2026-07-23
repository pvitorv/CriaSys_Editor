import { Canvas, FabricText, IText, FabricImage, Rect, Circle, Ellipse, Line, loadSVGFromURL, util, filters, Shadow, ActiveSelection } from 'fabric';
import { writePsdBuffer } from 'ag-psd';
import { jsPDF } from 'jspdf';
import {
    EMOJI_FONT_STACK,
    addCanvasObject,
    centerObjectOnCanvas,
    createShapeFromSpec,
} from './imageStudioShapes';
import {
    buildTextStyleFromFont,
    ensureFontLoaded,
    findFontBySlug,
    resolveFontWeight,
    fontCssFamily,
    isTextLikeType,
    normalizeColorInput,
    preloadIconFontCdns,
    preloadStarterGoogleFonts,
    FALLBACK_FONTS,
} from './imageStudioTextFonts';

const OBJECT_SCALE_MIN_PERCENT = 5;
const OBJECT_SCALE_MAX_PERCENT = 600;

const FALLBACK_PRESETS = [
    { slug: 'yt_thumb_hd', name: 'Thumbnail Full HD', group: 'youtube', group_label: 'YouTube', width: 1920, height: 1080, icon: '▶', aspect: '16:9' },
    { slug: 'yt_thumb', name: 'Thumbnail 16:9', group: 'youtube', group_label: 'YouTube', width: 1280, height: 720, icon: '▶', aspect: '16:9' },
    { slug: 'yt_shorts', name: 'Shorts capa 9:16', group: 'youtube', group_label: 'YouTube', width: 1080, height: 1920, icon: '▲', aspect: '9:16' },
    { slug: 'ig_feed_square', name: 'Feed quadrado 1:1', group: 'instagram', group_label: 'Instagram', width: 1080, height: 1080, icon: '◎', aspect: '1:1' },
    { slug: 'ig_story', name: 'Story 9:16', group: 'instagram', group_label: 'Instagram', width: 1080, height: 1920, icon: '▲', aspect: '9:16' },
    { slug: 'tt_video', name: 'Vídeo / capa 9:16', group: 'tiktok', group_label: 'TikTok', width: 1080, height: 1920, icon: '♪', aspect: '9:16' },
    { slug: 'ratio_16_9_hd', name: 'Paisagem 16:9 — 1280', group: 'ratios', group_label: 'Proporções genéricas', width: 1280, height: 720, icon: '▭', aspect: '16:9' },
];

const DEFAULT_CANVAS_FALLBACK = { slug: 'yt_thumb', width: 1280, height: 720, aspect: '16:9' };

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

function isFabricShape(obj) {
    if (!obj || obj.criasysGuide) {
        return false;
    }

    return !isFabricText(obj) && !isFabricImage(obj);
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

function parseHexColor(hex) {
    let h = String(hex || '#ffffff').replace('#', '').trim();
    if (h.length === 3) {
        h = h.split('').map((c) => c + c).join('');
    }
    if (h.length !== 6) {
        return { r: 255, g: 255, b: 255 };
    }

    return {
        r: parseInt(h.substring(0, 2), 16),
        g: parseInt(h.substring(2, 4), 16),
        b: parseInt(h.substring(4, 6), 16),
    };
}

function parseCanvasBackgroundState(backgroundColor, fallbackColor = '#ffffff') {
    const bg = backgroundColor;
    if (!bg || bg === 'transparent') {
        return { color: fallbackColor, transparency: 100 };
    }
    if (typeof bg === 'string' && bg.startsWith('rgba')) {
        const match = bg.match(/rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)/i);
        if (match) {
            const alpha = parseFloat(match[4]);
            const r = Number(match[1]).toString(16).padStart(2, '0');
            const g = Number(match[2]).toString(16).padStart(2, '0');
            const b = Number(match[3]).toString(16).padStart(2, '0');
            return {
                color: `#${r}${g}${b}`,
                transparency: Math.round((1 - alpha) * 100),
            };
        }
    }

    return { color: bg, transparency: 0 };
}

async function canvasToBlob(canvas, mime, quality = 0.92) {
    return new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob), mime, quality);
    });
}

function drawCoverMedia(ctx, media, destW, destH) {
    const sw = media.videoWidth || media.width || 1;
    const sh = media.videoHeight || media.height || 1;
    const scale = Math.max(destW / sw, destH / sh);
    const dw = sw * scale;
    const dh = sh * scale;
    ctx.drawImage(media, (destW - dw) / 2, (destH - dh) / 2, dw, dh);
}

async function loadVideoFrameCanvas(url) {
    return new Promise((resolve, reject) => {
        const video = document.createElement('video');
        video.crossOrigin = 'anonymous';
        video.preload = 'auto';
        video.muted = true;
        video.playsInline = true;
        const fail = () => {
            video.src = '';
            reject(new Error('video'));
        };
        video.addEventListener('error', fail, { once: true });
        video.addEventListener('loadeddata', () => {
            const seekTo = Math.min(0.5, Math.max(0, (video.duration || 1) * 0.05));
            video.currentTime = Number.isFinite(seekTo) ? seekTo : 0;
        }, { once: true });
        video.addEventListener('seeked', () => {
            const c = document.createElement('canvas');
            c.width = Math.max(1, video.videoWidth || 1);
            c.height = Math.max(1, video.videoHeight || 1);
            c.getContext('2d').drawImage(video, 0, 0);
            video.src = '';
            resolve(c);
        }, { once: true });
        video.src = url;
    });
}

async function loadMediaDrawable(url, isVideo = false) {
    if (!url) {
        return null;
    }
    if (isVideo) {
        return loadVideoFrameCanvas(url);
    }

    return loadHtmlImage(url);
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
        this._bgColor = '#ffffff';
        this._bgTransparency = 0;
        this._underlaySlideIndex = null;
        this._underlayEnabled = true;
    }

    getBackgroundState() {
        return {
            color: this._bgColor || '#ffffff',
            transparency: this._bgTransparency ?? 0,
        };
    }

    getBackgroundPaint() {
        const transparency = Math.max(0, Math.min(100, Number(this._bgTransparency) || 0));
        if (transparency >= 100) {
            return null;
        }
        const { r, g, b } = parseHexColor(this._bgColor);
        const alpha = transparency <= 0 ? 1 : 1 - (transparency / 100);

        return { r, g, b, a: alpha };
    }

    syncBackgroundUiState() {
        const parsed = parseCanvasBackgroundState(this.canvas?.backgroundColor, this._bgColor);
        this._bgColor = parsed.color;
        this._bgTransparency = parsed.transparency;

        return this.getBackgroundState();
    }

    getUnderlayState() {
        return {
            slideIndex: this._underlaySlideIndex,
            enabled: this._underlayEnabled !== false,
        };
    }

    setUnderlayState(slideIndex, enabled = true) {
        const idx = slideIndex === null || slideIndex === undefined || Number(slideIndex) < 0
            ? null
            : Math.max(0, Number(slideIndex) || 0);
        this._underlaySlideIndex = idx;
        this._underlayEnabled = enabled !== false;
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
            padding: 8,
            transparentCorners: false,
            hasControls: true,
            hasBorders: true,
            centeredRotation: true,
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
        const rotateOffset = Math.min(72, Math.max(36, Math.round(48 / z)));
        if (obj.controls?.mtr) {
            obj.controls.mtr.offsetY = -rotateOffset;
            obj.controls.mtr.withConnection = true;
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
            controlsAboveOverlay: true,
        });
        this.canvas.on('object:scaling', (e) => {
            if (e.target) {
                this.clampObjectScale(e.target);
            }
            this.canvas?.requestRenderAll();
            this.notifyChange();
        });
        this.canvas.on('object:rotating', () => this.notifyChange());
        this.canvas.on('object:modified', (e) => {
            if (e.target) {
                this.clampObjectScale(e.target);
            }
            this.emitChange();
        });
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

    clampObjectScale(obj) {
        if (!obj || obj.criasysGuide) {
            return false;
        }
        const sx = Math.abs(obj.scaleX ?? 1) || 1;
        const sy = Math.abs(obj.scaleY ?? 1) || 1;
        const ratio = sy / sx;
        const currentPercent = ((sx + sy) / 2) * 100;
        const clampedPercent = Math.max(
            OBJECT_SCALE_MIN_PERCENT,
            Math.min(OBJECT_SCALE_MAX_PERCENT, currentPercent)
        );
        if (Math.abs(clampedPercent - currentPercent) < 0.5) {
            return false;
        }
        const p = clampedPercent / 100;
        const signX = (obj.scaleX ?? 1) < 0 ? -1 : 1;
        const signY = (obj.scaleY ?? 1) < 0 ? -1 : 1;
        if (Math.abs(ratio - 1) < 0.02) {
            obj.set({ scaleX: signX * p, scaleY: signY * p });
        } else {
            obj.set({ scaleX: signX * p, scaleY: signY * p * ratio });
        }
        obj.setCoords();
        return true;
    }

    setActiveObjectScalePercent(percent) {
        const obj = this.canvas?.getActiveObject();
        if (!obj || obj.criasysGuide) {
            return;
        }
        const sx = Math.abs(obj.scaleX ?? 1) || 1;
        const sy = Math.abs(obj.scaleY ?? 1) || 1;
        const ratio = sy / sx;
        const p = Math.max(
            OBJECT_SCALE_MIN_PERCENT,
            Math.min(OBJECT_SCALE_MAX_PERCENT, Number(percent) || 100)
        ) / 100;
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

    normalizeObjectAngle(degrees) {
        let angle = Math.round(Number(degrees) || 0);
        while (angle < 0) {
            angle += 360;
        }
        while (angle >= 360) {
            angle -= 360;
        }

        return angle;
    }

    getActiveObjectAngle() {
        const obj = this.canvas?.getActiveObject();
        if (!obj || obj.criasysGuide) {
            return 0;
        }

        return this.normalizeObjectAngle(obj.angle ?? 0);
    }

    setActiveObjectAngle(degrees) {
        const obj = this.canvas?.getActiveObject();
        if (!obj || obj.criasysGuide) {
            return;
        }
        obj.set({ angle: this.normalizeObjectAngle(degrees) });
        obj.setCoords();
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    nudgeActiveObjectAngle(deltaDegrees) {
        this.setActiveObjectAngle(this.getActiveObjectAngle() + (Number(deltaDegrees) || 0));
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

    setBackgroundColor(color, transparency = 0) {
        if (!this.canvas) {
            return;
        }
        const t = Math.max(0, Math.min(100, Number(transparency) || 0));
        this._bgColor = color || '#ffffff';
        this._bgTransparency = t;

        if (t >= 100) {
            this.canvas.backgroundColor = 'transparent';
        } else if (t <= 0) {
            this.canvas.backgroundColor = this._bgColor;
        } else {
            const { r, g, b } = parseHexColor(this._bgColor);
            const alpha = 1 - (t / 100);
            this.canvas.backgroundColor = `rgba(${r},${g},${b},${alpha})`;
        }
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    applyBackgroundState(color, transparency) {
        this.setBackgroundColor(color, transparency);
    }

    async loadFromJSON(json) {
        if (!this.canvas || !json) {
            return;
        }
        const payload = typeof json === 'string' ? JSON.parse(json) : { ...json };
        const savedBg = payload.criasysBackground;
        if (savedBg && typeof savedBg === 'object') {
            delete payload.criasysBackground;
        }
        const savedUnderlay = payload.criasysUnderlay;
        if (savedUnderlay && typeof savedUnderlay === 'object') {
            delete payload.criasysUnderlay;
        }

        this.historyPaused = true;
        await this.canvas.loadFromJSON(payload);
        this.canvas.getObjects().forEach((obj) => {
            this.configureSelectableObject(obj);
            if (obj.criasysFilters && isFabricImage(obj)) {
                this.applyFiltersToObject(obj, obj.criasysFilters);
            }
        });

        if (savedBg?.color !== undefined) {
            const transparency = savedBg.transparency ?? savedBg.opacity;
            if (savedBg.opacity !== undefined && savedBg.transparency === undefined) {
                this.applyBackgroundState(savedBg.color, Math.max(0, 100 - Number(savedBg.opacity)));
            } else {
                this.applyBackgroundState(savedBg.color, transparency ?? 0);
            }
        } else {
            this.syncBackgroundUiState();
        }

        if (savedUnderlay && typeof savedUnderlay === 'object') {
            this.setUnderlayState(savedUnderlay.slideIndex, savedUnderlay.enabled);
        }

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
        const base = this.canvas?.toObject?.() ?? this.canvas?.toJSON?.() ?? null;
        if (!base) {
            return null;
        }

        return {
            ...base,
            criasysBackground: {
                color: this._bgColor || '#ffffff',
                transparency: this._bgTransparency ?? 0,
            },
            criasysUnderlay: this.getUnderlayState(),
        };
    }

    async renderExportCanvas(multiplier = 1, options = {}) {
        const { underlayUrl = null, underlayIsVideo = false } = options;
        const w = this.canvas.getWidth();
        const h = this.canvas.getHeight();
        const out = document.createElement('canvas');
        out.width = w * multiplier;
        out.height = h * multiplier;
        const ctx = out.getContext('2d');
        ctx.clearRect(0, 0, out.width, out.height);

        if (underlayUrl) {
            try {
                const media = await loadMediaDrawable(underlayUrl, underlayIsVideo);
                if (media) {
                    drawCoverMedia(ctx, media, out.width, out.height);
                }
            } catch {
                /* slide sem mídia utilizável */
            }
        }

        const paint = this.getBackgroundPaint();
        if (paint) {
            ctx.fillStyle = `rgba(${paint.r},${paint.g},${paint.b},${paint.a})`;
            ctx.fillRect(0, 0, out.width, out.height);
        }

        const savedBg = this.canvas.backgroundColor;
        this.canvas.backgroundColor = 'transparent';
        this.canvas.requestRenderAll();
        const objectsLayer = this.canvas.toCanvasElement(multiplier);
        this.canvas.backgroundColor = savedBg;
        this.canvas.requestRenderAll();
        ctx.drawImage(objectsLayer, 0, 0);

        return out;
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
            fill: normalizeColorInput(options.fill, '#ffffff'),
            fontWeight: options.fontWeight ?? 'bold',
            fontStyle: options.fontStyle || 'normal',
            underline: !!options.underline,
            linethrough: !!options.linethrough,
            stroke: (options.strokeWidth || 0) > 0 ? normalizeColorInput(options.stroke, '#000000') : null,
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
            fill: normalizeColorInput(obj.fill, '#ffffff'),
            stroke: normalizeColorInput(obj.stroke, '#000000'),
            strokeWidth: Math.max(0, parseFloat(obj.strokeWidth) || 0),
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

        this.applyTextPaint(object, style);

        object.set({
            fontStyle,
            fontWeight,
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
            originX: 'center',
            originY: 'center',
            left: this.designWidth / 2,
            top: this.designHeight / 2,
            width: this.designWidth * 0.7,
            height: this.designHeight * 0.25,
            fill: color,
            opacity: opacity / 100,
            name: 'Retângulo',
            criasysId: 'rect_' + Date.now(),
        });
        this.configureSelectableObject(rect);
        this.canvas.add(rect);
        this.canvas.setActiveObject(rect);
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    addCircle(color = '#3b82f6', opacity = 80) {
        const size = Math.min(this.designWidth, this.designHeight) * 0.25;
        const circle = new Circle({
            originX: 'center',
            originY: 'center',
            left: this.designWidth / 2,
            top: this.designHeight / 2,
            radius: size / 2,
            fill: color,
            opacity: opacity / 100,
            name: 'Círculo',
            criasysId: 'circle_' + Date.now(),
        });
        this.configureSelectableObject(circle);
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
        if (!spec || !this.canvas) {
            return;
        }

        const type = spec.type || spec.kind;

        if (type === 'svg_icon') {
            return this.addSvgIconFromSpec(spec);
        }

        if (type === 'emoji' || type === 'sticker') {
            const char = spec.char || spec.icon || '★';
            const isColorEmoji = /[\u{1F300}-\u{1FAFF}\u2600-\u27BF]/u.test(char);
            this.addText(char, {
                fontSize: spec.fontSize || 120,
                fill: spec.fill || '#ffffff',
                fontFamily: isColorEmoji ? EMOJI_FONT_STACK : 'Impact, Arial Black, sans-serif',
                fontWeight: isColorEmoji ? 'normal' : 'bold',
                textAlign: 'center',
                name: spec.name || 'Emoji',
                fontSlug: null,
                charSpacing: spec.charSpacing || 0,
            });
            const active = this.getActiveObject();
            if (active && isFabricText(active)) {
                centerObjectOnCanvas(active, this.designWidth, this.designHeight);
                this.canvas.requestRenderAll();
            }
            return;
        }

        const shape = createShapeFromSpec(spec, this.designWidth, this.designHeight);
        if (shape) {
            addCanvasObject(
                this.canvas,
                shape,
                this.designWidth,
                this.designHeight,
                (obj) => this.configureSelectableObject(obj),
            );
            this.emitChange();
            return;
        }

        this.emitChange();
    }

    async applyTemplate(template, fontMap = {}) {
        if (!this.canvas || !template) {
            return;
        }
        const width = this.canvas.getWidth();
        const height = this.canvas.getHeight();
        this.canvas.clear();
        const bg = template.background || {};
        this.setBackgroundColor(bg.color || '#ffffff', bg.transparency ?? Math.max(0, 100 - (bg.opacity ?? 100)));

        const fontList = Object.values(fontMap || {});

        for (let idx = 0; idx < (template.objects || []).length; idx += 1) {
            const spec = template.objects[idx];
            const kind = spec.kind || spec.type;

            if (kind === 'rect') {
                const rect = new Rect({
                    left: (spec.x ?? 0) * width,
                    top: (spec.y ?? 0) * height,
                    width: (spec.w ?? 0.5) * width,
                    height: (spec.h ?? 0.5) * height,
                    fill: spec.fill || '#ef4444',
                    opacity: (spec.opacity ?? 100) / 100,
                    rx: spec.rx ?? 0,
                    ry: spec.ry ?? spec.rx ?? 0,
                    name: spec.name || 'Retângulo',
                    criasysId: 'tpl_rect_' + idx + '_' + Date.now(),
                });
                this.canvas.add(rect);
                continue;
            }

            if (kind === 'circle') {
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
                continue;
            }

            if (kind === 'text') {
                await this.addTemplateTextObject(spec, idx, width, height, fontMap, fontList);
            }
        }

        this.configureAllObjects();
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    async addTemplateTextObject(spec, idx, width, height, fontMap, fontList) {
        const fontMeta = spec.fontSlug
            ? (fontMap[spec.fontSlug] || findFontBySlug(fontList, spec.fontSlug))
            : null;
        const bold = spec.fontWeight === 'bold' || spec.fontWeight === 700 || spec.fontWeight === '700';

        if (fontMeta) {
            await ensureFontLoaded(fontMeta, { bold, italic: spec.italic });
        }

        const fontFamily = fontMeta
            ? fontCssFamily(fontMeta)
            : (spec.fontFamily || 'Impact, Arial Black, sans-serif');

        const textObj = new IText(spec.text || 'Texto', {
            left: (spec.x ?? 0.5) * width,
            top: (spec.y ?? 0.5) * height,
            fontFamily,
            fontSize: spec.fontSize || 48,
            fill: spec.fill || '#ffffff',
            originX: spec.originX || 'left',
            originY: spec.originY || 'top',
            fontWeight: fontMeta ? resolveFontWeight(fontMeta, bold) : (spec.fontWeight || 'bold'),
            fontStyle: spec.italic ? 'italic' : 'normal',
            textAlign: spec.textAlign || 'center',
            charSpacing: spec.charSpacing ?? 0,
            lineHeight: spec.lineHeight ?? 1.05,
            stroke: (spec.strokeWidth || 0) > 0 ? (spec.stroke || '#000000') : '',
            strokeWidth: spec.strokeWidth || 0,
            name: spec.name || 'Texto',
            criasysId: 'tpl_text_' + idx + '_' + Date.now(),
            criasysFontSlug: spec.fontSlug || null,
        });

        if (fontMeta) {
            textObj.set(buildTextStyleFromFont(fontMeta, { bold, italic: spec.italic }));
        }

        if (spec.shadow) {
            textObj.set('shadow', new Shadow({
                color: spec.shadowColor || '#000000',
                blur: spec.shadowBlur ?? 10,
                offsetX: spec.shadowOffsetX ?? 2,
                offsetY: spec.shadowOffsetY ?? 2,
            }));
        }

        this.canvas.add(textObj);
        textObj.setCoords();

        if (spec.textBackground) {
            this.addTemplateTextBackground(textObj, spec.textBackground, idx);
        }
    }

    addTemplateTextBackground(textObj, bgSpec, idx) {
        const bounds = textObj.getBoundingRect();
        const padX = bgSpec.padX ?? 16;
        const padY = bgSpec.padY ?? 10;
        const rect = new Rect({
            left: bounds.left - padX,
            top: bounds.top - padY,
            width: bounds.width + (padX * 2),
            height: bounds.height + (padY * 2),
            fill: bgSpec.fill || '#fde047',
            opacity: (bgSpec.opacity ?? 100) / 100,
            rx: bgSpec.rx ?? 6,
            ry: bgSpec.ry ?? bgSpec.rx ?? 6,
            name: (textObj.name || 'Texto') + ' · fundo',
            criasysId: 'tpl_tbg_' + idx + '_' + Date.now(),
        });
        this.configureSelectableObject(rect);
        const textIndex = this.canvas.getObjects().indexOf(textObj);
        this.canvas.insertAt(Math.max(0, textIndex), rect);
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
            originX: 'center',
            originY: 'center',
            left: this.designWidth / 2,
            top: this.designHeight / 2,
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

    getShapeStyleFromObject(obj) {
        if (!isFabricShape(obj)) {
            return null;
        }
        const isLine = String(obj.type || '').toLowerCase() === 'line';
        const strokeWidth = Math.max(0, parseFloat(obj.strokeWidth) || 0);
        let fill = obj.fill;
        if (fill === null || fill === undefined || fill === 'transparent') {
            fill = '';
        } else {
            fill = normalizeColorInput(fill, '#ffffff');
        }

        return {
            fill,
            stroke: normalizeColorInput(obj.stroke, '#ffffff'),
            strokeWidth,
            isLine,
        };
    }

    applyShapePaint(object, style) {
        if (!isFabricShape(object) || !style) {
            return;
        }
        const strokeWidth = Math.max(0, parseFloat(style.strokeWidth) || 0);
        const stroke = strokeWidth > 0
            ? normalizeColorInput(style.stroke, '#ffffff')
            : '';
        const updates = { stroke, strokeWidth };

        if (!style.isLine) {
            updates.fill = style.fill
                ? normalizeColorInput(style.fill, '#ffffff')
                : 'transparent';
        }

        object.set(updates);
        object.setCoords();
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    getActiveObject() {
        return this.canvas?.getActiveObject() ?? null;
    }

    getActiveTextObject() {
        const obj = this.getActiveObject();
        if (!obj) {
            return null;
        }

        if (isFabricText(obj)) {
            return obj;
        }

        if (obj instanceof ActiveSelection || String(obj.type || '').toLowerCase() === 'activeselection') {
            const nested = typeof obj.getObjects === 'function' ? obj.getObjects() : [];
            return nested.find((item) => isFabricText(item)) ?? null;
        }

        return null;
    }

    applyTextPaint(object, style) {
        if (!isFabricText(object) || !style) {
            return;
        }

        const fill = normalizeColorInput(style.fill, '#ffffff');
        const strokeWidth = Math.max(0, parseFloat(style.strokeWidth) || 0);
        const stroke = strokeWidth > 0
            ? normalizeColorInput(style.stroke, '#000000')
            : null;

        object.set({
            fill,
            stroke,
            strokeWidth,
        });

        if (typeof object.setSelectionStyles === 'function' && object.text?.length) {
            object.setSelectionStyles({
                fill,
                stroke,
                strokeWidth,
            }, 0, object.text.length);
        }

        object.set('styles', {});
    }

    async exportBlob(format = 'png', quality = 0.92, options = {}) {
        if (!this.canvas) {
            return null;
        }
        const {
            frameOverlayUrl = null,
            frameVisible = true,
            underlayUrl = null,
            underlayIsVideo = false,
        } = options;
        const exportOpts = { underlayUrl, underlayIsVideo };
        if (format === 'svg') {
            const svg = this.canvas.toSVG();
            return new Blob([svg], { type: 'image/svg+xml' });
        }
        if (format === 'json') {
            return new Blob([JSON.stringify(this.toJSON(), null, 2)], { type: 'application/json' });
        }
        if (format === 'psd') {
            return await this.exportPsdBlob(frameOverlayUrl, frameVisible, exportOpts);
        }
        if (format === 'pdf') {
            return this.exportPdfBlob(quality, frameOverlayUrl, frameVisible, exportOpts);
        }
        const mime = format === 'jpg' ? 'image/jpeg' : 'image/png';
        let blob;
        if (format === 'jpg') {
            const exportCanvas = await this.renderExportCanvas(1, exportOpts);
            const jpegOff = document.createElement('canvas');
            jpegOff.width = exportCanvas.width;
            jpegOff.height = exportCanvas.height;
            const ctx = jpegOff.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, jpegOff.width, jpegOff.height);
            ctx.drawImage(exportCanvas, 0, 0);
            blob = await canvasToBlob(jpegOff, mime, quality);
        } else if (frameOverlayUrl && frameVisible !== false) {
            let dataUrl = await compositeFrameOnCanvasDataUrl(this.canvas, frameOverlayUrl, frameVisible);
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
            const res = await fetch(dataUrl);
            blob = await res.blob();
        } else {
            const exportCanvas = await this.renderExportCanvas(1, exportOpts);
            blob = await canvasToBlob(exportCanvas, mime, quality);
        }

        return blob;
    }

    async exportPsdBlob(frameOverlayUrl = null, frameVisible = true, exportOpts = {}) {
        const w = this.canvas.getWidth();
        const h = this.canvas.getHeight();
        const layers = [];

        const { underlayUrl = null, underlayIsVideo = false } = exportOpts;
        if (underlayUrl) {
            try {
                const media = await loadMediaDrawable(underlayUrl, underlayIsVideo);
                if (media) {
                    const underCanvas = document.createElement('canvas');
                    underCanvas.width = w;
                    underCanvas.height = h;
                    drawCoverMedia(underCanvas.getContext('2d'), media, w, h);
                    layers.push({ name: 'Slide', canvas: underCanvas });
                }
            } catch {
                /* skip */
            }
        }

        const paint = this.getBackgroundPaint();
        if (paint) {
            const bgCanvas = document.createElement('canvas');
            bgCanvas.width = w;
            bgCanvas.height = h;
            const ctx = bgCanvas.getContext('2d');
            ctx.fillStyle = `rgba(${paint.r},${paint.g},${paint.b},${paint.a})`;
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

    async exportPdfBlob(quality = 0.92, frameOverlayUrl = null, frameVisible = true, exportOpts = {}) {
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
            const exportCanvas = await this.renderExportCanvas(1, exportOpts);
            dataUrl = exportCanvas.toDataURL('image/jpeg', quality);
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
        const pad = 12;
        const scale = Math.min(
            (containerWidth - pad) / this.designWidth,
            (containerHeight - pad) / this.designHeight
        );

        return this.applyViewportZoom(Math.max(0.08, Math.min(1, scale)));
    }
}

export function imageStudioMethods() {
    return {
        imageStudioReady: false,
        imageStudioPresets: [],
        imageStudioPrimaryFormatDefs: [],
        imageStudioGroupOrder: [],
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
        imageStudioPreset: 'custom',
        imageStudioCustomWidth: 1920,
        imageStudioCustomHeight: 1080,
        imageStudioDimensionsModalOpen: false,
        imageStudioDimensionsFilter: '',
        imageStudioPresetPlatformMap: {},
        imageStudioEngine: null,
        imageStudioLayers: [],
        imageStudioSaving: false,
        imageStudioLastExport: null,
        imageStudioBgColor: '#ffffff',
        imageStudioBgTransparency: 0,
        imageStudioUnderlaySlideIndex: -1,
        imageStudioUnderlayEnabled: true,
        imageStudioSelectedObject: null,
        imageStudioObjectScale: 100,
        imageStudioObjectAngle: 0,
        imageStudioShapeFill: '#ffffff',
        imageStudioShapeStroke: '#ffffff',
        imageStudioShapeStrokeWidth: 0,
        imageStudioShapeIsLine: false,
        imageStudioZoom: 100,
        imageStudioShowFormatGuides: true,
        imageStudioBgRemoving: false,
        imageStudioFilters: { ...DEFAULT_FILTER_STATE },
        imageStudioShowGrid: false,
        imageStudioSnapGrid: false,
        imageStudioGridSize: 20,
        imageStudioCanUndo: false,
        imageStudioCanRedo: false,
        imageStudioElements: [],
        imageStudioElementGroups: {},
        imageStudioElementFilter: '',
        imageStudioElementFilterGroup: '',
        imageStudioElementsModalOpen: false,
        _imageStudioElementsModalShown: false,
        imageStudioExpanded: false,
        imageStudioLocalWatch: null,

        normalizeImageStudioElementList(source) {
            if (Array.isArray(source)) {
                return source;
            }
            if (source && typeof source === 'object') {
                return Object.values(source);
            }

            return [];
        },

        filterImageStudioElements() {
            const list = this.normalizeImageStudioElementList(this.imageStudioElements);
            const q = (this.imageStudioElementFilter || '').trim().toLowerCase();
            const groupFilter = (this.imageStudioElementFilterGroup || '').trim();

            return list.filter((el) => {
                if (!el || typeof el !== 'object') {
                    return false;
                }
                if (groupFilter) {
                    const matchesGroup = groupFilter === 'formas'
                        ? String(el.group || '').startsWith('formas')
                        : el.group === groupFilter;
                    if (!matchesGroup) {
                        return false;
                    }
                }
                if (q) {
                    const hay = `${el.name || ''} ${el.group || ''} ${el.char || ''} ${el.icon || ''}`.toLowerCase();
                    if (!hay.includes(q)) {
                        return false;
                    }
                }

                return true;
            });
        },

        imageStudioElementGroupList() {
            const groups = {};
            this.filterImageStudioElements().forEach((el) => {
                const key = this.imageStudioElementGroups?.[el.group] || el.group || 'Outros';
                if (!groups[key]) {
                    groups[key] = [];
                }
                groups[key].push(el);
            });

            return Object.entries(groups).map(([name, items]) => ({ name, items }));
        },

        imageStudioElementsFilteredCount() {
            return this.filterImageStudioElements().length;
        },

        imageStudioElementQuickGroups() {
            return [
                { id: '', label: 'Todos' },
                { id: 'icones', label: 'Ícones' },
                { id: 'emojis', label: 'Emojis' },
                { id: 'blobs', label: 'Slimes' },
                { id: 'formas', label: 'Formas' },
                { id: 'formas_molduras', label: 'Molduras' },
                { id: 'formas_3d', label: '3D' },
                { id: 'formas_extras', label: 'Decor' },
                { id: 'adesivos', label: 'Adesivos' },
                { id: 'linhas', label: 'Linhas' },
            ];
        },

        imageStudioIconGlyphClass(font) {
            const map = {
                fa_regular: 'is-ic-fa-regular',
                fa_brands: 'is-ic-fa-brands',
                material_symbols: 'is-ic-material',
                fa_solid: 'is-ic-fa-solid',
            };
            return map[font] || 'is-ic-fa-solid';
        },

        syncImageStudioElementsCatalog() {
            const base = this.normalizeImageStudioElementList(this.imageStudioElements)
                .filter((el) => el?.type !== 'icon_glyph');
            const glyphs = (this.imageStudioIconGlyphs || []).map((g) => ({
                type: 'icon_glyph',
                slug: g.slug || ('icon_' + String(g.char || '').charCodeAt(0)),
                name: g.label || g.slug || 'Ícone',
                char: g.char,
                font: g.font || 'fa_solid',
                group: 'icones',
                icon_group: g.group || '',
            }));
            const slugs = new Set(base.map((el) => el.slug));
            const merged = [...base];
            glyphs.forEach((g) => {
                if (g.slug && !slugs.has(g.slug)) {
                    merged.push(g);
                    slugs.add(g.slug);
                }
            });
            this.imageStudioElements = merged;
            if (!this.imageStudioElementGroups?.icones) {
                this.imageStudioElementGroups = {
                    ...(this.imageStudioElementGroups || {}),
                    icones: 'Ícones',
                };
            }
        },

        toggleImageStudioExpanded() {
            this.imageStudioExpanded = !this.imageStudioExpanded;
            this.$nextTick(() => this.fitImageStudioCanvas());
        },

        closeImageStudioExpanded() {
            if (!this.imageStudioExpanded) {
                return;
            }
            this.imageStudioExpanded = false;
            this.$nextTick(() => this.fitImageStudioCanvas());
        },

        imageStudioOpenElementsModal() {
            this.imageStudioElementFilter = '';
            this.imageStudioElementFilterGroup = '';
            this.imageStudioElementsModalOpen = true;
        },

        imageStudioCloseElementsModal() {
            this.imageStudioElementsModalOpen = false;
        },

        async imageStudioAddElementFromModal(el) {
            await this.imageStudioAddElement(el);
        },

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
            const order = this.imageStudioGroupOrder?.length
                ? this.imageStudioGroupOrder
                : ['YouTube', 'Proporções genéricas', 'Instagram'];
            const sorted = {};
            order.forEach((name) => {
                if (groups[name]?.length) {
                    sorted[name] = groups[name];
                }
            });
            Object.keys(groups).forEach((name) => {
                if (!sorted[name]) {
                    sorted[name] = groups[name];
                }
            });
            return sorted;
        },

        imageStudioPrimaryFormats() {
            const defs = this.imageStudioPrimaryFormatDefs?.length
                ? this.imageStudioPrimaryFormatDefs
                : [
                    { slug: 'yt_thumb_hd', label: 'YouTube Full HD', aspect: '16:9' },
                    { slug: 'yt_thumb', label: 'YouTube Thumbnail', aspect: '16:9' },
                    { slug: 'yt_shorts', label: 'YouTube Shorts', aspect: '9:16' },
                ];
            return defs.map((def) => {
                const preset = (this.imageStudioPresets || []).find((p) => p.slug === def.slug);
                if (!preset) {
                    return null;
                }
                return {
                    slug: def.slug,
                    label: def.label || preset.name,
                    aspect: def.aspect || preset.aspect,
                    width: preset.width,
                    height: preset.height,
                };
            }).filter(Boolean);
        },

        imageStudioCanvasAspectLabel() {
            const w = this.imageStudioEngine?.designWidth || this.imageStudioCustomWidth || 1920;
            const h = this.imageStudioEngine?.designHeight || this.imageStudioCustomHeight || 1080;
            if (w === h) {
                return '1:1';
            }
            if (w > h) {
                const ratio = (w / h).toFixed(2);
                return `${ratio}:1`;
            }
            return `1:${(h / w).toFixed(2)}`;
        },

        syncImageStudioCustomDimensionsFromEngine() {
            const w = this.imageStudioEngine?.designWidth;
            const h = this.imageStudioEngine?.designHeight;
            if (w && h) {
                this.imageStudioCustomWidth = w;
                this.imageStudioCustomHeight = h;
            }
        },

        imageStudioReferencePresets() {
            const q = (this.imageStudioDimensionsFilter || '').trim().toLowerCase();
            return (this.imageStudioPresets || [])
                .filter((p) => p.slug !== 'custom')
                .filter((p) => {
                    if (!q) {
                        return true;
                    }
                    return (p.name || '').toLowerCase().includes(q)
                        || (p.group_label || '').toLowerCase().includes(q)
                        || String(p.width).includes(q)
                        || String(p.height).includes(q)
                        || (p.aspect || '').includes(q);
                });
        },

        imageStudioReferencePresetGroups() {
            const groups = {};
            this.imageStudioReferencePresets().forEach((p) => {
                const key = p.group_label || p.group || 'Outros';
                if (!groups[key]) {
                    groups[key] = [];
                }
                groups[key].push(p);
            });
            const order = this.imageStudioGroupOrder?.length
                ? this.imageStudioGroupOrder
                : ['YouTube', 'Proporções genéricas', 'Instagram', 'TikTok', 'Facebook'];
            const sorted = {};
            order.forEach((name) => {
                if (groups[name]?.length) {
                    sorted[name] = groups[name];
                }
            });
            Object.keys(groups).forEach((name) => {
                if (!sorted[name]) {
                    sorted[name] = groups[name];
                }
            });
            return sorted;
        },

        openImageStudioDimensionsModal() {
            this.imageStudioDimensionsModalOpen = true;
        },

        closeImageStudioDimensionsModal() {
            this.imageStudioDimensionsModalOpen = false;
        },

        pickImageStudioReferenceDimensions(preset) {
            if (!preset?.width || !preset?.height) {
                return;
            }
            this.imageStudioCustomWidth = preset.width;
            this.imageStudioCustomHeight = preset.height;
            this.closeImageStudioDimensionsModal();
            this.applyImageStudioCustomDimensions();
            this.message = `Dimensões: ${preset.name} (${preset.width}×${preset.height})`;
        },

        async applyImageStudioCustomDimensions() {
            let w = Math.round(Number(this.imageStudioCustomWidth) || 0);
            let h = Math.round(Number(this.imageStudioCustomHeight) || 0);
            w = Math.min(8000, Math.max(100, w));
            h = Math.min(8000, Math.max(100, h));
            this.imageStudioCustomWidth = w;
            this.imageStudioCustomHeight = h;
            this.imageStudioPreset = 'custom';

            if (!this.imageStudioEngine?.canvas) {
                await this.initImageStudio();
            }
            if (!this.imageStudioEngine?.canvas) {
                return;
            }

            this.imageStudioEngine.setSize(w, h);
            this.fitImageStudioCanvas();
            await this.saveImageStudioDesign();
            this.message = `Canvas ${w}×${h} px (${this.imageStudioCanvasAspectLabel()})`;
        },

        imageStudioCanvasViewportStyle() {
            const w = this.imageStudioEngine?.designWidth || this.imageStudioCustomWidth || 1920;
            const h = this.imageStudioEngine?.designHeight || this.imageStudioCustomHeight || 1080;
            const z = (this.imageStudioZoom || 100) / 100;

            return {
                width: `${Math.ceil(w * z)}px`,
                height: `${Math.ceil(h * z)}px`,
                flexShrink: '0',
            };
        },

        imageStudioCanvasScalerStyle() {
            const w = this.imageStudioEngine?.designWidth || this.imageStudioCustomWidth || 1920;
            const h = this.imageStudioEngine?.designHeight || this.imageStudioCustomHeight || 1080;
            const z = (this.imageStudioZoom || 100) / 100;
            const checker = 'repeating-conic-gradient(#3f3f46 0% 25%, #27272a 0% 50%) 50% / 16px 16px';
            const bg = this.imageStudioShowUnderlayMedia() ? 'transparent' : checker;

            return {
                width: `${w}px`,
                height: `${h}px`,
                transform: `scale(${z})`,
                transformOrigin: 'top left',
                background: bg,
            };
        },

        resolveImageStudioPresetMeta(slug = null) {
            const w = this.imageStudioEngine?.designWidth || this.imageStudioCustomWidth || 1920;
            const h = this.imageStudioEngine?.designHeight || this.imageStudioCustomHeight || 1080;
            return {
                slug: 'custom',
                name: 'Personalizado',
                width: w,
                height: h,
                aspect: this.imageStudioCanvasAspectLabel(),
            };
        },

        get imageStudioCurrentPreset() {
            return this.resolveImageStudioPresetMeta();
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

        seedImageStudioPresetsFromEmbedded() {
            const presets = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-presets');
            const defaults = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-defaults');
            const primary = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-primary-formats');
            const groupOrder = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-group-order');

            if (Array.isArray(presets) && presets.length) {
                this.imageStudioPresets = presets;
            } else if (!this.imageStudioPresets?.length) {
                this.imageStudioPresets = FALLBACK_PRESETS;
            }
            if (Array.isArray(primary) && primary.length) {
                this.imageStudioPrimaryFormatDefs = primary;
            }
            if (Array.isArray(groupOrder) && groupOrder.length) {
                this.imageStudioGroupOrder = groupOrder;
            }
            if (defaults?.preset) {
                this.imageStudioPreset = 'custom';
            }
            if (defaults?.width) {
                this.imageStudioCustomWidth = defaults.width;
            }
            if (defaults?.height) {
                this.imageStudioCustomHeight = defaults.height;
            }
        },

        seedImageStudioFromEmbedded(meta = {}) {
            this.seedImageStudioPresetsFromEmbedded();
            let fonts = meta.imageStudioFonts;
            let icons = meta.imageStudioIconGlyphs;
            let iconFonts = meta.imageStudioIconFonts;
            let elements = meta.imageStudioElements;
            let elementGroups = meta.imageStudioElementGroups;

            if (!Array.isArray(fonts) || !fonts.length) {
                fonts = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-fonts');
            }
            if (!Array.isArray(icons) || !icons.length) {
                icons = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-icons');
            }
            if (!Array.isArray(iconFonts) || !iconFonts.length) {
                iconFonts = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-icon-fonts');
            }
            if (!Array.isArray(elements) || !elements.length) {
                elements = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-elements');
            }
            if (!elementGroups || !Object.keys(elementGroups).length) {
                elementGroups = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-element-groups');
            }

            if (Array.isArray(fonts) && fonts.length) {
                this.imageStudioFonts = fonts;
            } else if (!this.imageStudioFonts?.length) {
                this.imageStudioFonts = FALLBACK_FONTS;
            }
            if (Array.isArray(icons) && icons.length) {
                this.imageStudioIconGlyphs = icons;
            }
            if (Array.isArray(elements) && elements.length) {
                this.imageStudioElements = elements;
            }
            if (elementGroups && typeof elementGroups === 'object') {
                this.imageStudioElementGroups = elementGroups;
            }
            this.syncImageStudioElementsCatalog();
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
            this.seedImageStudioPresetsFromEmbedded();
            try {
                const { data } = await api.get('/image-studio/catalog');
                if (data.presets?.length) {
                    this.imageStudioPresets = data.presets;
                }
                this.imageStudioGroups = data.groups || {};
                this.imageStudioExportFormats = data.export_formats || [];
                this.imageStudioTemplates = data.templates || [];
                this.imageStudioElements = this.normalizeImageStudioElementList(data.elements);
                this.imageStudioElementGroups = data.element_groups || {};
                this.imageStudioFonts = data.fonts?.length ? data.fonts : FALLBACK_FONTS;
                this.imageStudioFontGroups = data.font_groups || {};
                this.imageStudioIconGlyphs = data.icon_glyphs || [];
                this.buildImageStudioFontMap();
                preloadIconFontCdns(data.icon_fonts || []);
                preloadStarterGoogleFonts(this.imageStudioFonts);
                this.imageStudioBgRemoval = true;
                this.imageStudioPresetPlatformMap = data.preset_platform_map || {};
                if (data.primary_formats?.length) {
                    this.imageStudioPrimaryFormatDefs = data.primary_formats;
                }
                if (data.group_order?.length) {
                    this.imageStudioGroupOrder = data.group_order;
                }
                if (data.defaults?.preset) {
                    this.imageStudioPreset = 'custom';
                }
                if (data.defaults?.width) {
                    this.imageStudioCustomWidth = data.defaults.width;
                }
                if (data.defaults?.height) {
                    this.imageStudioCustomHeight = data.defaults.height;
                }
                if (!this.imageStudioFontMap[this.imageStudioTextFontSlug]) {
                    this.imageStudioTextFontSlug = this.imageStudioFonts[0]?.slug || 'bebas_neue';
                }
                this.syncImageStudioElementsCatalog();
            } catch (e) {
                console.error('Image Studio catalog:', e);
                if (!this.imageStudioPresets?.length) {
                    this.imageStudioPresets = FALLBACK_PRESETS;
                }
                if (!this.imageStudioFonts?.length) {
                    this.imageStudioFonts = FALLBACK_FONTS;
                    this.buildImageStudioFontMap();
                }
                this.error = e.response?.data?.message || 'Catálogo remoto indisponível — formatos locais carregados.';
            }
        },

        async initImageStudio() {
            if (!this.imageStudioElements?.length) {
                const embedded = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-elements');
                if (Array.isArray(embedded) && embedded.length) {
                    this.imageStudioElements = embedded;
                }
                const embeddedGroups = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-element-groups');
                if (embeddedGroups && typeof embeddedGroups === 'object') {
                    this.imageStudioElementGroups = embeddedGroups;
                }
                const embeddedIcons = ImageStudioEngine.readEmbeddedJson('criasys-image-studio-icons');
                if (Array.isArray(embeddedIcons) && embeddedIcons.length) {
                    this.imageStudioIconGlyphs = embeddedIcons;
                }
                this.syncImageStudioElementsCatalog();
            }

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
                const p = this.resolveImageStudioPresetMeta();
                this.imageStudioEngine.init(p.width, p.height, this.imageStudioBgColor);
                this.imageStudioEngine.setBackgroundColor(this.imageStudioBgColor, this.imageStudioBgTransparency);
            }

            this.syncImageStudioBackgroundFromEngine();
            this.syncImageStudioUnderlayFromEngine();
            this.syncImageStudioUnderlayToEngine();
            this.imageStudioEngine.pushHistory();
            this.imageStudioReady = true;
            this.setupImageStudioKeyboard();
            this.setupImageStudioLocalWatch();
            this.setupImageStudioWheelZoom();
            this.fitImageStudioCanvas();
            this.syncImageStudioCustomDimensionsFromEngine();
        },

        async loadImageStudioDesign() {
            let w = this.imageStudioCustomWidth || 1920;
            let h = this.imageStudioCustomHeight || 1080;
            let canvasJson = null;
            try {
                const { data } = await api.get(`/projects/${this.projectId}/image-studio`, {
                    params: { preset: 'custom' },
                });
                if (data.width && data.height) {
                    w = data.width;
                    h = data.height;
                }
                canvasJson = data.canvas;
                this.imageStudioPreset = 'custom';
            } catch (e) {
                this.error = e.response?.data?.message || null;
            }
            this.imageStudioCustomWidth = w;
            this.imageStudioCustomHeight = h;
            this.imageStudioEngine.init(w, h, this.imageStudioBgColor);
            if (canvasJson) {
                try {
                    await this.imageStudioEngine.loadFromJSON(canvasJson);
                } catch {
                    this.imageStudioEngine.setBackgroundColor(this.imageStudioBgColor, this.imageStudioBgTransparency);
                }
            } else {
                this.imageStudioEngine.setBackgroundColor(this.imageStudioBgColor, this.imageStudioBgTransparency);
            }
            this.imageStudioEngine.setSize(w, h);
            this.syncImageStudioCustomDimensionsFromEngine();
            this.syncImageStudioBackgroundFromEngine();
            this.syncImageStudioUnderlayFromEngine();
            this.syncImageStudioUnderlayToEngine();
            this.refreshImageStudioLayers();
        },

        syncImageStudioBackgroundFromEngine() {
            const bg = this.imageStudioEngine?.getBackgroundState();
            if (!bg) {
                return;
            }
            this.imageStudioBgColor = bg.color;
            this.imageStudioBgTransparency = bg.transparency;
        },

        syncImageStudioUnderlayFromEngine() {
            const underlay = this.imageStudioEngine?.getUnderlayState();
            if (!underlay) {
                return;
            }
            this.imageStudioUnderlaySlideIndex = underlay.slideIndex ?? -1;
            this.imageStudioUnderlayEnabled = underlay.enabled !== false;
        },

        syncImageStudioUnderlayToEngine() {
            const slideIndex = Number(this.imageStudioUnderlaySlideIndex) >= 0
                ? Number(this.imageStudioUnderlaySlideIndex)
                : null;
            this.imageStudioEngine?.setUnderlayState(
                slideIndex,
                this.imageStudioUnderlayEnabled
            );
        },

        resolveImageStudioUnderlaySlideIndex() {
            if (Number(this.imageStudioUnderlaySlideIndex) >= 0) {
                const idx = Number(this.imageStudioUnderlaySlideIndex);
                return Math.max(0, Math.min((this.slides?.length || 1) - 1, Number.isFinite(idx) ? idx : 0));
            }
            const selectedIdx = (this.slides || []).findIndex((s) => s.id === this.selectedSlide?.id);
            return selectedIdx >= 0 ? selectedIdx : 0;
        },

        resolveImageStudioUnderlaySlide() {
            const idx = this.resolveImageStudioUnderlaySlideIndex();
            return this.slides?.[idx] ?? null;
        },

        getImageStudioUnderlayImageUrl() {
            if (!this.imageStudioUnderlayEnabled) {
                return null;
            }
            const slide = this.resolveImageStudioUnderlaySlide();
            return slide?.image_url || null;
        },

        getImageStudioUnderlayVideoUrl() {
            if (!this.imageStudioUnderlayEnabled) {
                return null;
            }
            const slide = this.resolveImageStudioUnderlaySlide();
            if (slide?.image_url) {
                return null;
            }
            return slide?.video_url || null;
        },

        imageStudioShowUnderlayMedia() {
            return this.imageStudioUnderlayEnabled
                && !!(this.getImageStudioUnderlayImageUrl() || this.getImageStudioUnderlayVideoUrl());
        },

        buildImageStudioExportOptions() {
            const slide = this.resolveImageStudioUnderlaySlide();
            const underlayUrl = this.imageStudioUnderlayEnabled
                ? (slide?.image_url || slide?.video_url || null)
                : null;

            return {
                underlayUrl,
                underlayIsVideo: !!(underlayUrl && slide?.video_url && !slide?.image_url),
            };
        },

        onImageStudioUnderlayChange() {
            this.syncImageStudioUnderlayToEngine();
            this.scheduleImageStudioSave();
        },

        fitImageStudioCanvas() {
            const wrap = this.$refs.imageStudioCanvasWrap;
            if (!wrap || !this.imageStudioEngine?.canvas) {
                return;
            }
            const z = this.imageStudioEngine.zoomToFit(wrap.clientWidth, wrap.clientHeight);
            this.imageStudioZoom = Math.round(z * 100);
            this.$nextTick(() => {
                this.imageStudioEngine?.canvas?.calcOffset();
            });
        },

        imageStudioSetZoomPercent(percent) {
            if (!this.imageStudioEngine?.canvas) {
                return;
            }
            const z = this.imageStudioEngine.applyViewportZoom(percent / 100);
            this.imageStudioZoom = Math.round(z * 100);
            this.$nextTick(() => {
                this.imageStudioEngine?.canvas?.calcOffset();
            });
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

            if (!wrap._criasysResizeFit) {
                wrap._criasysResizeFit = true;
                const ro = new ResizeObserver(() => {
                    if (this.activeTab === 'image_studio' && this.imageStudioReady) {
                        this.fitImageStudioCanvas();
                    }
                });
                ro.observe(wrap);
            }
        },

        onImageStudioFormatGuidesChange() {
            this.imageStudioEngine?.setFormatGuidesVisible(this.imageStudioShowFormatGuides);
        },

        refreshImageStudioLayers() {
            this.imageStudioLayers = this.imageStudioEngine?.getLayers() || [];
            const raw = this.imageStudioEngine?.getActiveTextObject() || this.imageStudioEngine?.getActiveObject();
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
            this.imageStudioObjectAngle = this.imageStudioEngine?.getActiveObjectAngle() ?? 0;
            this.imageStudioCanUndo = this.imageStudioEngine?.canUndo() ?? false;
            this.imageStudioCanRedo = this.imageStudioEngine?.canRedo() ?? false;
            if (raw && isFabricImage(raw)) {
                this.imageStudioFilters = this.imageStudioEngine.getFilterState(raw);
            }
            if (raw && isFabricShape(raw)) {
                const shapeStyle = this.imageStudioEngine.getShapeStyleFromObject(raw);
                if (shapeStyle) {
                    this.imageStudioShapeFill = shapeStyle.fill || '#ffffff';
                    this.imageStudioShapeStroke = shapeStyle.stroke || '#ffffff';
                    this.imageStudioShapeStrokeWidth = shapeStyle.strokeWidth;
                    this.imageStudioShapeIsLine = shapeStyle.isLine;
                }
            }
        },

        imageStudioOnShapeFillChange() {
            const obj = this.imageStudioEngine?.getActiveObject();
            if (!isFabricShape(obj)) {
                return;
            }
            this.imageStudioEngine.applyShapePaint(obj, {
                fill: this.imageStudioShapeFill,
                stroke: this.imageStudioShapeStroke,
                strokeWidth: this.imageStudioShapeStrokeWidth,
                isLine: this.imageStudioShapeIsLine,
            });
        },

        imageStudioOnShapeStrokeChange() {
            this.imageStudioOnShapeFillChange();
        },

        imageStudioOnShapeStrokeWidthChange() {
            if (this.imageStudioShapeStrokeWidth > 0 && !this.imageStudioShapeStroke) {
                this.imageStudioShapeStroke = '#ffffff';
            }
            this.imageStudioOnShapeFillChange();
        },

        imageStudioClearShapeFill() {
            this.imageStudioShapeFill = '';
            this.imageStudioOnShapeFillChange();
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

        imageStudioSetObjectAngle(degrees) {
            this.imageStudioEngine?.setActiveObjectAngle(degrees);
            this.imageStudioObjectAngle = this.imageStudioEngine?.getActiveObjectAngle() ?? 0;
            this.refreshImageStudioLayers();
        },

        imageStudioNudgeObjectAngle(delta) {
            this.imageStudioEngine?.nudgeActiveObjectAngle(delta);
            this.imageStudioObjectAngle = this.imageStudioEngine?.getActiveObjectAngle() ?? 0;
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
                const tag = (e.target?.tagName || '').toLowerCase();
                if (tag === 'input' || tag === 'textarea' || e.target?.isContentEditable) {
                    return;
                }
                const mod = e.ctrlKey || e.metaKey;
                if (e.key === 'Escape' && this.imageStudioExpanded && !this.imageStudioElementsModalOpen && !this.imageStudioDimensionsModalOpen) {
                    e.preventDefault();
                    this.closeImageStudioExpanded();
                } else if (mod && e.key === 'z' && !e.shiftKey) {
                    e.preventDefault();
                    this.imageStudioUndo();
                } else if (mod && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
                    e.preventDefault();
                    this.imageStudioRedo();
                } else if (!mod && this.imageStudioSelectedObject && e.key === '[') {
                    e.preventDefault();
                    this.imageStudioNudgeObjectAngle(e.shiftKey ? -15 : -5);
                } else if (!mod && this.imageStudioSelectedObject && e.key === ']') {
                    e.preventDefault();
                    this.imageStudioNudgeObjectAngle(e.shiftKey ? 15 : 5);
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
                json.width = this.imageStudioEngine.designWidth;
                json.height = this.imageStudioEngine.designHeight;
                await api.put(`/projects/${this.projectId}/image-studio`, {
                    preset: 'custom',
                    canvas: json,
                });
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar design';
            } finally {
                this.imageStudioSaving = false;
            }
        },

        async ensureImageStudioFormatForThumbnailPlatform() {
            const platform = this.selectedThumbnailPlatform || 'youtube_landscape';
            const presetMap = {
                youtube_landscape: 'yt_thumb_hd',
                youtube_shorts: 'yt_shorts',
                instagram_feed_square: 'ig_feed_square',
                instagram_reels: 'ig_story',
                tiktok: 'tt_video',
            };
            const targetSlug = presetMap[platform];
            if (!targetSlug) {
                return;
            }
            const current = this.resolveImageStudioPresetMeta();
            if (current.width === current.height && platform === 'youtube_landscape') {
                await this.switchImageStudioPreset('yt_thumb_hd');
                return;
            }
            if (this.imageStudioPreset !== targetSlug && !this._imageStudioPresetLocked) {
                await this.switchImageStudioPreset(targetSlug);
            }
        },

        async switchImageStudioPreset(slug) {
            const preset = this.resolveImageStudioPresetMeta(slug);
            if (slug === this.imageStudioPreset) {
                if (this.imageStudioEngine?.canvas
                    && (this.imageStudioEngine.designWidth !== preset.width
                        || this.imageStudioEngine.designHeight !== preset.height)) {
                    this.imageStudioEngine.setSize(preset.width, preset.height);
                    this.fitImageStudioCanvas();
                    this.message = `Canvas ajustado: ${preset.aspect || ''} ${preset.width}×${preset.height}px`;
                }
                return;
            }
            if (!this.imageStudioEngine?.canvas) {
                await this.initImageStudio();
            }
            await this.saveImageStudioDesign();
            this.imageStudioPreset = slug;
            this._imageStudioPresetLocked = true;
            await this.loadImageStudioDesign();
            this.fitImageStudioCanvas();
            this.message = `Formato: ${preset.name || slug} — ${preset.aspect || ''} ${preset.width}×${preset.height}px`;
            this.refreshImageStudioLayers();
        },

        onImageStudioBgChange() {
            this.imageStudioEngine?.setBackgroundColor(this.imageStudioBgColor, this.imageStudioBgTransparency);
            this.syncImageStudioUnderlayToEngine();
            this.scheduleImageStudioSave();
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
            const obj = this.imageStudioEngine?.getActiveTextObject();
            if (!isFabricText(obj)) {
                return;
            }
            await this.imageStudioEngine.applyTextStyle(
                obj,
                this.imageStudioTextStylePayload(),
                this.imageStudioFontMap
            );
            this._syncingTextUi = true;
            const st = this.imageStudioEngine.getTextStyleFromObject(obj);
            if (st) {
                this.imageStudioTextFill = st.fill;
                this.imageStudioTextStroke = st.stroke;
                this.imageStudioTextStrokeWidth = st.strokeWidth;
            }
            this.$nextTick(() => {
                this._syncingTextUi = false;
            });
            this.imageStudioObjectScale = this.imageStudioEngine?.getActiveObjectScalePercent() ?? 100;
            this.imageStudioCanUndo = this.imageStudioEngine?.canUndo() ?? false;
            this.imageStudioCanRedo = this.imageStudioEngine?.canRedo() ?? false;
        },

        imageStudioOnTextFillChange() {
            this.imageStudioTextFill = normalizeColorInput(this.imageStudioTextFill, '#ffffff');
            this.imageStudioOnTextControlChange();
        },

        imageStudioOnTextStrokeChange() {
            this.imageStudioTextStroke = normalizeColorInput(this.imageStudioTextStroke, '#000000');
            if ((this.imageStudioTextStrokeWidth || 0) <= 0) {
                this.imageStudioTextStrokeWidth = 2;
            }
            this.imageStudioOnTextControlChange();
        },

        imageStudioRemoveTextOutline() {
            this.imageStudioTextStrokeWidth = 0;
            this.imageStudioOnTextControlChange();
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
            if (el?.type === 'icon_glyph') {
                await this.imageStudioAddIconGlyph({
                    char: el.char,
                    font: el.font,
                    slug: el.slug,
                    label: el.name || el.label,
                });
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

            this.imageStudioBgRemoving = true;
            this.message = 'Removendo fundo com IA…';

            try {
                if (!this.imageStudioEngine?.canvas) await this.initImageStudio();
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

            this.imageStudioBgRemoving = true;
            this.message = 'Removendo fundo com IA…';

            try {
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
            this.imageStudioEngine?.selectLayer(layer.object);
            this.refreshImageStudioLayers();
        },

        imageStudioLayerAction(layer, action) {
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

        async imageStudioExport(format, options = {}) {
            if (!this.imageStudioEngine) {
                return;
            }
            const openPreview = options.openPreview !== false;
            try {
                const blob = await this.imageStudioEngine.exportBlob(format, 0.92, this.buildImageStudioExportOptions());
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
                if (openPreview && (format === 'png' || format === 'jpg')) {
                    window.open(data.export.url, '_blank');
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao exportar';
            }
        },

        imageStudioResolveThumbnailPlatform() {
            const mapped = this.imageStudioPresetPlatformMap?.[this.imageStudioPreset];
            return mapped || this.selectedThumbnailPlatform || 'youtube_landscape';
        },

        async imageStudioPushThumbnail() {
            if (!this.imageStudioLastExport?.filename) {
                await this.imageStudioExport('png');
            }
            if (!this.imageStudioLastExport?.filename) {
                return;
            }
            const platform = this.imageStudioResolveThumbnailPlatform();
            try {
                const { data } = await api.post(`/projects/${this.projectId}/image-studio/push-thumbnail`, {
                    filename: this.imageStudioLastExport.filename,
                    platform,
                    preset: this.imageStudioPreset,
                });
                this.message = `Arte do Image Studio aplicada na capa ${platform}`;
                if (data.settings) {
                    this.applyThumbnailSettingsPatch(data.settings);
                }
                if (data.platform) {
                    this.selectedThumbnailPlatform = data.platform;
                    this.thumbnailSettingsByPlatform[data.platform] = {
                        ...(this.thumbnailSettingsByPlatform[data.platform] || {}),
                        ...(data.settings || {}),
                    };
                }
                if (data.thumbnail?.url) {
                    this.thumbnailPreviewUrl = data.thumbnail.url;
                    this.thumbnailPreviewUrls[platform] = data.thumbnail.url;
                }
                this.switchTab('thumbnail');
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao enviar para Thumbnail';
            }
        },

        async imageStudioPushLibrary() {
            if (!this.imageStudioLastExport?.filename) {
                await this.imageStudioExport('png', { openPreview: false });
            }
            if (!this.imageStudioLastExport?.filename) {
                this.error = 'Exporte o design antes de salvar na biblioteca.';
                return;
            }
            try {
                const { data } = await api.post(`/projects/${this.projectId}/image-studio/push-library`, {
                    filename: this.imageStudioLastExport.filename,
                    preset: this.imageStudioPreset,
                });
                if (data.asset) {
                    this.upsertProjectLibraryAsset(data.asset);
                } else {
                    await this.loadProjectLibraryAssets();
                }
                this.message = data.message || 'Imagem adicionada à biblioteca do projeto';
                this.switchTab('biblioteca');
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
            await this.imageStudioEngine.applyTemplate(full, this.imageStudioFontMap);
            if (full.background?.color !== undefined) {
                this.imageStudioBgColor = full.background.color;
                this.imageStudioBgTransparency = full.background.transparency
                    ?? (full.background.opacity !== undefined
                        ? Math.max(0, 100 - Number(full.background.opacity))
                        : 0);
                this.syncImageStudioBackgroundFromEngine();
            }
            const hookFont = (full.objects || []).find((o) => o.fontSlug)?.fontSlug;
            if (hookFont && this.imageStudioFontMap[hookFont]) {
                this.imageStudioTextFontSlug = hookFont;
            }
            this.fitImageStudioCanvas();
            this.refreshImageStudioLayers();
            this.scheduleImageStudioSave();
            const extras = full.viral ? ' — fonte de impacto, caixa de destaque e formato prontos' : '';
            this.message = `Layout "${full.name}" aplicado${extras}`;
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
            if (!asset?.id && !asset?.url) {
                return;
            }
            const url = asset.url || `/api/projects/${this.projectId}/assets/${asset.id}`;
            if (!this.imageStudioReady) {
                this.switchTab('image_studio');
                await this.$nextTick();
                if (!this.imageStudioReady) {
                    await this.initImageStudio();
                }
            } else {
                this.switchTab('image_studio');
            }
            await this.imageStudioEngine?.addImageFromUrl(url, asset.item_title || 'Asset');
            this.refreshImageStudioLayers();
            this.message = 'Asset adicionado ao canvas';
        },
    };
}
