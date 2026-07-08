import { app, BrowserWindow, dialog } from 'electron';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { registerFilesystemIpc } from './ipc/filesystem.js';
import { registerLaravelIpc, startLaravel, stopLaravel } from './ipc/laravel.js';
import { registerRenderIpc } from './ipc/render.js';
import { getLaravelRoot, resolveRuntimePaths } from './portable.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const isDev = !app.isPackaged;
const resourcesPath = process.resourcesPath;
const execPath = process.execPath;
const electronDir = __dirname;

const projectRoot = getLaravelRoot(isDev, resourcesPath, app.getAppPath());
const runtimePaths = resolveRuntimePaths(isDev, electronDir, resourcesPath, execPath);

let mainWindow = null;

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

    const url = `http://127.0.0.1:${runtimePaths.port}`;

    try {
        await mainWindow.loadURL(url);
    } catch (err) {
        dialog.showErrorBox(
            'CriaSys Editor — Erro ao carregar',
            `Não foi possível abrir ${url}.\n\n${err.message}`
        );
    }

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

app.whenReady().then(async () => {
    registerFilesystemIpc(projectRoot, runtimePaths.dataPath);
    registerLaravelIpc(projectRoot, runtimePaths);
    registerRenderIpc();

    try {
        await startLaravel(projectRoot, runtimePaths);
        await createWindow();
    } catch (err) {
        dialog.showErrorBox(
            'CriaSys Editor — Falha ao iniciar',
            `${err.message}\n\nModo portátil: coloque PHP e FFmpeg em electron/php e electron/ffmpeg.\nDados gravados em: ${runtimePaths.dataPath}`
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
