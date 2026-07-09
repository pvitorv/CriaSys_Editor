'use strict';

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

function getArg(args, name) {
    const i = args.indexOf(name);
    return i >= 0 ? args[i + 1] : null;
}

function dbg(msg) {
    if (!process.env.TTS_DEBUG_LOG) return;
    try {
        fs.appendFileSync(process.env.TTS_DEBUG_LOG, '[' + new Date().toISOString() + '] ' + msg + '\n');
    } catch (e) {
        // ignore
    }
}

/** Quebra texto respeitando limite de bytes da API Microsoft (~4096). */
function splitText(text, maxBytes = 3200) {
    const trimmed = text.trim();
    if (!trimmed) return [];

    const encoder = new TextEncoder();
    if (encoder.encode(trimmed).length <= maxBytes) {
        return [trimmed];
    }

    const parts = [];
    let buffer = '';

    const flush = () => {
        const chunk = buffer.trim();
        if (chunk) parts.push(chunk);
        buffer = '';
    };

    for (const sentence of trimmed.split(/(?<=[.!?…])\s+/u)) {
        const candidate = buffer ? `${buffer} ${sentence}` : sentence;
        if (encoder.encode(candidate).length <= maxBytes) {
            buffer = candidate;
            continue;
        }

        if (buffer) flush();

        if (encoder.encode(sentence).length <= maxBytes) {
            buffer = sentence;
            continue;
        }

        let wordBuf = '';
        for (const word of sentence.split(/\s+/u)) {
            const next = wordBuf ? `${wordBuf} ${word}` : word;
            if (encoder.encode(next).length <= maxBytes) {
                wordBuf = next;
            } else {
                if (wordBuf) parts.push(wordBuf);
                wordBuf = word;
            }
        }
        if (wordBuf) buffer = wordBuf;
    }

    flush();

    return parts.length ? parts : [trimmed.slice(0, 800)];
}

function friendlyError(raw) {
    const msg = String(raw || '');
    if (/output has been disabled/i.test(msg)) {
        return 'Microsoft bloqueou o Edge TTS (“Output has been disabled”). Use OpenAI TTS (barato, em Integrações) ou instale Piper (grátis local — veja docs/DESENVOLVIMENTO.md).';
    }
    if (/403|401|invalid response/i.test(msg)) {
        return 'Edge TTS recusado pela Microsoft (403). Tente de novo em alguns minutos ou mude o motor em Narração.';
    }
    if (/timeout/i.test(msg)) {
        return 'Edge TTS demorou demais. Verifique internet e data/hora do Windows, ou use OpenAI/Piper.';
    }
    return msg;
}

async function synthesizeChunk(text, voice) {
    const { Communicate } = await import('edge-tts-universal');
    const communicate = new Communicate(text, { voice });
    const buffers = [];

    for await (const chunk of communicate.stream()) {
        if (chunk.type === 'audio' && chunk.data) {
            buffers.push(Buffer.from(chunk.data));
        }
    }

    if (!buffers.length) {
        throw new Error('Output has been disabled.');
    }

    return Buffer.concat(buffers);
}

async function synthesizeWithRetry(text, voice, attempts = 3) {
    let lastError = null;

    for (let i = 0; i < attempts; i++) {
        try {
            dbg(`synthesize attempt ${i + 1}`);
            return await withTimeout(synthesizeChunk(text, voice), 60000, 'Timeout ao conectar no Edge TTS (60s)');
        } catch (err) {
            lastError = err;
            dbg(`attempt failed: ${err?.message || err}`);
            if (i < attempts - 1) {
                await sleep(1500 * (i + 1));
            }
        }
    }

    throw lastError;
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function withTimeout(promise, ms, message) {
    let timer;
    const timeout = new Promise((_, reject) => {
        timer = setTimeout(() => reject(new Error(message)), ms);
    });
    return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
}

function concatMp3(files, output) {
    if (files.length === 1) {
        fs.copyFileSync(files[0], output);
        return;
    }

    const listPath = path.join(path.dirname(output), `concat_${Date.now()}.txt`);
    const lines = files.map((f) => `file '${f.replace(/'/g, "'\\''")}'`);
    fs.writeFileSync(listPath, lines.join('\n'));

    const ffmpeg = process.env.FFMPEG_PATH || 'ffmpeg';
    const result = spawnSync(
        ffmpeg,
        ['-y', '-f', 'concat', '-safe', '0', '-i', listPath, '-c', 'copy', output],
        { encoding: 'utf8', windowsHide: true }
    );

    fs.unlinkSync(listPath);

    if (result.status !== 0 || !fs.existsSync(output)) {
        throw new Error(result.stderr || 'Falha ao juntar trechos de áudio');
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

    const chunks = splitText(text);
    dbg(`chunks=${chunks.length}`);

    const target = path.resolve(output);
    fs.mkdirSync(path.dirname(target), { recursive: true });

    const tempDir = path.dirname(target);
    const partFiles = [];

    for (let i = 0; i < chunks.length; i++) {
        const partPath = path.join(tempDir, `tts_part_${Date.now()}_${i}.mp3`);
        const buffer = await synthesizeWithRetry(chunks[i], voice);
        fs.writeFileSync(partPath, buffer);
        partFiles.push(partPath);
    }

    concatMp3(partFiles, target);
    partFiles.forEach((f) => {
        try {
            fs.unlinkSync(f);
        } catch (e) {
            // ignore
        }
    });

    const size = fs.statSync(target).size;
    process.stdout.write('OK ' + size + '\n');
}

run()
    .then(() => process.exit(0))
    .catch((err) => {
        process.stderr.write(friendlyError(err && err.message ? err.message : String(err)) + '\n');
        process.exit(1);
    });
