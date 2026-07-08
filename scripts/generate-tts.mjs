import { readFileSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

const args = process.argv.slice(2);
const getArg = (name) => {
    const i = args.indexOf(name);
    return i >= 0 ? args[i + 1] : null;
};

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

const py = spawnSync('python', ['-m', 'edge_tts', '--voice', voice, '--text', text, '--write-media', output], {
    encoding: 'utf8',
    shell: process.platform === 'win32',
});

if (py.status === 0) {
    process.exit(0);
}

try {
    const { MsEdgeTTS, OUTPUT_FORMAT } = await import('edge-tts-universal');
    const tts = new MsEdgeTTS();
    await tts.setMetadata(voice, OUTPUT_FORMAT.AUDIO_24KHZ_96KBITRATE_MONO_MP3);
    const readable = tts.toStream(text);
    const chunks = [];
    for await (const chunk of readable) {
        chunks.push(chunk);
    }
    writeFileSync(output, Buffer.concat(chunks));
    process.exit(0);
} catch (err) {
    console.error(py.stderr || py.stdout || err.message);
    process.exit(py.status || 1);
}
