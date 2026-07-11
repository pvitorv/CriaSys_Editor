import { ipcMain } from 'electron';
import { getLaravelPort } from './laravel.js';

export function registerRenderIpc() {
    ipcMain.handle('criasys:getRenderStatus', async () => {
        try {
            const port = getLaravelPort();
            const res = await fetch(`http://127.0.0.1:${port}/up`);
            return { laravel: res.ok, message: res.ok ? 'Servidor ativo' : 'Servidor indisponível' };
        } catch {
            return { laravel: false, message: 'Servidor Laravel offline' };
        }
    });
}
