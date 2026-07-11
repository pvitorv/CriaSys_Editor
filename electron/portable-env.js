import path from 'node:path';
import {
    ensurePortableStructure,
    getPortableDataPath,
    loadOrCreateAppKey,
    loadPortableSecrets,
} from './portable.js';

export function buildLaravelEnv({ isDev, dataPath, ffmpegPath, ffprobePath, port = 8000 }) {
    const dbFile = path.join(dataPath, 'database', 'criasys.sqlite').replace(/\\/g, '/');
    const storagePath = path.join(dataPath, 'storage').replace(/\\/g, '/');
    const projectsPath = path.join(dataPath, 'storage', 'app', 'criasys', 'projetos').replace(/\\/g, '/');
    const exportsPath = path.join(dataPath, 'storage', 'app', 'criasys', 'exports').replace(/\\/g, '/');
    const secrets = loadPortableSecrets(dataPath);

    ensurePortableStructure(dataPath);

    return {
        ...process.env,
        CRIASYS_PORTABLE: 'true',
        CRIASYS_DEPLOYMENT: 'desktop',
        CRIASYS_DATA_PATH: dataPath,
        APP_NAME: 'CriaSys_Editor',
        APP_ENV: 'production',
        APP_DEBUG: 'false',
        APP_URL: `http://127.0.0.1:${port}`,
        APP_KEY: loadOrCreateAppKey(dataPath),
        LOG_CHANNEL: 'stack',
        LOG_LEVEL: 'warning',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: dbFile,
        SESSION_DRIVER: 'database',
        QUEUE_CONNECTION: 'database',
        CACHE_STORE: 'database',
        FILESYSTEM_DISK: 'local',
        FFMPEG_PATH: ffmpegPath,
        FFPROBE_PATH: ffprobePath,
        CRIASYS_PROJECTS_PATH: projectsPath,
        CRIASYS_EXPORTS_PATH: exportsPath,
        LARAVEL_STORAGE_PATH: storagePath,
        ADMIN_USERNAME: secrets.admin_username,
        ADMIN_EMAIL: secrets.admin_email,
        ADMIN_PASSWORD: secrets.admin_password,
        HASH_DRIVER: 'argon2id',
        MAIL_MAILER: 'log',
    };
}
