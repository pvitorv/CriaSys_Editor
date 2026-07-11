import { ipcMain } from 'electron';
import { spawn } from 'node:child_process';
import net from 'node:net';
import waitOn from 'wait-on';
import { buildLaravelEnv } from '../portable-env.js';
import { isPortableInitialized, markPortableInitialized } from '../portable.js';

let serverProcess = null;
let queueProcess = null;
let projectRootPath = '';
let runtime = {};

function isPortFree(port) {
    return new Promise((resolve) => {
        const server = net.createServer();
        server.once('error', () => resolve(false));
        server.once('listening', () => server.close(() => resolve(true)));
        server.listen(port, '127.0.0.1');
    });
}

async function findFreePort(preferred = 8765, maxAttempts = 30) {
    for (let port = preferred; port < preferred + maxAttempts; port += 1) {
        if (await isPortFree(port)) {
            return port;
        }
    }
    throw new Error('Nenhuma porta livre entre ' + preferred + ' e ' + (preferred + maxAttempts - 1));
}

export function getLaravelPort() {
    return runtime.port || 8765;
}

export function registerLaravelIpc(root, paths) {
    projectRootPath = root;
    runtime = paths;

    ipcMain.handle('criasys:restartLaravel', async () => {
        await restartLaravel();
        return { ok: true };
    });

    ipcMain.handle('criasys:getPortableInfo', () => ({
        portable: !runtime.isDev,
        dataPath: runtime.dataPath,
        laravelRoot: projectRootPath,
        platform: process.platform,
    }));
}

function getPhpBinary() {
    if (runtime.isDev) {
        return process.platform === 'win32' ? 'php' : 'php';
    }
    return runtime.phpPath;
}

function buildEnv() {
    const port = runtime.port || 8765;
    if (runtime.isDev) {
        return {
            ...process.env,
            FFMPEG_PATH: runtime.ffmpegPath,
            FFPROBE_PATH: runtime.ffprobePath,
        };
    }

    return buildLaravelEnv({
        isDev: false,
        dataPath: runtime.dataPath,
        ffmpegPath: runtime.ffmpegPath,
        ffprobePath: runtime.ffprobePath,
        port,
    });
}

function runArtisan(args, env, timeoutMs = 120000) {
    return new Promise((resolve, reject) => {
        const proc = spawn(getPhpBinary(), ['artisan', ...args], {
            cwd: projectRootPath,
            env,
            shell: process.platform === 'win32',
            stdio: ['ignore', 'pipe', 'pipe'],
        });

        let stderr = '';
        proc.stderr?.on('data', (d) => { stderr += d.toString(); });
        proc.on('error', reject);
        proc.on('close', (code) => {
            if (code === 0) resolve(true);
            else reject(new Error(stderr || `artisan ${args.join(' ')} falhou (code ${code})`));
        });

        setTimeout(() => {
            proc.kill();
            reject(new Error(`Timeout: artisan ${args.join(' ')}`));
        }, timeoutMs);
    });
}

async function initializePortable(env) {
    if (runtime.isDev || isPortableInitialized(runtime.dataPath)) {
        return;
    }

    await runArtisan(['migrate', '--force'], env);
    await runArtisan(['db:seed', '--class=AdminUserSeeder', '--force'], env);
    markPortableInitialized(runtime.dataPath);
}

export async function startLaravel(root, paths) {
    projectRootPath = root;
    runtime = paths;

    if (serverProcess) {
        return waitForServer(runtime.port);
    }

    runtime.port = runtime.isDev
        ? (runtime.port || 8000)
        : await findFreePort(runtime.port || 8765);

    const env = buildEnv();
    await initializePortable(env);

    const shell = process.platform === 'win32';
    const port = runtime.port;

    serverProcess = spawn(getPhpBinary(), ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`], {
        cwd: projectRootPath,
        env,
        shell,
        stdio: 'pipe',
    });

    queueProcess = spawn(getPhpBinary(), ['artisan', 'queue:listen', '--tries=1', '--timeout=0'], {
        cwd: projectRootPath,
        env,
        shell,
        stdio: 'pipe',
    });

    serverProcess.stderr?.on('data', (data) => console.error('[laravel:serve]', data.toString()));
    queueProcess.stderr?.on('data', (data) => console.error('[laravel:queue]', data.toString()));

    return waitForServer(port);
}

function waitForServer(port) {
    return waitOn({
        resources: [`http-get://127.0.0.1:${port}/up`],
        timeout: 90000,
        interval: 500,
    });
}

export function stopLaravel() {
    [serverProcess, queueProcess].forEach((proc) => {
        if (proc && !proc.killed) proc.kill();
    });
    serverProcess = null;
    queueProcess = null;
}

export async function restartLaravel() {
    stopLaravel();
    await startLaravel(projectRootPath, runtime);
}
