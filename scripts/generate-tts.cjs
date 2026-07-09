'use strict';

const fs = require('fs');
const path = require('path');

function getArg(args, name) {
    const i = args.indexOf(name);
    return i >= 0 ? args[i + 1] : null;
}

async function run() {
    const args = process.argv.slice(2);
    const voice = getArg(args, '--voice') || 'pt-BR-FranciscaNeural';
    const input = getArg(args, '--input');
    const output = getArg(args, '--output');

    if (!input || !output) {
        throw new Error('Uso: node generate-tts.cjs --voice VOICE --input FILE --output FILE');
    }

    const text = fs.readFileSync(input, 'utf8').trim();
    if (!text) {
        throw new Error('Texto vazio');
    }

    const { UniversalEdgeTTS } = await import('edge-tts-universal');
    const tts = new UniversalEdgeTTS(text, voice);
    const result = await tts.synthesize();
    const audio = result?.audio;

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
}

run()
    .then(() => process.exit(0))
    .catch((err) => {
        console.error(err?.message || String(err));
        process.exit(1);
    });
