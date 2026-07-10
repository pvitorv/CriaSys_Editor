import axios from 'axios';
import { formatNarrationText, parseScript } from './scriptParser';
import { applyAutomaticDurations, probeVideoFileDuration, DURATION_MIN, DURATION_MAX } from './slideDuration';
import {
    normalizeTextStyle as normalizeSlideTextStyle,
    slideBodyStyle as buildSlideBodyStyle,
    defaultTextStyle,
} from './slideTextStyle';
import { PreviewAudioMixer } from './previewAudio';

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
        editorTabs: [
            { id: 'roteiro', label: 'Roteiro' },
            { id: 'audio', label: 'Trilhas & FX' },
            { id: 'biblioteca', label: 'Biblioteca' },
            { id: 'exportar', label: 'Exportar' },
            { id: 'thumbnail', label: 'Thumbnail' },
        ],
        selectedSoundEffectId: null,
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
        mediaLibraryProviders: null,
        mediaSfxStartAt: 0,
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
        audioTracks: [],
        selectedMusicSlot: 0,
        soundEffects: [],
        previewMixer: null,
        renderJobs: [],
        saving: false,
        message: '',
        error: '',
        pollInterval: null,
        saveTimeout: null,
        descriptionSaveTimeout: null,
        dragFromIndex: null,
        burnSubtitles: false,
        timelineZoom: 18,
        timelineZoomManual: false,
        topPanelHeight: 0,
        timelineWidthRatio: 0.7,
        timelinePlayheadSec: 0,
        timelineTool: 'select',
        timelineSelectedClip: null,
        timelineCutMarkIn: null,
        timelineCutMarkOut: null,
        timelineSelectedClipLabel: '',
        thumbnailTemplates: [],
        thumbnailFonts: [],
        thumbnailSettings: {
            template: 'classic',
            slide_index: 0,
            title_text: '',
            subtitle_text: '',
            title_color: '#ffffff',
            subtitle_color: '#e5e7eb',
            accent_color: '#8b5cf6',
            background_color: '#18181b',
            font_family: 'arial',
            title_size: 64,
            subtitle_size: 32,
            brightness: 0,
            contrast: 0,
            overlay_opacity: 45,
            text_align: 'center',
            vertical_align: 'center',
        },
        thumbnailPreviewUrl: null,
        thumbnailSaving: false,
        thumbnailPreviewTimeout: null,

        get previewSlide() {
            if (this.previewPlaying && this.slides.length) {
                return this.slides[this.previewIndex] ?? this.selectedSlide;
            }
            return this.selectedSlide;
        },

        get previewDisplayText() {
            const slide = this.previewSlide;

            if (slide) {
                const body = (slide.body_text || '').trim();
                if (body) return body;

                const narr = (slide.narration_text || '').trim();
                if (narr) return narr;

                return '';
            }

            if (this.fullScript?.trim()) return this.fullScript.trim();
            if (this.narration?.full_script?.trim()) return this.narration.full_script.trim();

            return '';
        },

        get canPlayPreview() {
            if (this.slides.length > 0) return true;

            return !!this.fullScript?.trim() || !!this.narration?.audio_url;
        },

        get previewModeLabel() {
            if (!this.slides.length) return 'Roteiro / narração';
            if (this.previewPlaying) {
                return `Slide ${this.previewIndex + 1}/${this.slides.length}`;
            }
            return '';
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

        get timelineTotalSeconds() {
            return this.slides.reduce((sum, s) => sum + parseFloat(s.duration_seconds || 5), 0);
        },

        get timelineTrackWidthPx() {
            const gaps = Math.max(0, this.slides.length - 1) * 8;
            const content = this.timelineTotalSeconds * this.timelineZoom + gaps + 16;

            return Math.max(content, this.timelineViewportWidthPx());
        },

        get timelineTicks() {
            const total = this.timelineTotalSeconds;
            const step = total > 120 ? 30 : total > 60 ? 15 : total > 20 ? 5 : 2;
            const ticks = [];
            for (let sec = 0; sec <= total + 0.01; sec += step) {
                ticks.push({
                    sec,
                    px: sec * this.timelineZoom,
                    label: this.formatTimelineTime(sec),
                });
            }
            return ticks;
        },

        get audioModulesCount() {
            const tracks = this.audioTracks.filter((t) => t.file_path).length;

            return tracks + this.soundEffects.length;
        },

        get showTimelineAudioLanes() {
            return this.slides.length > 0
                || this.audioTracks.some((t) => t.file_path)
                || this.soundEffects.length > 0
                || !!this.narration?.audio_url;
        },

        async init() {
            await Promise.all([
                this.loadSlides(),
                this.loadNarration(),
                this.loadRenderJobs(),
                this.loadDownloads(),
                this.loadExportPresets(),
                this.loadExportPackages(),
                this.loadAudioTracks(),
                this.loadSoundEffects(),
                this.loadMediaProviders(),
                this.loadTtsEngines(),
                this.loadProjectCredits(),
                this.loadPlatformDescriptions(),
                this.loadStockLicenses(),
                this.loadThumbnailCatalog(),
                this.loadThumbnailSettings(),
            ]);
            await this.loadVoices();
            await this.syncPublish();
            this.previewMixer = new PreviewAudioMixer();
            this.pollInterval = setInterval(() => {
                this.loadRenderJobs();
                this.loadExportPackages();
                this.loadDownloads();
            }, 3000);

            document.addEventListener('keydown', (e) => this.handleShortcut(e));
            this.syncTimelineZoomToViewport();
            this.initTopPanelHeightSync();
        },

        syncTopPanelHeight() {
            this.$nextTick(() => {
                const el = this.$refs.previewColumn;
                if (el) {
                    this.topPanelHeight = Math.round(el.getBoundingClientRect().height);
                }
            });
        },

        initTopPanelHeightSync() {
            this.syncTopPanelHeight();
            const el = this.$refs.previewColumn;
            if (!el || typeof ResizeObserver === 'undefined') {
                return;
            }
            this._topPanelRo?.disconnect();
            this._topPanelRo = new ResizeObserver(() => this.syncTopPanelHeight());
            this._topPanelRo.observe(el);
        },

        timelineViewportWidthPx() {
            if (typeof window === 'undefined') {
                return 960;
            }
            const root = this.$el?.closest?.('[x-data]') || this.$el;
            const measured = root?.clientWidth;
            const fromViewport = Math.floor(window.innerWidth * this.timelineWidthRatio) - 48;

            return Math.max(320, measured || fromViewport);
        },

        syncTimelineZoomToViewport() {
            if (this.timelineZoomManual || !this.slides.length) {
                return;
            }

            this.$nextTick(() => {
                const viewport = this.timelineViewportWidthPx();
                const gaps = Math.max(0, this.slides.length - 1) * 8;
                const total = this.timelineTotalSeconds;
                if (total <= 0 || viewport <= 0) {
                    return;
                }

                const fit = (viewport - gaps - 32) / total;
                this.timelineZoom = Math.min(72, Math.max(6, Math.round(fit * 10) / 10));
            });
        },

        resetTimelineZoom() {
            this.timelineZoomManual = false;
            this.syncTimelineZoomToViewport();
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

        slidePreviewText(slide, index = 0) {
            if (!slide) return `Slide ${index + 1}`;
            const body = (slide.body_text || '').trim();
            if (body) {
                const oneLine = body.replace(/\s+/g, ' ');
                return oneLine.length > 72 ? `${oneLine.slice(0, 72)}…` : oneLine;
            }
            const narr = (slide.narration_text || '').trim();
            if (narr) {
                const oneLine = narr.replace(/\s+/g, ' ');
                return oneLine.length > 72 ? `${oneLine.slice(0, 72)}…` : oneLine;
            }
            return slide.title?.trim() || `Slide ${index + 1}`;
        },

        slideSearchQuery(slide) {
            const body = (slide?.body_text || '').trim();
            if (body) return body.replace(/\s+/g, ' ').slice(0, 120);
            const narr = (slide?.narration_text || '').trim();
            if (narr) return narr.replace(/\s+/g, ' ').slice(0, 120);
            return (slide?.title || '').trim();
        },

        slidePayload(slide) {
            return {
                title: slide.title ?? '',
                subtitle: slide.subtitle ?? '',
                body_text: slide.body_text ?? '',
                narration_text: slide.narration_text ?? '',
                duration_seconds: slide.duration_seconds ?? 5,
                duration_mode: slide.duration_mode ?? 'narration',
                video_duration_seconds: slide.video_duration_seconds ?? null,
                transition_type: slide.transition_type ?? 'fade',
                text_style: this.normalizeTextStyle(slide.text_style),
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
            if (!this.canPlayPreview) return;
            this.stopSlideshow();
            this.previewPlaying = true;
            this.previewIndex = 0;
            this.previewPlayToken++;
            this.previewTransitioning = false;
            if (this.slides.length) {
                this.scrollTimelineToSlide(0);
                this.schedulePreviewAdvance();
            }
            this.startPreviewAudioMix();
        },

        stopSlideshow() {
            this.previewPlaying = false;
            if (this.previewMixer) {
                this.previewMixer.stop();
            }
            if (this.previewTimer) {
                clearTimeout(this.previewTimer);
                this.previewTimer = null;
            }
            this.previewTransitioning = false;
        },

        startPreviewAudioMix() {
            if (!this.previewMixer) {
                this.previewMixer = new PreviewAudioMixer();
            }

            const musicTracks = this.audioTracks
                .filter((t) => t?.file_path && t?.audio_url)
                .map((t) => ({
                    audio_url: t.audio_url,
                    volume: t.volume ?? 0.35,
                    start_at: t.start_at ?? 0,
                }));

            const soundEffects = this.soundEffects
                .filter((fx) => fx?.file_path && fx?.audio_url)
                .map((fx) => ({
                    audio_url: fx.audio_url,
                    volume: fx.volume ?? 1,
                    start_at: fx.start_at ?? 0,
                }));

            this.previewMixer.play({
                narrationUrl: this.narration?.audio_url || null,
                musicTracks,
                soundEffects,
                onEnd: () => {
                    if (this.previewPlaying && !this.slides.length) {
                        this.stopSlideshow();
                    }
                },
            });
        },

        previewTextStyle() {
            if (this.previewSlide?.text_style) {
                return this.slideBodyStyle(this.previewSlide);
            }

            return buildSlideBodyStyle(defaultTextStyle());
        },

        previewVerticalAlignClass() {
            const slide = this.previewSlide;
            if (slide?.text_style?.vertical_align) {
                return this.slideVerticalAlignClass(slide);
            }
            return 'justify-center';
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
                this.scrollTimelineToSlide(this.previewIndex);
                this.schedulePreviewAdvance();
                return;
            }

            this.previewTransitionKind = trans === 'slide' ? 'slide' : 'fade';
            this.previewTransitioning = true;
            setTimeout(() => {
                this.previewIndex = (this.previewIndex + 1) % this.slides.length;
                this.previewPlayToken++;
                this.previewTransitioning = false;
                this.scrollTimelineToSlide(this.previewIndex);
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

        async loadMediaProviders() {
            try {
                const { data } = await api.get('/media/providers');
                this.mediaLibraryProviders = data;
            } catch {
                this.mediaLibraryProviders = null;
            }
        },

        mediaSearchType() {
            if (this.mediaType === 'music') return 'audio';
            return this.mediaType;
        },

        setMediaLibraryMode(mode) {
            this.mediaType = mode === 'visual' ? 'image' : mode;
            if (mode === 'music' || mode === 'sfx') {
                this.mediaSource = 'all';
            }
            if (mode === 'visual' || mode === 'image') {
                this.mediaSource = 'all';
            }
        },

        openLibraryForMusic(slot = 0) {
            this.selectedMusicSlot = slot;
            this.setMediaLibraryMode('music');
            this.activeTab = 'biblioteca';
        },

        openLibraryForSfx(startAt = 0) {
            this.mediaSfxStartAt = startAt;
            this.setMediaLibraryMode('sfx');
            this.activeTab = 'biblioteca';
        },

        async loadAudioTracks() {
            const { data } = await api.get(`/projects/${this.projectId}/audio-tracks`);
            const music = data.filter((t) => t.type === 'music');
            this.audioTracks = [0, 1, 2].map((slot) => {
                const track = music.find((t) => (t.track_slot ?? 0) === slot);
                return track ? this.enrichAudioTrack({ ...track, track_slot: slot }) : this.emptyMusicSlot(slot);
            });
            this.audioTrack = this.audioTracks[0];
        },

        emptyMusicSlot(slot) {
            return {
                track_slot: slot,
                id: null,
                type: 'music',
                volume: 0.35,
                start_at: 0,
                trim_in: 0,
                trim_out: null,
                source_duration: null,
                ducking_enabled: slot === 0,
                file_path: null,
                audio_url: null,
                label: `Trilha ${slot + 1}`,
            };
        },

        enrichAudioTrack(track) {
            if (track?.file_path) {
                track.audio_url = this.fileUrl('assets', track.file_path.split(/[/\\]/).pop());
            }
            track.label = track.label || `Trilha ${(track.track_slot ?? 0) + 1}`;
            track.trim_in = parseFloat(track.trim_in) || 0;
            track.trim_out = track.trim_out != null ? parseFloat(track.trim_out) : null;
            track.source_duration = track.source_duration != null ? parseFloat(track.source_duration) : null;

            return track;
        },

        async loadSoundEffects() {
            const { data } = await api.get(`/projects/${this.projectId}/sound-effects`);
            this.soundEffects = data.map((fx) => this.enrichSoundEffect(fx));
        },

        enrichSoundEffect(fx) {
            if (fx?.file_path) {
                fx.audio_url = this.fileUrl('assets', fx.file_path.split(/[/\\]/).pop());
            }
            fx.trim_in = parseFloat(fx.trim_in) || 0;
            fx.trim_out = fx.trim_out != null ? parseFloat(fx.trim_out) : null;
            fx.source_duration = fx.source_duration != null ? parseFloat(fx.source_duration) : null;
            fx.clip_duration = fx.clip_duration != null ? parseFloat(fx.clip_duration) : null;

            return fx;
        },

        async saveMusicTrack(slot) {
            const track = this.audioTracks[slot];
            if (!track?.id) return;
            try {
                const { data } = await api.put(
                    `/projects/${this.projectId}/audio-tracks/${track.id}`,
                    {
                        volume: track.volume,
                        start_at: track.start_at,
                        trim_in: track.trim_in,
                        trim_out: track.trim_out,
                        source_duration: track.source_duration,
                        ducking_enabled: track.ducking_enabled,
                    }
                );
                this.audioTracks[slot] = this.enrichAudioTrack({ ...track, ...data });
                if (slot === 0) this.audioTrack = this.audioTracks[0];
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar trilha';
            }
        },

        async removeMusicTrack(slot) {
            const track = this.audioTracks[slot];
            if (!track?.id) return;
            if (!confirm(`Remover ${track.label}?`)) return;
            await api.delete(`/projects/${this.projectId}/audio-tracks/${track.id}`);
            this.audioTracks[slot] = this.emptyMusicSlot(slot);
            if (slot === 0) this.audioTrack = this.audioTracks[0];
            this.message = 'Trilha removida';
        },

        async saveSoundEffect(fx) {
            if (!fx?.id) return;
            try {
                const { data } = await api.put(
                    `/projects/${this.projectId}/sound-effects/${fx.id}`,
                    {
                        label: fx.label,
                        start_at: fx.start_at,
                        volume: fx.volume,
                        trim_in: fx.trim_in,
                        trim_out: fx.trim_out,
                        source_duration: fx.source_duration,
                        clip_duration: fx.clip_duration,
                    }
                );
                const idx = this.soundEffects.findIndex((e) => e.id === fx.id);
                if (idx >= 0) {
                    this.soundEffects[idx] = this.enrichSoundEffect({ ...fx, ...data });
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar efeito';
            }
        },

        async removeSoundEffect(fx) {
            if (!fx?.id) return;
            if (!confirm('Remover este efeito?')) return;
            await api.delete(`/projects/${this.projectId}/sound-effects/${fx.id}`);
            this.soundEffects = this.soundEffects.filter((e) => e.id !== fx.id);
        },

        async uploadSoundEffect(event) {
            const file = event.target.files[0];
            if (!file) return;
            const startAt = parseFloat(prompt('Início do efeito (segundos):', '0') || '0') || 0;
            try {
                const asset = await this.uploadAsset(file, 'audio', false);
                const { data } = await api.post(`/projects/${this.projectId}/sound-effects`, {
                    asset_id: asset.id,
                    file_path: asset.file_path,
                    label: file.name.replace(/\.[^.]+$/, ''),
                    start_at: startAt,
                    volume: 1,
                });
                this.soundEffects.push(this.enrichSoundEffect(data));
                this.message = `Efeito adicionado aos ${startAt}s`;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao importar efeito';
            }
        },

        async loadSlides() {
            const { data } = await api.get(`/projects/${this.projectId}/slides`);
            this.slides = data.map(s => this.enrichSlide(s));
            this.syncSelection();
            this.buildFullScriptFromSlides();
            this.syncTimelineZoomToViewport();
        },

        enrichSlide(slide) {
            slide.text_style = this.normalizeTextStyle(slide.text_style);
            slide.duration_mode = slide.duration_mode || 'narration';
            if (!(slide.body_text || '').trim() && (slide.narration_text || '').trim()) {
                slide.body_text = slide.narration_text;
            }
            if (slide.image_path) {
                slide.image_url = this.fileUrl('assets', slide.image_path.split(/[/\\]/).pop());
            }
            if (slide.video_path) {
                slide.video_url = this.fileUrl('assets', slide.video_path.split(/[/\\]/).pop());
            }
            return slide;
        },

        normalizeTextStyle(style) {
            return normalizeSlideTextStyle(style);
        },

        slideBodyStyle(slide) {
            return buildSlideBodyStyle(slide?.text_style);
        },

        durationModeLabel(mode) {
            return {
                manual: 'Manual',
                video: 'Vídeo',
                narration: 'Narração',
            }[mode] || 'Narração';
        },

        onManualDurationChange() {
            if (this.selectedSlide) {
                this.selectedSlide.duration_mode = 'manual';
            }
            this.scheduleSave();
        },

        async onDurationModeChange() {
            const slide = this.selectedSlide;
            if (!slide) return;

            if (slide.duration_mode === 'narration') {
                await this.recalculateNarrationDurations();
                return;
            }

            if (slide.duration_mode === 'video' && slide.video_duration_seconds > 0) {
                slide.duration_seconds = Math.round(slide.video_duration_seconds * 10) / 10;
            }

            this.scheduleSave();
        },

        async recalculateNarrationDurations() {
            try {
                const { data } = await api.post(`/projects/${this.projectId}/slides/recalculate-durations`);
                this.slides = data.map((s) => this.enrichSlide(s));
                this.syncSelection();
                this.message = `Tempos recalculados pela narração (${DURATION_MIN}–${DURATION_MAX}s)`;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao recalcular tempos';
            }
        },

        applyLocalAutomaticDurations() {
            applyAutomaticDurations(this.slides);
        },

        slideVerticalAlignClass(slide) {
            const align = slide?.text_style?.vertical_align || 'center';
            if (align === 'top') return 'justify-start';
            if (align === 'bottom') return 'justify-end';

            return 'justify-center';
        },

        syncBodyTextStyle() {
            const slide = this.selectedSlide;
            if (!slide?.text_style) return;
            slide.text_style.title_color = slide.text_style.body_color;
            slide.text_style.title_size = slide.text_style.body_size;
            this.scheduleSave();
        },

        fileUrl(type, filename) {
            return `/api/projects/${this.projectId}/files/${type}/${filename}`;
        },

        selectSlide(slide) {
            if (this.previewPlaying) {
                this.stopSlideshow();
            }
            this.selectedSlide = this.slides.find(s => s.id === slide.id) ?? slide;
            const index = this.slides.findIndex(s => s.id === slide.id);
            if (index >= 0) {
                this.scrollTimelineToSlide(index);
            }
        },

        formatTimelineTime(seconds) {
            const s = Math.max(0, parseFloat(seconds) || 0);
            const m = Math.floor(s / 60);
            const r = Math.round(s % 60);
            if (m > 0) {
                return `${m}:${String(r).padStart(2, '0')}`;
            }

            return `${Math.round(s * 10) / 10}s`;
        },

        timelineClipWidth(slide) {
            const dur = parseFloat(slide?.duration_seconds || 5);

            return Math.max(80, dur * this.timelineZoom);
        },

        timelineSecondsToPx(seconds) {
            return Math.max(0, parseFloat(seconds) || 0) * this.timelineZoom;
        },

        timelineAudioSpanWidth(startAt) {
            const start = Math.max(0, parseFloat(startAt) || 0);
            const span = Math.max(0, this.timelineTotalSeconds - start);

            return Math.max(24, span * this.timelineZoom);
        },

        timelineFxClipWidth() {
            return Math.max(36, this.timelineZoom * 1.5);
        },

        selectSoundEffect(fx) {
            this.selectedSoundEffectId = fx?.id ?? null;
            this.timelineSelectedClip = fx ? { kind: 'sfx', id: fx.id } : null;
            this.timelineSelectedClipLabel = fx?.label || 'Efeito';
            this.activeTab = 'audio';
        },

        selectTimelineNarration() {
            if (!this.narration?.audio_url) return;
            this.timelineSelectedClip = { kind: 'narration' };
            this.timelineSelectedClipLabel = 'Narração';
        },

        selectTimelineMusic(slot) {
            const track = this.audioTracks[slot];
            if (!track?.file_path) return;
            this.timelineSelectedClip = { kind: 'music', slot };
            this.timelineSelectedClipLabel = track.label;
        },

        timelinePxToSeconds(px) {
            return Math.max(0, (parseFloat(px) || 0) / this.timelineZoom);
        },

        timelineEffectiveDuration(item, fallback = 30) {
            const source = parseFloat(item?.source_duration)
                || parseFloat(item?.clip_duration)
                || parseFloat(item?.duration_seconds)
                || fallback;
            const trimIn = parseFloat(item?.trim_in) || 0;
            const trimOut = item?.trim_out != null ? parseFloat(item.trim_out) : source;

            return Math.max(0.1, trimOut - trimIn);
        },

        timelineMusicClipWidth(track) {
            return Math.max(24, this.timelineEffectiveDuration(track, this.timelineTotalSeconds) * this.timelineZoom);
        },

        timelineFxDisplayWidth(fx) {
            const dur = this.timelineEffectiveDuration(fx, fx?.clip_duration || 2);

            return Math.max(36, Math.min(160, dur * this.timelineZoom));
        },

        timelineNarrationWidthPx() {
            const narr = this.narration || {};
            const dur = this.timelineEffectiveDuration(narr, narr.duration_seconds || this.timelineTotalSeconds);

            return Math.max(24, Math.min(this.timelineSecondsToPx(this.timelineTotalSeconds), dur * this.timelineZoom));
        },

        setPlayheadFromTimelineEvent(event) {
            const scroller = this.$refs.timelineScroll;
            const area = this.$refs.timelineTrackArea;
            if (!scroller || !area) return;

            const rect = area.getBoundingClientRect();
            const x = event.clientX - rect.left + scroller.scrollLeft;
            const sec = Math.max(0, Math.min(this.timelineTotalSeconds, this.timelinePxToSeconds(x)));
            this.timelinePlayheadSec = Math.round(sec * 100) / 100;
        },

        markTimelineCutIn() {
            this.timelineCutMarkIn = this.timelinePlayheadSec;
        },

        markTimelineCutOut() {
            this.timelineCutMarkOut = this.timelinePlayheadSec;
        },

        clearTimelineCutMarks() {
            this.timelineCutMarkIn = null;
            this.timelineCutMarkOut = null;
        },

        async applyTimelineTrim() {
            const clip = this.timelineSelectedClip;
            if (!clip) {
                this.error = 'Selecione uma faixa de áudio, efeito ou narração na timeline.';
                return;
            }

            const markIn = this.timelineCutMarkIn ?? this.timelinePlayheadSec;
            const markOut = this.timelineCutMarkOut ?? this.timelinePlayheadSec;
            if (markOut <= markIn) {
                this.error = 'Marca de saída deve ser depois da entrada.';
                return;
            }

            const span = Math.round((markOut - markIn) * 100) / 100;

            try {
                if (clip.kind === 'narration' && this.narration?.id) {
                    this.narration.trim_in = markIn;
                    this.narration.trim_out = markOut;
                    await api.put(`/projects/${this.projectId}/narration`, {
                        trim_in: markIn,
                        trim_out: markOut,
                    });
                } else if (clip.kind === 'music' && clip.slot != null) {
                    const track = this.audioTracks[clip.slot];
                    if (!track?.id) return;
                    track.start_at = markIn;
                    track.trim_out = (parseFloat(track.trim_in) || 0) + span;
                    if (track.source_duration != null) {
                        track.trim_out = Math.min(track.trim_out, track.source_duration);
                    }
                    await this.saveMusicTrack(clip.slot);
                } else if (clip.kind === 'sfx' && clip.id) {
                    const fx = this.soundEffects.find((e) => e.id === clip.id);
                    if (!fx) return;
                    fx.start_at = markIn;
                    fx.clip_duration = span;
                    fx.trim_out = (parseFloat(fx.trim_in) || 0) + span;
                    await this.saveSoundEffect(fx);
                }
                this.message = `Corte aplicado (${this.formatTimelineTime(markIn)} → ${this.formatTimelineTime(markOut)})`;
                this.clearTimelineCutMarks();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao aplicar corte';
            }
        },

        async saveNarrationTrim() {
            if (!this.narration?.id) return;
            try {
                const { data } = await api.put(`/projects/${this.projectId}/narration`, {
                    trim_in: this.narration.trim_in ?? 0,
                    trim_out: this.narration.trim_out,
                });
                this.narration = { ...this.narration, ...data };
                this.message = 'Corte da narração salvo';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar corte da narração';
            }
        },

        openAudioTab() {
            this.activeTab = 'audio';
        },

        timelineOffsetPx(index) {
            let offset = 0;
            for (let i = 0; i < index; i++) {
                offset += this.timelineClipWidth(this.slides[i]) + 8;
            }

            return offset;
        },

        scrollTimelineToSlide(index) {
            this.$nextTick(() => {
                const scroller = this.$refs.timelineScroll;
                if (!scroller || index < 0) return;
                const offset = this.timelineOffsetPx(index);
                const clipW = this.timelineClipWidth(this.slides[index]);
                const viewW = scroller.clientWidth || 400;
                const target = offset - (viewW / 2) + (clipW / 2);
                scroller.scrollTo({ left: Math.max(0, target), behavior: 'smooth' });
            });
        },

        adjustTimelineZoom(delta) {
            this.timelineZoomManual = true;
            this.timelineZoom = Math.min(72, Math.max(6, this.timelineZoom + delta));
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
            const raw = this.slideSearchQuery(slide);
            if (!raw) return;
            if (!this.mediaQuery.trim() || this.mediaQuery === this._lastSlideSearchRaw) {
                this.resolveMediaQuery(raw).then((query) => {
                    this.mediaQuery = query;
                    this._lastSlideSearchRaw = raw;
                    this.searchMedia();
                });
            }
        },

        async searchFromSlideBody() {
            const slide = this.selectedSlide;
            const raw = this.slideSearchQuery(slide);
            if (!raw) {
                this.error = 'Escreva o corpo do slide antes de buscar imagens.';
                return;
            }
            this.activeTab = 'biblioteca';
            this.mediaQuery = await this.resolveMediaQuery(raw);
            this._lastSlideSearchRaw = raw;
            this.searchMedia();
        },

        async resolveMediaQuery(raw) {
            try {
                const { data } = await api.get('/media/suggest-query', { params: { query: raw } });
                return data.primary || data.extracted || raw;
            } catch {
                return raw.replace(/\s+/g, ' ').slice(0, 80);
            }
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
                text_style: defaultTextStyle(),
                duration_mode: 'narration',
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

        copyBodyToNarration() {
            const slide = this.selectedSlide;
            if (!slide) return;
            const text = (slide.body_text || '').trim();
            if (!text) {
                this.error = 'Escreva o corpo do slide primeiro.';
                return;
            }
            slide.narration_text = formatNarrationText(text);
            this.scheduleSave();
            this.message = 'Corpo copiado para narração';
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
                    blocks: parsed.blocks.map(({ narration_text, body_text, kind, section_title }) => ({
                        narration_text,
                        body_text,
                        kind,
                        section_title,
                    })),
                    trim_extra_slides: fromPaste || trimExtra,
                });
                this.slides = data.map(s => this.enrichSlide(s));
                this.syncSelection();
                if (!silent) {
                    this.message = `Roteiro aplicado: ${parsed.stats.slides} slide(s) — tempos ajustados automaticamente`;
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
                const duration = await probeVideoFileDuration(file);
                const asset = await this.uploadAsset(file, 'video', false);
                this.selectedSlide.video_path = asset.file_path;
                this.selectedSlide.video_url = this.fileUrl('assets', asset.file_path.split(/[/\\]/).pop());
                this.selectedSlide.duration_mode = 'video';
                if (duration && duration > 0) {
                    this.selectedSlide.video_duration_seconds = duration;
                    this.selectedSlide.duration_seconds = Math.round(duration * 10) / 10;
                }
                await this.saveSlide();
                this.message = duration
                    ? `Vídeo inserido — duração ${Math.round(duration * 10) / 10}s (corrido)`
                    : 'Vídeo inserido no slide';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao enviar vídeo';
            }
        },

        clearSlideVideo() {
            if (!this.selectedSlide) return;
            this.selectedSlide.video_path = null;
            this.selectedSlide.video_url = null;
            this.selectedSlide.video_duration_seconds = null;
            if (this.selectedSlide.duration_mode === 'video') {
                this.selectedSlide.duration_mode = 'narration';
            }
            this.scheduleSave();
        },

        async uploadAudio(event) {
            const file = event.target.files[0];
            if (!file) return;
            const slot = this.selectedMusicSlot ?? 0;
            const track = this.audioTracks[slot] ?? this.emptyMusicSlot(slot);

            try {
                const asset = await this.uploadAsset(file, 'audio', false);
                const { data } = await api.post(`/projects/${this.projectId}/audio-tracks`, {
                    asset_id: asset.id,
                    file_path: asset.file_path,
                    track_slot: slot,
                    volume: track.volume ?? 0.35,
                    start_at: track.start_at ?? 0,
                    ducking_enabled: track.ducking_enabled ?? true,
                });
                this.audioTracks[slot] = this.enrichAudioTrack(data);
                if (slot === 0) this.audioTrack = this.audioTracks[0];
                this.message = `${this.audioTracks[slot].label} importada`;
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
            await this.saveMusicTrack(0);
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
                        type: this.mediaSearchType(),
                    },
                });
                this.mediaResults = data.results || [];
                this.mediaErrors = data.errors || [];
                if (this.mediaResults.length) {
                    const search = data.search || {};
                    const hint = search.hint
                        ? ` — ${search.hint}`
                        : search.translated
                            ? ` (${search.query} → ${search.primary})`
                            : '';
                    this.message = `${this.mediaResults.length} resultado(s)${hint} — clique para inserir`;
                } else if (data.search?.hint || data.search?.translated) {
                    this.message = (data.search.hint || `Buscamos como "${data.search.primary}"`) + ' — nenhum resultado ainda.';
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro na busca';
            } finally {
                this.mediaSearching = false;
            }
        },

        async importMedia(item) {
            const isMusic = item.type === 'audio' || this.mediaType === 'music';
            const isSfx = item.type === 'sfx' || this.mediaType === 'sfx';

            if (!isMusic && !isSfx && !this.selectedSlide) {
                this.error = 'Selecione um slide antes de inserir mídia.';
                return;
            }

            const target = isSfx ? 'sound_effect' : (isMusic ? 'audio_track' : 'slide');
            const payload = {
                item,
                target,
                slide_id: this.selectedSlide?.id,
            };

            if (isMusic) {
                payload.track_slot = this.selectedMusicSlot ?? 0;
            }

            if (isSfx) {
                const startAt = parseFloat(
                    prompt('Início do efeito na timeline (segundos):', String(this.mediaSfxStartAt ?? 0))
                ) || 0;
                payload.start_at = startAt;
                payload.label = item.title || 'Efeito';
            }

            try {
                const { data } = await api.post(`/projects/${this.projectId}/media/import`, payload);

                this.applyPublish(data.publish);

                if (target === 'slide' && this.selectedSlide) {
                    const asset = data.asset;
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
                    } else if (asset) {
                        if (item.type === 'video') {
                            this.selectedSlide.video_path = asset.file_path;
                            this.selectedSlide.video_url = this.fileUrl('assets', asset.file_path.split(/[/\\]/).pop());
                            this.selectedSlide.duration_mode = 'video';
                            if (item.duration_seconds && item.duration_seconds > 0) {
                                this.selectedSlide.video_duration_seconds = item.duration_seconds;
                                this.selectedSlide.duration_seconds = Math.round(item.duration_seconds * 10) / 10;
                            }
                        } else {
                            this.selectedSlide.image_path = asset.file_path;
                            this.selectedSlide.image_url = `/api/projects/${this.projectId}/assets/${asset.id}`;
                        }
                        await this.saveSlide();
                    }

                    this.message = data.publish?.message || 'Mídia inserida — créditos atualizados na exportação';
                } else if (data.audio_track) {
                    const slot = data.audio_track.track_slot ?? 0;
                    this.audioTracks[slot] = this.enrichAudioTrack(data.audio_track);
                    if (slot === 0) this.audioTrack = this.audioTracks[0];
                    this.message = data.publish?.message || `${this.audioTracks[slot].label} importada — licença registrada`;
                    this.activeTab = 'audio';
                } else if (data.sound_effect) {
                    this.soundEffects.push(this.enrichSoundEffect(data.sound_effect));
                    this.message = data.publish?.message || `Efeito "${data.sound_effect.label}" adicionado — crédito na descrição`;
                    this.activeTab = 'audio';
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
            if (data?.id) {
                data.trim_in = parseFloat(data.trim_in) || 0;
                data.trim_out = data.trim_out != null ? parseFloat(data.trim_out) : null;
            }
            this.narration = data?.id ? data : null;
        },

        slideNarrationText() {
            const slide = this.selectedSlide;
            if (!slide) return '';
            const text = (slide.narration_text || '').trim();
            if (text) return formatNarrationText(text);
            return formatNarrationText((slide.body_text || '').trim() || (slide.narration_text || '').trim());
        },

        async testNarration() {
            const text = this.slideNarrationText();
            if (!text) {
                this.error = 'Escreva narração ou preencha o corpo do slide.';
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
            await this.generateThumbnailFinal(false);
        },

        async loadThumbnailCatalog() {
            try {
                const { data } = await api.get('/thumbnail/templates');
                this.thumbnailTemplates = data.templates || [];
                this.thumbnailFonts = data.fonts || [];
                if (data.defaults) {
                    this.thumbnailSettings = { ...this.thumbnailSettings, ...data.defaults };
                }
            } catch (_) {
                /* opcional */
            }
        },

        async loadThumbnailSettings() {
            try {
                const { data } = await api.get(`/projects/${this.projectId}/thumbnail`);
                this.thumbnailSettings = { ...this.thumbnailSettings, ...data };
            } catch (_) {
                /* primeiro uso */
            }
        },

        scheduleThumbnailPreview() {
            clearTimeout(this.thumbnailPreviewTimeout);
            this.thumbnailPreviewTimeout = setTimeout(() => this.saveAndPreviewThumbnail(), 600);
        },

        async saveThumbnailSettings() {
            this.thumbnailSaving = true;
            try {
                const { data } = await api.put(`/projects/${this.projectId}/thumbnail`, this.thumbnailSettings);
                this.thumbnailSettings = { ...this.thumbnailSettings, ...data };
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar thumbnail';
            } finally {
                this.thumbnailSaving = false;
            }
        },

        async saveAndPreviewThumbnail() {
            await this.saveThumbnailSettings();
            await this.generateThumbnailFinal(true);
        },

        async generateThumbnailFinal(preview = false) {
            try {
                const { data } = await api.post(`/projects/${this.projectId}/thumbnail/generate`, {
                    slide_index: this.thumbnailSettings.slide_index,
                    preview,
                });
                if (data.url) {
                    this.thumbnailPreviewUrl = data.url;
                }
                this.message = preview ? 'Preview da thumbnail atualizado' : 'Thumbnail gerada';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao gerar thumbnail';
            }
        },

        selectThumbnailTemplate(slug) {
            this.thumbnailSettings.template = slug;
            this.scheduleThumbnailPreview();
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
