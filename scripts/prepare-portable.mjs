#!/usr/bin/env node
/**
 * Verifica estrutura para build portátil multi-plataforma.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
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
