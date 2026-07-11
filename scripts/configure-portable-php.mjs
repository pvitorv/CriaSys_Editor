#!/usr/bin/env node
/**
 * Ajusta php.ini copiado do Laragon para funcionar embutido no Electron (caminhos relativos + SQLite).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const phpDir = path.join(root, 'electron', 'php', 'win');
const iniPath = path.join(phpDir, 'php.ini');

if (!fs.existsSync(iniPath)) {
    console.warn('configure-portable-php: php.ini não encontrado em electron/php/win/');
    process.exit(0);
}

let lines = fs.readFileSync(iniPath, 'utf8').split(/\r?\n/);

lines = lines.map((line) => {
    const trimmed = line.trimStart();
    if (trimmed.startsWith(';')) {
        return line;
    }
    if (/^extension_dir\s*=/.test(trimmed)) {
        return 'extension_dir = "ext"';
    }
    if (/^error_log\s*=/.test(trimmed)) {
        return 'error_log = ""';
    }
    if (/^include_path\s*=/.test(trimmed)) {
        return 'include_path = "."';
    }
    if (/^session\.save_path\s*=/.test(trimmed)) {
        return 'session.save_path = ""';
    }
    if (/^sendmail_path\s*=/.test(trimmed)) {
        return ';sendmail_path=""';
    }
    if (/^curl\.cainfo\s*=/.test(trimmed)) {
        return 'curl.cainfo = "cacert.pem"';
    }
    if (/^zend_extension\s*=/.test(trimmed)) {
        return ';zend_extension=opcache';
    }
    if (/^;extension=pdo_sqlite/.test(trimmed)) {
        return 'extension=pdo_sqlite';
    }
    if (/^;extension=sqlite3/.test(trimmed)) {
        return 'extension=sqlite3';
    }
    return line;
});

fs.writeFileSync(iniPath, lines.join('\n'));

const cacertSrc = path.join('C:', 'laragon', 'etc', 'ssl', 'cacert.pem');
const cacertDst = path.join(phpDir, 'cacert.pem');
if (fs.existsSync(cacertSrc) && !fs.existsSync(cacertDst)) {
    fs.copyFileSync(cacertSrc, cacertDst);
}

console.log('✓ php.ini ajustado para modo portátil (ext relativo + SQLite)');
