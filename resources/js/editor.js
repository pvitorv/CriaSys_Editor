import axios from 'axios';

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

window.editorApp = function (projectId) {
    return {
        projectId,
        slides: [],
        selectedSlide: null,
        activeTab: 'roteiro',
        voice: 'pt-BR-FranciscaNeural',
        ttsEngine: 'edge',
        ttsEngines: [],
        narration: null,
        narrationLoading: false,
        mediaQuery: '',
        mediaSource: 'all',
        mediaType: 'image',
        mediaResults: [],
        mediaErrors: [],
        exportPresets: [],
        exportPackages: [],
        audioTrack: { volume: 0.35, ducking_enabled: true },
        renderJobs: [],
        saving: false,
        message: '',
        error: '',
        pollInterval: null,
        saveTimeout: null,
        dragFromIndex: null,
        burnSubtitles: false,

        async init() {
            await Promise.all([
                this.loadSlides(),
                this.loadNarration(),
                this.loadRenderJobs(),
                this.loadExportPresets(),
                this.loadExportPackages(),
                this.loadAudioTrack(),
                this.loadTtsEngines(),
            ]);
            this.pollInterval = setInterval(() => {
                this.loadRenderJobs();
                this.loadExportPackages();
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
                if (this.selectedSlide) {
                    this.selectedSlide = this.slides.find(s => s.id === this.selectedSlide.id) || this.slides[0];
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao reordenar slides';
                await this.loadSlides();
            }
        },

        scheduleSave() {
            clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(() => this.saveSlide(), 800);
        },

        async loadTtsEngines() {
            const { data } = await api.get('/tts/engines');
            this.ttsEngines = data;
            const available = data.find(e => e.slug === this.ttsEngine && e.available);
            if (!available && data.length) {
                const first = data.find(e => e.available);
                if (first) this.ttsEngine = first.slug;
            }
        },

        async loadExportPresets() {
            const { data } = await api.get('/export-presets');
            this.exportPresets = data;
        },

        async loadExportPackages() {
            const { data } = await api.get(`/projects/${this.projectId}/export-packages`);
            this.exportPackages = data;
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
            if (!this.selectedSlide && this.slides.length) {
                this.selectSlide(this.slides[0]);
            }
        },

        enrichSlide(slide) {
            if (slide.image_path) {
                slide.image_url = this.fileUrl('assets', slide.image_path.split(/[/\\]/).pop());
            }
            return slide;
        },

        fileUrl(type, filename) {
            return `/api/projects/${this.projectId}/files/${type}/${filename}`;
        },

        selectSlide(slide) {
            this.selectedSlide = slide;
        },

        async addSlide() {
            const { data } = await api.post(`/projects/${this.projectId}/slides`, {
                title: `Slide ${this.slides.length + 1}`,
            });
            this.slides.push(this.enrichSlide(data));
            this.selectSlide(this.slides[this.slides.length - 1]);
        },

        async removeSlide(slide) {
            if (!confirm('Remover este slide?')) return;
            await api.delete(`/projects/${this.projectId}/slides/${slide.id}`);
            this.slides = this.slides.filter(s => s.id !== slide.id);
            this.selectedSlide = this.slides[0] || null;
        },

        async saveSlide() {
            if (!this.selectedSlide) return;
            this.saving = true;
            this.error = '';
            try {
                const payload = { ...this.selectedSlide };
                delete payload.image_url;
                const { data } = await api.put(
                    `/projects/${this.projectId}/slides/${this.selectedSlide.id}`,
                    payload
                );
                Object.assign(this.selectedSlide, this.enrichSlide(data));
                const idx = this.slides.findIndex(s => s.id === data.id);
                if (idx >= 0) this.slides[idx] = this.selectedSlide;
                this.message = 'Salvo';
                setTimeout(() => this.message = '', 2000);
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar';
            } finally {
                this.saving = false;
            }
        },

        async uploadImage(event) {
            const file = event.target.files[0];
            if (!file || !this.selectedSlide) return;
            await this.uploadAsset(file, 'image');
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

            const { data: asset } = await api.post(
                `/projects/${this.projectId}/assets/upload`,
                form,
                { headers: { 'Content-Type': 'multipart/form-data' } }
            );

            if (attachToSlide && this.selectedSlide) {
                this.selectedSlide.image_path = asset.file_path;
                this.selectedSlide.image_url = `/api/projects/${this.projectId}/assets/${asset.id}`;
                await this.saveSlide();
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
            if (!this.mediaQuery.trim()) return;
            this.mediaErrors = [];
            try {
                const { data } = await api.get('/media/search', {
                    params: {
                        query: this.mediaQuery,
                        source: this.mediaSource,
                        type: this.mediaType,
                    },
                });
                this.mediaResults = data.results || [];
                this.mediaErrors = data.errors || [];
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro na busca';
            }
        },

        async importMedia(item) {
            const target = item.type === 'audio' ? 'audio_track' : 'slide';
            try {
                const { data } = await api.post(`/projects/${this.projectId}/media/import`, { item, target });
                if (target === 'slide' && this.selectedSlide) {
                    const asset = data.asset || data;
                    this.selectedSlide.image_path = asset.file_path;
                    this.selectedSlide.image_url = `/api/projects/${this.projectId}/assets/${asset.id}`;
                    await this.saveSlide();
                    this.message = 'Imagem inserida';
                } else {
                    await this.loadAudioTrack();
                    this.message = 'Áudio inserido na trilha';
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao importar';
            }
        },

        async loadNarration() {
            const { data } = await api.get(`/projects/${this.projectId}/narration`);
            if (data?.audio_path) {
                data.audio_url = this.fileUrl('audio', data.audio_path.split(/[/\\]/).pop());
            }
            this.narration = data;
        },

        async generateNarration() {
            this.narrationLoading = true;
            this.error = '';
            try {
                const { data } = await api.post(`/projects/${this.projectId}/narration/generate`, {
                    voice: this.voice,
                    engine: this.ttsEngine,
                });
                if (data?.audio_path) {
                    data.audio_url = this.fileUrl('audio', data.audio_path.split(/[/\\]/).pop());
                }
                this.narration = data;
                this.message = 'Narração gerada';
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
                if (this.selectedSlide) {
                    this.selectedSlide = this.slides.find(s => s.id === this.selectedSlide.id) || this.slides[0];
                }
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
                const { data } = await api.post(`/projects/${this.projectId}/thumbnail`);
                this.message = 'Thumbnail gerada';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao gerar thumb';
            }
        },

        async exportSubtitles() {
            try {
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
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao exportar pacote';
            }
        },
    };
};
