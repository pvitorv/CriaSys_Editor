import { ipcMain } from 'electron';

export function registerRenderIpc() {
    ipcMain.handle('criasys:getRenderStatus', async () => {
        try {
            const res = await fetch('http://127.0.0.1:8000/up');
            return { laravel: res.ok, message: res.ok ? 'Servidor ativo' : 'Servidor indisponível' };
        } catch {
            return { laravel: false, message: 'Servidor Laravel offline' };
        }
    });
}
