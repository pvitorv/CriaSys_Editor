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
    };
};
