import { ipcMain } from 'electron';
import { spawn } from 'node:child_process';
import waitOn from 'wait-on';

let serverProcess = null;
let queueProcess = null;
let projectRootPath = '';
let ffmpegPath = '';

export function registerLaravelIpc(root, ffmpeg) {
    projectRootPath = root;
    ffmpegPath = ffmpeg;

    ipcMain.handle('criasys:restartLaravel', async () => {
        await restartLaravel();
        return { ok: true };
    });
}

export function startLaravel(root, ffmpeg) {
    projectRootPath = root;
    ffmpegPath = ffmpeg;

    if (serverProcess) {
        return waitForServer();
    }

    const env = {
        ...process.env,
        FFMPEG_PATH: ffmpeg,
        FFPROBE_PATH: ffmpeg.replace(/ffmpeg/i, 'ffprobe'),
    };

    const shell = process.platform === 'win32';

    serverProcess = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000'], {
        cwd: projectRootPath,
        env,
        shell,
        stdio: 'pipe',
    });

    queueProcess = spawn('php', ['artisan', 'queue:listen', '--tries=1', '--timeout=0'], {
        cwd: projectRootPath,
        env,
        shell,
        stdio: 'pipe',
    });

    serverProcess.stderr?.on('data', (data) => {
        console.error('[laravel:serve]', data.toString());
    });

    queueProcess.stderr?.on('data', (data) => {
        console.error('[laravel:queue]', data.toString());
    });

    return waitForServer();
}

function waitForServer() {
    return waitOn({
        resources: ['http-get://127.0.0.1:8000/up'],
        timeout: 60000,
        interval: 500,
    });
}

export function stopLaravel() {
    [serverProcess, queueProcess].forEach((proc) => {
        if (proc && !proc.killed) {
            proc.kill();
        }
    });
    serverProcess = null;
    queueProcess = null;
}

export async function restartLaravel() {
    stopLaravel();
    await startLaravel(projectRootPath, ffmpegPath);
}
