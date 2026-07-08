import { app, BrowserWindow, dialog } from 'electron';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { registerFilesystemIpc } from './ipc/filesystem.js';
import { registerLaravelIpc, startLaravel, stopLaravel } from './ipc/laravel.js';
import { registerRenderIpc } from './ipc/render.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const isDev = !app.isPackaged;
const projectRoot = isDev
    ? path.resolve(__dirname, '..')
    : app.getAppPath();

let mainWindow = null;

function getFfmpegPath() {
    const platform = process.platform === 'win32' ? 'win' : process.platform === 'darwin' ? 'mac' : 'linux';
    const binary = process.platform === 'win32' ? 'ffmpeg.exe' : 'ffmpeg';
    const bundled = isDev
        ? path.join(__dirname, 'ffmpeg', platform, binary)
        : path.join(process.resourcesPath, 'ffmpeg', platform, binary);

    try {
        if (fs.existsSync(bundled)) {
            return bundled;
        }
    } catch {
        // fallback to PATH
    }

    return process.platform === 'win32' ? 'ffmpeg.exe' : 'ffmpeg';
}

async function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1400,
        height: 900,
        minWidth: 1024,
        minHeight: 700,
        title: 'CriaSys Editor',
        backgroundColor: '#09090b',
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
        },
    });

    const url = process.env.CRIASYS_URL || 'http://127.0.0.1:8000';

    try {
        await mainWindow.loadURL(url);
    } catch (err) {
        dialog.showErrorBox(
            'CriaSys Editor — Erro ao carregar',
            `Não foi possível abrir ${url}.\n\nVerifique se o Laravel está rodando.\n\n${err.message}`
        );
    }

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

app.whenReady().then(async () => {
    registerFilesystemIpc(projectRoot);
    registerLaravelIpc(projectRoot, getFfmpegPath());
    registerRenderIpc();

    try {
        await startLaravel(projectRoot, getFfmpegPath());
        await createWindow();
    } catch (err) {
        dialog.showErrorBox(
            'CriaSys Editor — Falha ao iniciar',
            `${err.message}\n\nCertifique-se de que PHP está no PATH (Laragon) e o .env está configurado.`
        );
        app.quit();
    }

    app.on('activate', async () => {
        if (BrowserWindow.getAllWindows().length === 0) {
            await createWindow();
        }
    });
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        stopLaravel();
        app.quit();
    }
});

app.on('before-quit', () => {
    stopLaravel();
});

process.on('uncaughtException', (err) => {
    dialog.showErrorBox('Erro inesperado', err.message);
});
