import axios from 'axios';
import { formatNarrationText, parseScript } from './scriptParser';

const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

window.api = axios.create({
    baseURL: '/api',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

window.api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

window.editorApp = function (projectId, projectMeta = {}) {
    return {
        projectId,
        projectDescription: projectMeta.description || '',
        slides: [],
        selectedSlide: null,
        activeTab: 'roteiro',
        fullScript: '',
        scriptStats: null,
        scriptParseTimeout: null,
        voice: projectMeta.defaultVoice || 'onyx',
        ttsEngine: projectMeta.defaultTtsEngine || 'openai',
        ttsEngines: [],
        voices: [],
        voicesLoading: false,
        narration: null,
        narrationLoading: false,
        previewLoading: false,
        previewAudioUrl: null,
        previewPlaying: false,
        previewIndex: 0,
        previewTimer: null,
        previewTransitioning: false,
        previewTransitionKind: 'fade',
        previewPlayToken: 0,
        mediaQuery: '',
        mediaSource: 'all',
        mediaType: 'image',
        mediaResults: [],
        mediaErrors: [],
        mediaSearching: false,
        exportPresets: [],
        exportPackages: [],
        downloads: [],
        selectedDownloadIds: [],
        platformDescriptions: {},
        platformDescKeys: ['youtube', 'youtube_shorts', 'tiktok', 'instagram_reels', 'instagram_feed'],
        selectedPlatformDesc: 'youtube',
        projectCreditsText: '',
        projectCreditsCount: 0,
        publishAuto: false,
        publishFiles: {},
        stockLicenses: [],
        stockLicenseProviders: [],
        stockLicenseForm: {
            provider: 'envato',
            project_title: '',
            license_url: '',
            license_note: '',
            is_default: true,
        },
        attachPaidLicenseOnUpload: true,
        audioTrack: { volume: 0.35, ducking_enabled: true },
        renderJobs: [],
        saving: false,
        message: '',
        error: '',
        pollInterval: null,
        saveTimeout: null,
        descriptionSaveTimeout: null,
        dragFromIndex: null,
        burnSubtitles: false,

        get previewSlide() {
            if (this.previewPlaying && this.slides.length) {
                return this.slides[this.previewIndex] ?? this.selectedSlide;
            }
            return this.selectedSlide;
        },

        get defaultStockLicense() {
            return this.stockLicenses.find((r) => r.is_default) || this.stockLicenses[0] || null;
        },

        get stockLicenseProviderHint() {
            const slug = this.stockLicenseForm.provider;
            const meta = this.stockLicenseProviders.find((p) => p.slug === slug);

            return meta?.project_hint || '';
        },

        get selectedTtsEngineMeta() {
            return this.ttsEngines.find((e) => e.slug === this.ttsEngine) || null;
        },

        async init() {
            await Promise.all([
                this.loadSlides(),
                this.loadNarration(),
                this.loadRenderJobs(),
                this.loadDownloads(),
                this.loadExportPresets(),
                this.loadExportPackages(),
                this.loadAudioTrack(),
                this.loadTtsEngines(),
                this.loadProjectCredits(),
                this.loadPlatformDescriptions(),
                this.loadStockLicenses(),
            ]);
            await this.loadVoices();
            await this.syncPublish();
            this.pollInterval = setInterval(() => {
                this.loadRenderJobs();
                this.loadExportPackages();
                this.loadDownloads();
            }, 3000);

            document.addEventListener('keydown', (e) => this.handleShortcut(e));
        },

        handleShortcut(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                this.saveSlide();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                this.addSlide();
            }
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'S') {
                e.preventDefault();
                this.syncNarration();
            }
        },

        dragStart(index) {
            this.dragFromIndex = index;
        },

        dropSlide(toIndex) {
            if (this.dragFromIndex === null || this.dragFromIndex === toIndex) return;
            const moved = this.slides.splice(this.dragFromIndex, 1)[0];
            this.slides.splice(toIndex, 0, moved);
            this.dragFromIndex = null;
            this.persistSlideOrder();
        },

        async persistSlideOrder() {
            try {
                const { data } = await api.put(`/projects/${this.projectId}/slides/reorder`, {
                    slide_ids: this.slides.map(s => s.id),
                });
                this.slides = data.map(s => this.enrichSlide(s));
                this.syncSelection();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao reordenar slides';
                await this.loadSlides();
            }
        },

        scheduleSave() {
            clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(() => this.saveSlide(), 600);
        },

        syncSelection() {
            if (this.selectedSlide?.id) {
                const fresh = this.slides.find(s => s.id === this.selectedSlide.id);
                this.selectedSlide = fresh ?? this.slides[0] ?? null;
            } else {
                this.selectedSlide = this.slides[0] ?? null;
            }
        },

        slidePayload(slide) {
            return {
                title: slide.title ?? '',
                subtitle: slide.subtitle ?? '',
                body_text: slide.body_text ?? '',
                narration_text: slide.narration_text ?? '',
                duration_seconds: slide.duration_seconds ?? 5,
                transition_type: slide.transition_type ?? 'fade',
                text_style: slide.text_style ?? null,
                image_path: slide.image_path ?? null,
                video_path: slide.video_path ?? null,
            };
        },

        async loadTtsEngines() {
            const { data } = await api.get('/tts/engines');
            this.ttsEngines = data;

            const recommended = data.find((e) => e.recommended && e.available);
            const preferred = ['openai', 'piper', 'edge', 'elevenlabs'];
            const current = data.find((e) => e.slug === this.ttsEngine && e.available);

            if (!current) {
                const pick = recommended
                    || preferred.map((slug) => data.find((e) => e.slug === slug && e.available)).find(Boolean);
                if (pick) this.ttsEngine = pick.slug;
            } else if (this.ttsEngine === 'edge') {
                const better = data.find((e) => e.slug === 'piper' && e.available);
                if (better) this.ttsEngine = better.slug;
            }
        },

        async loadVoices() {
            this.voicesLoading = true;
            try {
                const { data } = await api.get(`/tts/engines/${this.ttsEngine}/voices`);
                this.voices = data;
                if (data.length && !data.some(v => v.id === this.voice)) {
                    this.voice = data[0].id;
                }
            } catch (e) {
                this.voices = [];
            } finally {
                this.voicesLoading = false;
            }
        },

        async onEngineChange() {
            this.voice = '';
            await this.loadVoices();
        },

        async loadExportPresets() {
            const { data } = await api.get('/export-presets');
            this.exportPresets = data;
        },

        async loadDownloads() {
            const { data } = await api.get(`/projects/${this.projectId}/downloads`);
            this.downloads = data;
        },

        toggleDownload(id) {
            if (this.selectedDownloadIds.includes(id)) {
                this.selectedDownloadIds = this.selectedDownloadIds.filter(x => x !== id);
            } else {
                this.selectedDownloadIds.push(id);
            }
        },

        selectAllReadyDownloads() {
            this.selectedDownloadIds = this.downloads
                .filter(d => d.status === 'ready' && d.url)
                .map(d => d.id);
        },

        downloadSelected() {
            const items = this.downloads.filter(
                d => this.selectedDownloadIds.includes(d.id) && d.url
            );
            if (!items.length) {
                this.error = 'Selecione ao menos um arquivo pronto para download.';
                return;
            }
            items.forEach(item => {
                const a = document.createElement('a');
                a.href = item.url;
                a.download = item.filename || '';
                a.target = '_blank';
                document.body.appendChild(a);
                a.click();
                a.remove();
            });
            this.message = `${items.length} arquivo(s) baixado(s)`;
        },

        formatBytes(bytes) {
            if (!bytes) return '—';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },

        async loadStockLicenses() {
            try {
                const { data } = await api.get(`/projects/${this.projectId}/stock-licenses`);
                this.stockLicenses = data.registrations || [];
                this.stockLicenseProviders = data.providers || [];
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao carregar licenças';
            }
        },

        providerLabel(slug) {
            const meta = this.stockLicenseProviders.find((p) => p.slug === slug);

            return meta?.name || slug;
        },

        async saveStockLicense() {
            const title = this.stockLicenseForm.project_title.trim();
            if (!title) {
                this.error = 'Informe o nome do projeto na plataforma (ex.: nome do projeto Envato).';

                return;
            }

            try {
                await api.post(`/projects/${this.projectId}/stock-licenses`, this.stockLicenseForm);
                this.stockLicenseForm = {
                    provider: this.stockLicenseForm.provider,
                    project_title: '',
                    license_url: '',
                    license_note: '',
                    is_default: !this.stockLicenses.length,
                };
                await this.loadStockLicenses();
                await this.syncPublish();
                this.message = 'Licença cadastrada — uploads manuais usarão este registro.';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao cadastrar licença';
            }
        },

        async setDefaultStockLicense(reg) {
            try {
                await api.put(`/projects/${this.projectId}/stock-licenses/${reg.id}`, { is_default: true });
                await this.loadStockLicenses();
                this.message = `${this.providerLabel(reg.provider)} definido como licença padrão.`;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao definir licença padrão';
            }
        },

        async applyStockLicenseToLocal(reg) {
            try {
                const { data } = await api.post(
                    `/projects/${this.projectId}/stock-licenses/${reg.id}/apply-local`
                );
                await this.syncPublish();
                this.message = data.message || 'Licença aplicada aos uploads.';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao vincular licença';
            }
        },

        async removeStockLicense(reg) {
            if (!confirm(`Remover licença «${reg.project_title}»?`)) return;

            try {
                await api.delete(`/projects/${this.projectId}/stock-licenses/${reg.id}`);
                await this.loadStockLicenses();
                await this.syncPublish();
                this.message = 'Licença removida.';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao remover licença';
            }
        },

        async loadProjectCredits() {
            try {
                const { data } = await api.get(`/projects/${this.projectId}/credits`);
                this.projectCreditsText = data.text || '';
                this.projectCreditsCount = data.count || 0;
                this.publishAuto = this.projectCreditsCount > 0;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao carregar créditos';
            }
        },

        applyPublish(publish) {
            if (!publish) return;
            this.publishAuto = publish.auto !== false && (publish.materials_count || 0) > 0;
            this.projectCreditsText = publish.credits_text || '';
            this.projectCreditsCount = publish.materials_count || 0;
            if (publish.descriptions) {
                this.platformDescriptions = publish.descriptions;
            }
            if (publish.files) {
                this.publishFiles = publish.files;
            }
        },

        async syncPublish() {
            try {
                const { data } = await api.post(`/projects/${this.projectId}/publish/sync`);
                this.applyPublish(data);
                await this.loadDownloads();
            } catch (e) {
                // silencioso — sync é complementar
            }
        },

        async loadPlatformDescriptions() {
            try {
                const { data } = await api.get(`/projects/${this.projectId}/platform-descriptions`);
                this.platformDescriptions = data;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao carregar descrições';
            }
        },

        copyAllCredits() {
            if (!this.projectCreditsText) return;
            navigator.clipboard.writeText(this.projectCreditsText).then(() => {
                this.message = 'Créditos copiados — cole na descrição da plataforma';
            }).catch(() => {
                this.error = 'Não foi possível copiar — selecione e copie manualmente';
            });
        },

        scheduleDescriptionSave() {
            clearTimeout(this.descriptionSaveTimeout);
            this.descriptionSaveTimeout = setTimeout(() => this.saveProjectDescription(), 800);
        },

        async saveProjectDescription() {
            try {
                await api.put(`/projects/${this.projectId}`, { description: this.projectDescription });
                await this.syncPublish();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar descrição';
            }
        },

        playSlideshow() {
            if (!this.slides.length) return;
            this.stopSlideshow();
            this.previewPlaying = true;
            this.previewIndex = 0;
            this.previewPlayToken++;
            this.previewTransitioning = false;
            this.schedulePreviewAdvance();
        },

        stopSlideshow() {
            this.previewPlaying = false;
            if (this.previewTimer) {
                clearTimeout(this.previewTimer);
                this.previewTimer = null;
            }
            this.previewTransitioning = false;
        },

        schedulePreviewAdvance() {
            if (!this.previewPlaying) return;
            const slide = this.slides[this.previewIndex];
            if (!slide) {
                this.stopSlideshow();
                return;
            }
            const ms = Math.max(500, (slide.duration_seconds || 5) * 1000);
            this.previewTimer = setTimeout(() => this.advancePreviewSlide(), ms);
        },

        advancePreviewSlide() {
            if (!this.previewPlaying || !this.slides.length) return;
            const slide = this.slides[this.previewIndex];
            const trans = slide?.transition_type || 'fade';

            if (trans === 'cut') {
                this.previewIndex = (this.previewIndex + 1) % this.slides.length;
                this.previewPlayToken++;
                this.schedulePreviewAdvance();
                return;
            }

            this.previewTransitionKind = trans === 'slide' ? 'slide' : 'fade';
            this.previewTransitioning = true;
            setTimeout(() => {
                this.previewIndex = (this.previewIndex + 1) % this.slides.length;
                this.previewPlayToken++;
                this.previewTransitioning = false;
                this.schedulePreviewAdvance();
            }, 500);
        },

        copyPlatformDescription() {
            const d = this.platformDescriptions[this.selectedPlatformDesc];
            if (!d?.description) return;
            navigator.clipboard.writeText(d.description).then(() => {
                this.message = 'Descrição copiada para a área de transferência';
            }).catch(() => {
                this.error = 'Não foi possível copiar — selecione e copie manualmente';
            });
        },

        async loadExportPackages() {
            const { data } = await api.get(`/projects/${this.projectId}/export-packages`);
            this.exportPackages = data.map(pkg => ({
                ...pkg,
                download_url: pkg.status === 'completed' && pkg.package_path
                    ? this.fileUrl('exports', pkg.package_path.split(/[/\\]/).pop())
                    : null,
            }));
        },

        async loadAudioTrack() {
            const { data } = await api.get(`/projects/${this.projectId}/audio-tracks`);
            if (data.length) {
                this.audioTrack = data[0];
            }
        },

        async loadSlides() {
            const { data } = await api.get(`/projects/${this.projectId}/slides`);
            this.slides = data.map(s => this.enrichSlide(s));
            this.syncSelection();
            this.buildFullScriptFromSlides();
        },

        enrichSlide(slide) {
            if (!slide.text_style) {
                slide.text_style = {
                    title_color: '#ffffff',
                    title_size: 48,
                    align: 'center',
                };
            }
            if (slide.image_path) {
                slide.image_url = this.fileUrl('assets', slide.image_path.split(/[/\\]/).pop());
            }
            if (slide.video_path) {
                slide.video_url = this.fileUrl('assets', slide.video_path.split(/[/\\]/).pop());
            }
            return slide;
        },

        fileUrl(type, filename) {
            return `/api/projects/${this.projectId}/files/${type}/${filename}`;
        },

        selectSlide(slide) {
            if (this.previewPlaying) {
                this.stopSlideshow();
            }
            this.selectedSlide = this.slides.find(s => s.id === slide.id) ?? slide;
        },

        switchTab(tab) {
            this.activeTab = tab;
            if (tab === 'biblioteca') {
                this.prepareMediaSearch();
            }
            if (tab === 'exportar') {
                this.loadProjectCredits();
                this.loadPlatformDescriptions();
            }
        },

        prepareMediaSearch() {
            const slide = this.selectedSlide;
            if (!this.mediaQuery.trim() && slide?.title?.trim()) {
                this.mediaQuery = slide.title.trim();
                this.searchMedia();
            }
        },

        searchFromSlideTitle() {
            const slide = this.selectedSlide;
            if (!slide?.title?.trim()) {
                this.error = 'Defina um título no slide antes de buscar imagens.';
                return;
            }
            this.mediaQuery = slide.title.trim();
            this.activeTab = 'biblioteca';
            this.searchMedia();
        },

        buildFullScriptFromSlides() {
            this.fullScript = this.slides
                .map(s => (s.narration_text || '').trim())
                .filter(Boolean)
                .join('\n\n');
        },

        async addSlide() {
            const { data } = await api.post(`/projects/${this.projectId}/slides`, {
                title: `Slide ${this.slides.length + 1}`,
            });
            this.slides.push(this.enrichSlide(data));
            this.selectSlide(data);
        },

        async removeSlide(slide) {
            if (!confirm('Remover este slide?')) return;
            await api.delete(`/projects/${this.projectId}/slides/${slide.id}`);
            this.slides = this.slides.filter(s => s.id !== slide.id);
            this.syncSelection();
        },

        async saveSlide() {
            const slide = this.selectedSlide;
            if (!slide) return;
            this.saving = true;
            this.error = '';
            try {
                const { data } = await api.put(
                    `/projects/${this.projectId}/slides/${slide.id}`,
                    this.slidePayload(slide)
                );
                const idx = this.slides.findIndex(s => s.id === data.id);
                if (idx >= 0) {
                    this.slides[idx] = this.enrichSlide({ ...this.slides[idx], ...data });
                    if (this.selectedSlide?.id === data.id) {
                        this.selectedSlide = this.slides[idx];
                    }
                }
                this.message = 'Salvo';
                setTimeout(() => this.message = '', 2000);
                this.syncPublish();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar';
            } finally {
                this.saving = false;
            }
        },

        copyTitleToNarration() {
            const slide = this.selectedSlide;
            if (!slide) return;
            const parts = [slide.title, slide.subtitle, slide.body_text].filter(v => v?.trim());
            slide.narration_text = formatNarrationText(parts.join('. '));
            this.scheduleSave();
            this.message = 'Texto copiado para narração';
        },

        onFullScriptInput() {
            clearTimeout(this.scriptParseTimeout);
            this.scriptParseTimeout = setTimeout(() => this.refreshScriptPreview(), 400);
        },

        refreshScriptPreview() {
            const text = this.fullScript.trim();
            if (!text) {
                this.scriptStats = null;
                return;
            }
            this.scriptStats = parseScript(text).stats;
        },

        onFullScriptPaste(event) {
            const pasted = event.clipboardData?.getData('text') ?? '';
            if (!pasted.trim()) return;

            event.preventDefault();
            const textarea = event.target;
            const start = textarea.selectionStart ?? 0;
            const end = textarea.selectionEnd ?? 0;
            const merged = (this.fullScript.slice(0, start) + pasted + this.fullScript.slice(end)).trim();
            const parsed = parseScript(merged);

            this.fullScript = parsed.formattedScript || merged;
            this.scriptStats = parsed.stats;
            this.applyParsedScript(parsed, { silent: false, fromPaste: true });
        },

        onNarrationPaste(event) {
            const pasted = event.clipboardData?.getData('text') ?? '';
            if (!pasted.trim() || !this.selectedSlide) return;

            const looksLikeFullScript = pasted.length > 200
                || (pasted.includes('\n') && (parseScript(pasted).stats.slides > 1));

            if (looksLikeFullScript) {
                event.preventDefault();
                const parsed = parseScript(pasted);
                this.fullScript = parsed.formattedScript || pasted;
                this.scriptStats = parsed.stats;
                this.activeTab = 'roteiro';
                this.applyParsedScript(parsed, { fromPaste: true });
                this.message = `Roteiro inteiro detectado — ${parsed.stats.slides} slide(s) criado(s)`;
                return;
            }

            event.preventDefault();
            const textarea = event.target;
            const start = textarea.selectionStart ?? 0;
            const end = textarea.selectionEnd ?? 0;
            const merged = (this.selectedSlide.narration_text || '').slice(0, start)
                + pasted
                + (this.selectedSlide.narration_text || '').slice(end);

            this.selectedSlide.narration_text = formatNarrationText(merged);
            this.scheduleSave();
            this.message = 'Texto formatado para narração';
            setTimeout(() => { if (this.message === 'Texto formatado para narração') this.message = ''; }, 2500);
        },

        async applyParsedScript(parsed, { silent = false, fromPaste = false, trimExtra = false } = {}) {
            if (!parsed?.blocks?.length) {
                if (!silent) this.error = 'Nenhum bloco de narração detectado.';
                return;
            }

            try {
                const { data } = await api.post(`/projects/${this.projectId}/slides/apply-script`, {
                    blocks: parsed.blocks.map(({ narration_text, title }) => ({ narration_text, title })),
                    trim_extra_slides: fromPaste || trimExtra,
                });
                this.slides = data.map(s => this.enrichSlide(s));
                this.syncSelection();
                if (!silent) {
                    const s = parsed.stats;
                    this.message = `Roteiro aplicado: ${s.slides} slide(s) — ${s.prose} parágrafo(s), ${s.dialogues} fala(s), ${s.verses} verso(s), ${s.refrains || 0} refrão(ões)`;
                }
            } catch (e) {
                if (!silent) this.error = e.response?.data?.message || 'Erro ao aplicar roteiro';
            }
        },

        async applyFullScript() {
            const text = this.fullScript.trim();
            if (!text) {
                this.error = 'Cole ou escreva o roteiro completo primeiro.';
                return;
            }
            const parsed = parseScript(text);
            if (!parsed.blocks.length) {
                this.error = 'Não foi possível detectar blocos de narração no texto.';
                return;
            }
            this.fullScript = parsed.formattedScript;
            this.scriptStats = parsed.stats;
            await this.applyParsedScript(parsed, { trimExtra: true });
        },

        async uploadImage(event) {
            const file = event.target.files[0];
            if (!file || !this.selectedSlide) return;
            await this.uploadAsset(file, 'image');
        },

        async uploadVideo(event) {
            const file = event.target.files[0];
            if (!file || !this.selectedSlide) return;
            try {
                const asset = await this.uploadAsset(file, 'video', false);
                this.selectedSlide.video_path = asset.file_path;
                this.selectedSlide.video_url = this.fileUrl('assets', asset.file_path.split(/[/\\]/).pop());
                await this.saveSlide();
                this.message = 'Vídeo inserido no slide';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao enviar vídeo';
            }
        },

        clearSlideVideo() {
            if (!this.selectedSlide) return;
            this.selectedSlide.video_path = null;
            this.selectedSlide.video_url = null;
            this.scheduleSave();
        },

        async uploadAudio(event) {
            const file = event.target.files[0];
            if (!file) return;

            try {
                const asset = await this.uploadAsset(file, 'audio', false);
                await api.post(`/projects/${this.projectId}/audio-tracks`, {
                    asset_id: asset.id,
                    file_path: asset.file_path,
                    volume: this.audioTrack.volume,
                    ducking_enabled: this.audioTrack.ducking_enabled,
                });
                await this.loadAudioTrack();
                this.message = 'Trilha importada';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao importar áudio';
            }
        },

        async uploadAsset(file, type, attachToSlide = true) {
            const form = new FormData();
            form.append('file', file);
            form.append('type', type);

            if (this.attachPaidLicenseOnUpload && this.defaultStockLicense) {
                form.append('stock_license_id', this.defaultStockLicense.id);
                const baseName = file.name.replace(/\.[^.]+$/, '');
                form.append('item_title', baseName);
            }

            const { data: asset } = await api.post(
                `/projects/${this.projectId}/assets/upload`,
                form,
                { headers: { 'Content-Type': 'multipart/form-data' } }
            );

            if (attachToSlide && this.selectedSlide && type === 'image') {
                this.selectedSlide.image_path = asset.file_path;
                this.selectedSlide.image_url = `/api/projects/${this.projectId}/assets/${asset.id}`;
                await this.saveSlide();
            }

            if (asset.stock_license_id) {
                await this.syncPublish();
            }

            return asset;
        },

        async saveAudioTrack() {
            if (!this.audioTrack?.id) return;
            try {
                const { data } = await api.put(
                    `/projects/${this.projectId}/audio-tracks/${this.audioTrack.id}`,
                    this.audioTrack
                );
                this.audioTrack = data;
                this.message = 'Trilha atualizada';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar trilha';
            }
        },

        async searchMedia() {
            const query = this.mediaQuery.trim();
            if (query.length < 2) {
                this.error = 'Digite pelo menos 2 caracteres para buscar.';
                return;
            }
            this.mediaSearching = true;
            this.mediaErrors = [];
            this.error = '';
            try {
                const { data } = await api.get('/media/search', {
                    params: {
                        query,
                        source: this.mediaSource,
                        type: this.mediaType,
                    },
                });
                this.mediaResults = data.results || [];
                this.mediaErrors = data.errors || [];
                if (this.mediaResults.length) {
                    const hint = data.search?.translated
                        ? ` (${data.search.query} → ${data.search.primary})`
                        : '';
                    this.message = `${this.mediaResults.length} resultado(s)${hint} — clique para inserir`;
                } else if (data.search?.translated) {
                    this.message = `Buscamos também como "${data.search.primary}" — nenhum resultado ainda.`;
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro na busca';
            } finally {
                this.mediaSearching = false;
            }
        },

        async importMedia(item) {
            if (!this.selectedSlide && item.type !== 'audio') {
                this.error = 'Selecione um slide antes de inserir mídia.';
                return;
            }
            const target = item.type === 'audio' ? 'audio_track' : 'slide';
            try {
                const { data } = await api.post(`/projects/${this.projectId}/media/import`, {
                    item,
                    target,
                    slide_id: this.selectedSlide?.id,
                });

                this.applyPublish(data.publish);

                if (target === 'slide' && this.selectedSlide) {
                    const asset = data.asset || data;
                    const slideFromServer = data.slide;

                    if (slideFromServer) {
                        const idx = this.slides.findIndex(s => s.id === slideFromServer.id);
                        const merged = this.enrichSlide({ ...(idx >= 0 ? this.slides[idx] : {}), ...slideFromServer });
                        if (idx >= 0) {
                            this.slides[idx] = merged;
                        }
                        if (this.selectedSlide?.id === merged.id) {
                            this.selectedSlide = merged;
                        }
                    } else {
                        if (item.type === 'video') {
                            this.selectedSlide.video_path = asset.file_path;
                            this.selectedSlide.video_url = this.fileUrl('assets', asset.file_path.split(/[/\\]/).pop());
                            if (item.duration_seconds && item.duration_seconds > 0) {
                                this.selectedSlide.duration_seconds = Math.min(Math.max(item.duration_seconds, 1), 60);
                            }
                        } else {
                            this.selectedSlide.image_path = asset.file_path;
                            this.selectedSlide.image_url = `/api/projects/${this.projectId}/assets/${asset.id}`;
                        }
                        await this.saveSlide();
                    }

                    this.message = data.publish?.message || 'Mídia inserida — publicação atualizada automaticamente';
                } else {
                    await this.loadAudioTrack();
                    this.message = data.publish?.message || 'Áudio inserido — créditos incluídos automaticamente';
                }

                await this.loadDownloads();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao importar';
            }
        },

        async loadNarration() {
            const { data } = await api.get(`/projects/${this.projectId}/narration`);
            if (data?.audio_path) {
                data.audio_url = this.fileUrl('audio', data.audio_path.split(/[/\\]/).pop());
            }
            this.narration = data?.id ? data : null;
        },

        slideNarrationText() {
            const slide = this.selectedSlide;
            if (!slide) return '';
            const text = (slide.narration_text || '').trim();
            if (text) return formatNarrationText(text);
            return formatNarrationText([slide.title, slide.subtitle, slide.body_text].filter(v => v?.trim()).join('. '));
        },

        async testNarration() {
            const text = this.slideNarrationText();
            if (!text) {
                this.error = 'Escreva texto de narração ou preencha título/subtítulo do slide.';
                return;
            }
            this.previewLoading = true;
            this.error = '';
            try {
                const { data } = await api.post(`/projects/${this.projectId}/narration/preview`, {
                    text,
                    voice: this.voice,
                    engine: this.ttsEngine,
                });
                this.previewAudioUrl = data.audio_url;
                if (data.engine_used && data.engine_used !== this.ttsEngine) {
                    this.ttsEngine = data.engine_used;
                    await this.loadVoices();
                    this.message = `Teste gerado com ${data.engine_used} (Edge falhou — troca automática)`;
                } else {
                    this.message = `Teste gerado (${Math.round(data.duration_seconds || 0)}s) — ouça abaixo`;
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao testar narração';
            } finally {
                this.previewLoading = false;
            }
        },

        async generateNarration() {
            this.narrationLoading = true;
            this.error = '';
            try {
                if (this.fullScript.trim()) {
                    await this.applyFullScript();
                }
                await this.saveSlide();
                const { data } = await api.post(`/projects/${this.projectId}/narration/generate`, {
                    voice: this.voice,
                    engine: this.ttsEngine,
                });
                if (data?.audio_path) {
                    data.audio_url = this.fileUrl('audio', data.audio_path.split(/[/\\]/).pop());
                }
                this.narration = data;
                this.previewAudioUrl = data.audio_url;
                this.message = 'Narração completa gerada — ouça abaixo';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao gerar narração';
            } finally {
                this.narrationLoading = false;
            }
        },

        async syncNarration() {
            try {
                const { data } = await api.post(`/projects/${this.projectId}/narration/sync`);
                this.slides = data.map(s => this.enrichSlide(s));
                this.syncSelection();
                this.message = 'Slides sincronizados';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao sincronizar';
            }
        },

        async loadRenderJobs() {
            const { data } = await api.get(`/projects/${this.projectId}/render-jobs`);
            this.renderJobs = data.map(job => ({
                ...job,
                output_url: job.output_path
                    ? this.fileUrl('exports', job.output_path.split(/[/\\]/).pop())
                    : null,
            }));
        },

        async renderVideo(preset) {
            try {
                await api.post(`/projects/${this.projectId}/render-jobs`, {
                    preset,
                    burn_subtitles: this.burnSubtitles,
                });
                this.message = `Render ${preset} enfileirado`;
                await this.loadRenderJobs();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao enfileirar render';
            }
        },

        async retryRender(job) {
            try {
                await api.post(`/projects/${this.projectId}/render-jobs/${job.id}/retry`);
                this.message = 'Render reenfileirado';
                await this.loadRenderJobs();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao reenfileirar';
            }
        },

        async generateThumb() {
            try {
                await api.post(`/projects/${this.projectId}/thumbnail`);
                this.message = 'Thumbnail gerada';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao gerar thumb';
            }
        },

        async exportSubtitles() {
            try {
                this.error = '';
                const { data } = await api.post(`/projects/${this.projectId}/subtitles`);
                this.message = 'legendas.srt gerado';
                if (data.url) window.open(data.url, '_blank');
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao exportar legendas';
            }
        },

        async exportPsd() {
            try {
                const { data } = await api.post(`/projects/${this.projectId}/export-psd`);
                this.message = 'ZIP PSD/PNG gerado';
                if (data.url) window.open(data.url, '_blank');
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao exportar PSD';
            }
        },

        async exportPackage() {
            try {
                await api.post(`/projects/${this.projectId}/export-packages`, { preset: 'youtube_landscape' });
                this.message = 'Pacote enfileirado';
                await this.loadExportPackages();
                await this.loadDownloads();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao exportar pacote';
            }
        },
    };
};
