import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export function getPortableDataPath(isDev, execPath) {
    if (isDev) {
        return path.resolve(__dirname, '..', 'portable-data');
    }

    return path.join(path.dirname(execPath), 'CriaSysData');
}

export function getLaravelRoot(isDev, resourcesPath, appPath) {
    if (isDev) {
        return path.resolve(__dirname, '..');
    }

    const external = path.join(resourcesPath, 'laravel');
    if (fs.existsSync(path.join(external, 'artisan'))) {
        return external;
    }

    return appPath;
}

export function getPlatformKey() {
    if (process.platform === 'win32') return 'win';
    if (process.platform === 'darwin') return 'mac';
    return 'linux';
}

export function getBundledBinary(resourcesPath, electronDir, isDev, subfolder, binaryName) {
    const platform = getPlatformKey();
    const candidate = isDev
        ? path.join(electronDir, subfolder, platform, binaryName)
        : path.join(resourcesPath, subfolder, platform, binaryName);

    if (fs.existsSync(candidate)) {
        return candidate;
    }

    return binaryName;
}

export function resolveRuntimePaths(isDev, electronDir, resourcesPath, execPath) {
    const dataPath = getPortableDataPath(isDev, execPath);
    const win = process.platform === 'win32';

    return {
        dataPath,
        ffmpegPath: getBundledBinary(resourcesPath, electronDir, isDev, 'ffmpeg', win ? 'ffmpeg.exe' : 'ffmpeg'),
        ffprobePath: getBundledBinary(resourcesPath, electronDir, isDev, 'ffmpeg', win ? 'ffprobe.exe' : 'ffprobe'),
        phpPath: getBundledBinary(resourcesPath, electronDir, isDev, 'php', win ? 'php.exe' : 'php'),
        port: 8000,
        isDev,
    };
}

export function ensurePortableStructure(dataPath) {
    const dirs = [
        dataPath,
        path.join(dataPath, 'storage', 'app', 'criasys', 'projetos'),
        path.join(dataPath, 'storage', 'app', 'criasys', 'exports'),
        path.join(dataPath, 'storage', 'framework', 'cache', 'data'),
        path.join(dataPath, 'storage', 'framework', 'sessions'),
        path.join(dataPath, 'storage', 'framework', 'views'),
        path.join(dataPath, 'storage', 'logs'),
        path.join(dataPath, 'database'),
    ];

    for (const dir of dirs) {
        fs.mkdirSync(dir, { recursive: true });
    }
}

export function loadOrCreateAppKey(dataPath) {
    const keyFile = path.join(dataPath, 'app.key');
    if (fs.existsSync(keyFile)) {
        return fs.readFileSync(keyFile, 'utf8').trim();
    }

    const key = `base64:${crypto.randomBytes(32).toString('base64')}`;
    fs.writeFileSync(keyFile, key, 'utf8');
    return key;
}

export function isPortableInitialized(dataPath) {
    return fs.existsSync(path.join(dataPath, '.initialized'));
}

export function markPortableInitialized(dataPath) {
    fs.writeFileSync(path.join(dataPath, '.initialized'), new Date().toISOString(), 'utf8');
}

export function loadPortableSecrets(dataPath) {
    const secretsFile = path.join(dataPath, 'secrets.json');
    if (fs.existsSync(secretsFile)) {
        return JSON.parse(fs.readFileSync(secretsFile, 'utf8'));
    }

    const secrets = {
        admin_username: 'UserDev',
        admin_email: 'pontodeimpacto790@gmail.com',
        admin_password: 'Aa123456',
    };

    fs.writeFileSync(secretsFile, JSON.stringify(secrets, null, 2), 'utf8');
    return secrets;
}
