import { app, BrowserWindow, dialog } from 'electron';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { registerFilesystemIpc } from './ipc/filesystem.js';
import { registerLaravelIpc, startLaravel, stopLaravel, getLaravelPort } from './ipc/laravel.js';
import { registerRenderIpc } from './ipc/render.js';
import { getLaravelRoot, resolveRuntimePaths, getPortableBaseDir } from './portable.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

let mainWindow = null;
let runtimePaths = null;
let projectRoot = null;

function writeStartupLog(message) {
    try {
        const base = getPortableBaseDir() || path.dirname(process.execPath);
        const logDir = path.join(base, 'CriaSysData');
        fs.mkdirSync(logDir, { recursive: true });
        fs.appendFileSync(
            path.join(logDir, 'startup.log'),
            `[${new Date().toISOString()}] ${message}\n`,
            'utf8',
        );
    } catch (_) { /* ignore */ }
}

function resolveAppPaths() {
    const isDev = !app.isPackaged;
    const resourcesPath = process.resourcesPath;
    const execPath = process.execPath;
    const electronDir = __dirname;

    projectRoot = getLaravelRoot(isDev, resourcesPath, app.getAppPath());
    runtimePaths = resolveRuntimePaths(isDev, electronDir, resourcesPath, execPath);

    writeStartupLog(
        `isDev=${isDev} execPath=${execPath} resourcesPath=${resourcesPath} `
        + `dataPath=${runtimePaths.dataPath} phpPath=${runtimePaths.phpPath} `
        + `PORTABLE_EXECUTABLE_DIR=${process.env.PORTABLE_EXECUTABLE_DIR || ''} `
        + `PORTABLE_EXECUTABLE_FILE=${process.env.PORTABLE_EXECUTABLE_FILE || ''}`,
    );
}

async function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1400,
        height: 900,
        minWidth: 1024,
        minHeight: 700,
        title: 'CriaSys Editor',
        backgroundColor: '#09090b',
        show: false,
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
        },
    });

    mainWindow.once('ready-to-show', () => {
        mainWindow?.show();
    });

    const url = `http://127.0.0.1:${runtimePaths.port}`;

    try {
        await mainWindow.loadURL(url);
    } catch (err) {
        dialog.showErrorBox(
            'CriaSys Editor — Erro ao carregar',
            `Não foi possível abrir ${url}.\n\n${err.message}`,
        );
    }

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

app.whenReady().then(async () => {
    resolveAppPaths();

    registerFilesystemIpc(projectRoot, runtimePaths.dataPath);
    registerLaravelIpc(projectRoot, runtimePaths);
    registerRenderIpc();

    try {
        await startLaravel(projectRoot, runtimePaths);
        runtimePaths.port = getLaravelPort();
        writeStartupLog(`laravel ok port=${runtimePaths.port}`);
        await createWindow();
    } catch (err) {
        writeStartupLog(`ERRO: ${err?.stack || err?.message || err}`);
        try {
            const logDir = runtimePaths?.dataPath || path.join(getPortableBaseDir() || path.dirname(process.execPath), 'CriaSysData');
            fs.mkdirSync(logDir, { recursive: true });
            fs.writeFileSync(
                path.join(logDir, 'startup-error.txt'),
                `${new Date().toISOString()}\n\n${err?.stack || err?.message || err}`,
                'utf8',
            );
        } catch (_) { /* ignore */ }
        dialog.showErrorBox(
            'CriaSys Editor — Falha ao iniciar',
            `${err.message}\n\nDados: ${runtimePaths?.dataPath || '—'}\nLog: CriaSysData\\startup-error.txt`,
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
    writeStartupLog(`uncaught: ${err?.stack || err?.message || err}`);
    dialog.showErrorBox('Erro inesperado', err.message);
});
