#!/usr/bin/env node
/**
 * Verifica estrutura para build portátil multi-plataforma.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const phpIniScript = path.join(root, 'scripts', 'configure-portable-php.mjs');
if (fs.existsSync(phpIniScript)) {
    await import(pathToFileURL(phpIniScript).href);
}
const platforms = ['win', 'linux', 'mac'];

console.log('CriaSys Editor — verificação de runtimes\n');

let ok = true;

for (const p of platforms) {
    const phpDir = path.join(root, 'electron', 'php', p);
    const ffDir = path.join(root, 'electron', 'ffmpeg', p);
    const phpBin = p === 'win' ? 'php.exe' : 'php';
    const ffBin = p === 'win' ? 'ffmpeg.exe' : 'ffmpeg';

    const hasPhp = fs.existsSync(path.join(phpDir, phpBin));
    const hasFf = fs.existsSync(path.join(ffDir, ffBin));

    console.log(`[${p}] PHP: ${hasPhp ? 'OK' : 'FALTANDO'} | FFmpeg: ${hasFf ? 'OK' : 'FALTANDO'}`);

    if (p === 'win' && hasPhp) {
        const mod = spawnSync(path.join(phpDir, phpBin), ['-m'], { encoding: 'utf8' });
        const out = mod.stdout || '';
        if (!out.includes('pdo_sqlite') || !out.includes('sqlite3')) {
            console.log(`[${p}] SQLite: FALTANDO — rode npm run portable:prepare de novo`);
            ok = false;
        } else {
            console.log(`[${p}] SQLite: OK`);
        }
    }

    if (p === 'win' && (!hasPhp || !hasFf)) {
        ok = false;
    }
}

if (!fs.existsSync(path.join(root, 'vendor'))) {
    console.log('\n⚠  Execute: composer install --no-dev');
    ok = false;
}

if (!fs.existsSync(path.join(root, 'public', 'build'))) {
    console.log('⚠  Execute: npm run build');
    ok = false;
}

console.log(ok ? '\n✓ Pronto para build Windows' : '\n✗ Complete os runtimes antes do build (ver electron/php e electron/ffmpeg README)');
