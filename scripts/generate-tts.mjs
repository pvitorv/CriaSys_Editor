import { readFileSync, writeFileSync } from 'node:fs';
import { UniversalEdgeTTS } from 'edge-tts-universal';

const args = process.argv.slice(2);
const getArg = (name) => {
    const i = args.indexOf(name);
    return i >= 0 ? args[i + 1] : null;
};

async function main() {
    const voice = getArg('--voice') || 'pt-BR-FranciscaNeural';
    const input = getArg('--input');
    const output = getArg('--output');

    if (!input || !output) {
        console.error('Uso: node generate-tts.mjs --voice VOICE --input FILE --output FILE');
        process.exit(1);
    }

    const text = readFileSync(input, 'utf8').trim();
    if (!text) {
        console.error('Texto vazio');
        process.exit(1);
    }

    const tts = new UniversalEdgeTTS(text, voice);
    const result = await tts.synthesize();
    const audio = result?.audio;

    if (!audio) {
        throw new Error('Nenhum áudio retornado pelo Edge TTS');
    }

    if (typeof audio.arrayBuffer === 'function') {
        const buffer = Buffer.from(await audio.arrayBuffer());
        writeFileSync(output, buffer);
    } else if (audio instanceof Uint8Array) {
        writeFileSync(output, Buffer.from(audio));
    } else {
        writeFileSync(output, Buffer.from(audio));
    }
}

main().catch((err) => {
    console.error(err?.message || String(err));
    process.exit(1);
});
