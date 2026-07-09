'use strict';

const fs = require('fs');
const path = require('path');

function getArg(args, name) {
    const i = args.indexOf(name);
    return i >= 0 ? args[i + 1] : null;
}

function dbg(msg) {
    if (!process.env.TTS_DEBUG_LOG) {
        return;
    }
    try {
        fs.appendFileSync(process.env.TTS_DEBUG_LOG, '[' + new Date().toISOString() + '] ' + msg + '\n');
    } catch (e) {
        // ignore
    }
}

async function run() {
    const args = process.argv.slice(2);
    const voice = getArg(args, '--voice') || 'pt-BR-FranciscaNeural';
    const input = getArg(args, '--input');
    const output = getArg(args, '--output');

    if (!input || !output) {
        throw new Error('Uso: node generate-tts.cjs --voice VOICE --input FILE --output FILE');
    }

    dbg('start pid=' + process.pid);
    const text = fs.readFileSync(input, 'utf8').trim();
    if (!text) {
        throw new Error('Texto vazio');
    }

    dbg('before import');
    const { UniversalEdgeTTS } = await import('edge-tts-universal');
    dbg('after import, before synthesize');
    const tts = new UniversalEdgeTTS(text, voice);

    const result = await withTimeout(tts.synthesize(), 45000, 'Timeout ao conectar no Edge TTS (45s)');
    dbg('after synthesize');
    const audio = result && result.audio;

    if (!audio) {
        throw new Error('Nenhum áudio retornado pelo Edge TTS');
    }

    let buffer;
    if (typeof audio.arrayBuffer === 'function') {
        buffer = Buffer.from(await audio.arrayBuffer());
    } else if (audio instanceof Uint8Array) {
        buffer = Buffer.from(audio);
    } else {
        buffer = Buffer.from(audio);
    }

    if (!buffer.length) {
        throw new Error('Arquivo de áudio vazio');
    }

    const target = path.resolve(output);
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.writeFileSync(target, buffer);

    process.stdout.write('OK ' + buffer.length + '\n');
}

function withTimeout(promise, ms, message) {
    let timer;
    const timeout = new Promise((_, reject) => {
        timer = setTimeout(() => reject(new Error(message)), ms);
    });

    return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
}

run()
    .then(() => process.exit(0))
    .catch((err) => {
        process.stderr.write((err && err.message ? err.message : String(err)) + '\n');
        process.exit(1);
    });
