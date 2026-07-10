import { Canvas, FabricText, FabricImage, Rect, Circle, filters } from 'fabric';
import { writePsdBuffer } from 'ag-psd';
import { jsPDF } from 'jspdf';

const DEFAULT_FILTER_STATE = {
    brightness: 50,
    contrast: 50,
    saturation: 50,
    blur: 0,
    grayscale: 0,
    vignette: 0,
};

export class ImageStudioEngine {
    constructor(canvasEl) {
        this.canvasEl = canvasEl;
        this.canvas = null;
        this.onChange = null;
    }

    init(width, height, backgroundColor = '#ffffff') {
        if (this.canvas) {
            this.canvas.dispose();
        }
        this.canvas = new Canvas(this.canvasEl, {
            width,
            height,
            backgroundColor,
            preserveObjectStacking: true,
            selection: true,
        });
        this.canvas.on('object:modified', () => this.emitChange());
        this.canvas.on('object:added', () => this.emitChange());
        this.canvas.on('object:removed', () => this.emitChange());
        this.canvas.on('selection:created', () => this.emitChange());
        this.canvas.on('selection:updated', () => this.emitChange());
        this.canvas.on('selection:cleared', () => this.emitChange());
        return this.canvas;
    }

    emitChange() {
        if (typeof this.onChange === 'function') {
            this.onChange();
        }
    }

    setSize(width, height, backgroundColor) {
        if (!this.canvas) {
            return this.init(width, height, backgroundColor);
        }
        this.canvas.setDimensions({ width, height });
        if (backgroundColor) {
            this.canvas.backgroundColor = backgroundColor;
        }
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
        await this.canvas.loadFromJSON(json);
        this.canvas.getObjects().forEach((obj) => {
            if (obj.criasysFilters && (obj.type === 'image' || obj instanceof FabricImage)) {
                this.applyFiltersToObject(obj, obj.criasysFilters);
            }
        });
        this.canvas.requestRenderAll();
    }

    getFilterState(object) {
        return { ...DEFAULT_FILTER_STATE, ...(object?.criasysFilters || {}) };
    }

    applyFiltersToObject(object, state) {
        if (!object || object.type !== 'image') {
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
        if (!object || object.type !== 'image') {
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
        const textObj = new FabricText(text, {
            left: (this.canvas.width / 2) - 120,
            top: (this.canvas.height / 2) - 30,
            fontFamily: options.fontFamily || 'Impact, Arial Black, sans-serif',
            fontSize: options.fontSize || 64,
            fill: options.fill || '#ffffff',
            fontWeight: 'bold',
            name: 'Texto',
            criasysId: 'text_' + Date.now(),
        });
        this.canvas.add(textObj);
        this.canvas.setActiveObject(textObj);
        this.canvas.requestRenderAll();
        this.emitChange();
    }

    addRect(color = '#ef4444', opacity = 80) {
        const rect = new Rect({
            left: this.canvas.width * 0.15,
            top: this.canvas.height * 0.2,
            width: this.canvas.width * 0.7,
            height: this.canvas.height * 0.25,
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
        const size = Math.min(this.canvas.width, this.canvas.height) * 0.25;
        const circle = new Circle({
            left: this.canvas.width / 2 - size / 2,
            top: this.canvas.height / 2 - size / 2,
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

    async addImageFromUrl(url, name = 'Imagem') {
        const img = await FabricImage.fromURL(url, { crossOrigin: 'anonymous' });
        const maxW = this.canvas.width * 0.85;
        const maxH = this.canvas.height * 0.85;
        const scale = Math.min(maxW / img.width, maxH / img.height, 1);
        img.set({
            left: (this.canvas.width - img.width * scale) / 2,
            top: (this.canvas.height - img.height * scale) / 2,
            scaleX: scale,
            scaleY: scale,
            name,
            criasysId: 'img_' + Date.now(),
        });
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

    async exportBlob(format = 'png', quality = 0.92) {
        if (!this.canvas) {
            return null;
        }
        if (format === 'svg') {
            const svg = this.canvas.toSVG();
            return new Blob([svg], { type: 'image/svg+xml' });
        }
        if (format === 'json') {
            return new Blob([JSON.stringify(this.toJSON(), null, 2)], { type: 'application/json' });
        }
        if (format === 'psd') {
            return this.exportPsdBlob();
        }
        if (format === 'pdf') {
            return this.exportPdfBlob(quality);
        }
        const mime = format === 'jpg' ? 'image/jpeg' : 'image/png';
        const dataUrl = this.canvas.toDataURL({
            format: format === 'jpg' ? 'jpeg' : 'png',
            quality,
            multiplier: 1,
        });
        const res = await fetch(dataUrl);
        return res.blob();
    }

    exportPsdBlob() {
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

        const buffer = writePsdBuffer({ width: w, height: h, children: layers });
        return new Blob([buffer], { type: 'application/vnd.adobe.photoshop' });
    }

    async exportPdfBlob(quality = 0.92) {
        const w = this.canvas.getWidth();
        const h = this.canvas.getHeight();
        const dataUrl = this.canvas.toDataURL({
            format: 'jpeg',
            quality,
            multiplier: 1,
        });
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
        if (!this.canvas) {
            return 1;
        }
        const pad = 24;
        const scale = Math.min(
            (containerWidth - pad) / this.canvas.getWidth(),
            (containerHeight - pad) / this.canvas.getHeight(),
            1
        );
        this.canvas.setZoom(scale);
        this.canvas.setDimensions({
            width: this.canvas.getWidth() * scale,
            height: this.canvas.getHeight() * scale,
        });
        return scale;
    }
}

export function imageStudioMethods() {
    return {
        imageStudioReady: false,
        imageStudioPresets: [],
        imageStudioGroups: {},
        imageStudioExportFormats: [],
        imageStudioFonts: [],
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
        imageStudioZoom: 1,
        imageStudioFilters: { ...DEFAULT_FILTER_STATE },

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
                if (!groups[key]) {
                    groups[key] = [];
                }
                groups[key].push(p);
            });
            return groups;
        },

        get imageStudioCurrentPreset() {
            return (this.imageStudioPresets || []).find((p) => p.slug === this.imageStudioPreset) || null;
        },

        async loadImageStudioCatalog() {
            try {
                const { data } = await api.get('/image-studio/catalog');
                this.imageStudioPresets = data.presets || [];
                this.imageStudioGroups = data.groups || {};
                this.imageStudioExportFormats = data.export_formats || [];
                this.imageStudioFonts = data.fonts || [];
                this.imageStudioBgRemoval = Boolean(data.background_removal_available);
                if (data.defaults?.preset) {
                    this.imageStudioPreset = data.defaults.preset;
                }
            } catch (_) {
                /* opcional */
            }
        },

        async initImageStudio() {
            if (this.imageStudioReady) {
                this.refreshImageStudioLayers();
                return;
            }
            await this.loadImageStudioCatalog();
            await this.$nextTick();

            const el = this.$refs.imageStudioCanvas;
            if (!el) {
                return;
            }

            this.imageStudioEngine = new ImageStudioEngine(el);
            this.imageStudioEngine.onChange = () => {
                this.refreshImageStudioLayers();
                this.scheduleImageStudioSave();
            };

            await this.loadImageStudioDesign();
            this.imageStudioReady = true;
        },

        async loadImageStudioDesign() {
            try {
                const { data } = await api.get(`/projects/${this.projectId}/image-studio`, {
                    params: { preset: this.imageStudioPreset },
                });
                const preset = this.imageStudioPresets.find((p) => p.slug === data.preset)
                    || { width: data.width, height: data.height };
                const w = preset.width || data.width || 1080;
                const h = preset.height || data.height || 1080;

                this.imageStudioEngine.init(w, h, this.imageStudioBgColor);
                this.imageStudioEngine.setBackgroundColor(this.imageStudioBgColor, this.imageStudioBgOpacity);

                if (data.canvas) {
                    await this.imageStudioEngine.loadFromJSON(data.canvas);
                }
                this.fitImageStudioCanvas();
                this.refreshImageStudioLayers();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao carregar design';
            }
        },

        fitImageStudioCanvas() {
            const wrap = this.$refs.imageStudioCanvasWrap;
            if (!wrap || !this.imageStudioEngine?.canvas) {
                return;
            }
            const preset = this.imageStudioCurrentPreset;
            if (!preset) {
                return;
            }
            const c = this.imageStudioEngine.canvas;
            c.setZoom(1);
            c.setDimensions({ width: preset.width, height: preset.height });
            this.imageStudioZoom = this.imageStudioEngine.zoomToFit(wrap.clientWidth, wrap.clientHeight);
        },

        refreshImageStudioLayers() {
            this.imageStudioLayers = this.imageStudioEngine?.getLayers() || [];
            this.imageStudioSelectedObject = this.imageStudioEngine?.getActiveObject() || null;
            if (this.imageStudioSelectedObject?.type === 'image') {
                this.imageStudioFilters = this.imageStudioEngine.getFilterState(this.imageStudioSelectedObject);
            }
        },

        imageStudioApplyFilters() {
            const obj = this.imageStudioEngine?.getActiveObject();
            if (obj?.type === 'image') {
                this.imageStudioEngine.applyFiltersToObject(obj, this.imageStudioFilters);
            }
        },

        imageStudioClearFilters() {
            const obj = this.imageStudioEngine?.getActiveObject();
            if (obj?.type === 'image') {
                this.imageStudioEngine.clearFilters(obj);
                this.imageStudioFilters = { ...DEFAULT_FILTER_STATE };
            }
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
            await this.saveImageStudioDesign();
            this.imageStudioPreset = slug;
            this.imageStudioReady = false;
            await this.initImageStudio();
        },

        onImageStudioBgChange() {
            this.imageStudioEngine?.setBackgroundColor(this.imageStudioBgColor, this.imageStudioBgOpacity);
        },

        imageStudioAddText() {
            const font = this.imageStudioFonts.find((f) => f.slug === 'impact') || this.imageStudioFonts[0];
            const family = font?.label ? `${font.label}, Impact, sans-serif` : 'Impact, sans-serif';
            this.imageStudioEngine?.addText('Seu título aqui', { fontFamily: family });
        },

        imageStudioAddShape(type) {
            if (type === 'circle') {
                this.imageStudioEngine?.addCircle();
            } else {
                this.imageStudioEngine?.addRect();
            }
        },

        async imageStudioUploadImage(event) {
            const file = event?.target?.files?.[0];
            if (!file) {
                return;
            }
            const form = new FormData();
            form.append('file', file);
            form.append('type', 'image');
            form.append('attach_to_slide', '0');
            try {
                const { data } = await api.post(`/projects/${this.projectId}/assets/upload`, form, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                const assetId = data.id;
                if (assetId) {
                    const url = `/api/projects/${this.projectId}/assets/${assetId}`;
                    await this.imageStudioEngine?.addImageFromUrl(url, file.name);
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao importar imagem';
            } finally {
                event.target.value = '';
            }
        },

        async imageStudioRemoveBackground(event) {
            const file = event?.target?.files?.[0];
            if (!file) {
                return;
            }
            const form = new FormData();
            form.append('image', file);
            try {
                this.message = 'Removendo fundo…';
                const { data } = await api.post(`/projects/${this.projectId}/image-studio/remove-background`, form, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                await this.imageStudioEngine?.addImageFromUrl(data.url, 'Sem fundo');
                this.message = 'Fundo removido';
            } catch (e) {
                this.error = e.response?.data?.message || 'Instale rembg: pip install rembg pillow';
            } finally {
                event.target.value = '';
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

        async imageStudioExport(format) {
            if (!this.imageStudioEngine) {
                return;
            }
            try {
                const blob = await this.imageStudioEngine.exportBlob(format);
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

        async imageStudioImportFromLibrary(item) {
            if (!item?.preview_url && !item?.download_url) {
                return;
            }
            const url = item.preview_url || item.download_url;
            await this.imageStudioEngine?.addImageFromUrl(url, item.title || 'Biblioteca');
        },
    };
}
