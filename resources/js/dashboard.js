import axios from 'axios';

const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

const api = axios.create({
    baseURL: '/api',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

window.dashboardApp = function () {
    return {
        message: '',
        error: '',

        async duplicateProject(projectId) {
            this.error = '';
            try {
                await api.post(`/projects/${projectId}/duplicate`);
                this.message = 'Projeto duplicado';
                setTimeout(() => window.location.reload(), 600);
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao duplicar';
            }
        },

        async archiveProject(projectId) {
            if (!confirm('Arquivar este projeto?')) return;
            this.error = '';
            try {
                await api.post(`/projects/${projectId}/archive`);
                this.message = 'Projeto arquivado';
                setTimeout(() => window.location.reload(), 600);
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao arquivar';
            }
        },

        async deleteProject(projectId) {
            if (!confirm('Excluir permanentemente este projeto?')) return;
            this.error = '';
            try {
                await api.delete(`/projects/${projectId}`);
                this.message = 'Projeto excluído';
                setTimeout(() => window.location.reload(), 600);
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao excluir';
            }
        },

        async importBundle(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file) return;

            this.error = '';
            this.message = 'Importando bundle…';

            const form = new FormData();
            form.append('bundle', file);

            try {
                const { data } = await api.post('/projects/import-bundle', form, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                this.message = data.message || 'Projeto importado';
                if (data.editor_url) {
                    setTimeout(() => { window.location.href = data.editor_url; }, 800);
                } else {
                    setTimeout(() => window.location.reload(), 800);
                }
            } catch (e) {
                this.message = '';
                const errors = e.response?.data?.errors;
                this.error = errors?.bundle?.[0]
                    || e.response?.data?.message
                    || 'Erro ao importar bundle';
            }
        },
    };
};
