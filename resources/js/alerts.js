window.alertsApp = function () {
    return {
        alerts: [],

        async init() {
            await this.load();
            setInterval(() => this.load(), 30000);
        },

        async load() {
            try {
                const { data } = await api.get('/alerts/unread');
                this.alerts = data;
            } catch {
                // usuário não autenticado ou API indisponível
            }
        },

        async dismiss(id) {
            try {
                await api.post(`/alerts/${id}/read`);
                this.alerts = this.alerts.filter(a => a.id !== id);
            } catch {
                //
            }
        },
    };
};
