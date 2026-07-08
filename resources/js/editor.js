import axios from 'axios';

const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

window.api = axios.create({
    baseURL: '/api',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

window.editorApp = function (projectId) {
    return {
        projectId,
        slides: [],
        selectedSlide: null,
        activeTab: 'roteiro',
        voice: 'pt-BR-FranciscaNeural',
        narration: null,
        narrationLoading: false,
        mediaQuery: '',
        mediaResults: [],
        renderJobs: [],
        saving: false,
        message: '',
        error: '',
        pollInterval: null,

        async init() {
            await this.loadSlides();
            await this.loadNarration();
            await this.loadRenderJobs();
            this.pollInterval = setInterval(() => this.loadRenderJobs(), 3000);
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
                const { data } = await api.put(
                    `/projects/${this.projectId}/slides/${this.selectedSlide.id}`,
                    this.selectedSlide
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

            const form = new FormData();
            form.append('file', file);

            try {
                const { data: asset } = await api.post(
                    `/projects/${this.projectId}/assets/upload`,
                    form,
                    { headers: { 'Content-Type': 'multipart/form-data' } }
                );
                this.selectedSlide.image_path = asset.file_path;
                this.selectedSlide.image_url = `/api/projects/${this.projectId}/assets/${asset.id}`;
                await this.saveSlide();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro no upload';
            }
        },

        async searchMedia() {
            if (!this.mediaQuery.trim()) return;
            try {
                const { data } = await api.get('/media/search', { params: { query: this.mediaQuery } });
                this.mediaResults = data.results || [];
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro na busca Pexels';
            }
        },

        async importPhoto(photo) {
            if (!this.selectedSlide) return;
            try {
                const { data: asset } = await api.post(`/projects/${this.projectId}/media/import`, { photo });
                this.selectedSlide.image_path = asset.file_path;
                this.selectedSlide.image_url = `/api/projects/${this.projectId}/assets/${asset.id}`;
                await this.saveSlide();
                this.message = 'Imagem inserida';
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
                    generate_thumb: false,
                });
                this.message = 'Render enfileirado';
                await this.loadRenderJobs();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao enfileirar render';
            }
        },

        async generateThumb() {
            try {
                const { data } = await api.post(`/projects/${this.projectId}/thumbnail`);
                this.message = 'Thumbnail gerada: ' + data.url;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao gerar thumb';
            }
        },
    };
};
