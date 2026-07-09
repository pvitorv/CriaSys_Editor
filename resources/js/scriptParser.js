/**
 * Parser de roteiro para narração TTS e distribuição em slides.
 * Reconhece: parágrafos, falas, versos/refrões, marcadores de cena.
 */

const SECTION_HEADER = /^(?:\[\s*)?(REFRÃO|REFRAO|CORO|VERSO|ESTROFE|ESTRÓFE|INTRODUÇÃO|INTRO|PONTE|SÓLO|SOLO|PARTE|CENA|NARRADOR|NARRAÇÃO|NARRACAO|LOCUÇÃO|LOCUCAO|FALA|RAP|SLIDE|HOOK|OUTRO)\s*\d*\s*(?:[-–—:]\s*([^\]\n]+))?\s*(?:\])?\s*$/i;

const SCENE_MARKER = /^(?:[-–—=*_]{3,}|#{1,3}\s*(?:Slide|Cena|Parte|Ato)\s+\d+.*|(?:Slide|Cena|Parte|Ato)\s+\d+\s*[:.\-–—].*)$/i;

const STAGE_ONLY = /^(?:\([^)]{1,120}\)|\[[^\]]{1,120}\])$/;

const CHARACTER_NAME = /^[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ][A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ\s.'-]{1,35}$/;

const SPEAKER_INLINE = [
    /^(?:[-–—•*]\s*)?([A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ][A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸa-zàáâãäåæçèéêëìíîïñòóôõöùúûüýÿ\s.'-]{0,40}?)\s*:\s*(.+)$/,
    /^(?:[-–—•*]\s*)?([A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ][A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸa-zàáâãäåæçèéêëìíîïñòóôõöùúûüýÿ\s.'-]{0,40}?)\s*[-–—]\s+(.+)$/,
    /^\(([^)()]{1,40})\)\s*(.+)$/,
    /^[«""]([^»""]+)[»""]\s*(?:[-–—]\s*)?(.*)$/,
    /^(.+?),\s*(?:disse|falou|perguntou|respondeu|exclamou|murmurou|gritou|sussurrou)\s+([A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ][\w\s.'-]{1,40})\.?\s*$/i,
];

function normalizeRaw(text) {
    return String(text ?? '')
        .replace(/\r\n/g, '\n')
        .replace(/\r/g, '\n')
        .replace(/[\u201C\u201D\u201E\u00AB\u00BB]/g, '"')
        .replace(/[\u2018\u2019]/g, "'")
        .replace(/[\u2013\u2015]/g, '\u2014')
        .replace(/\u2026/g, '...')
        .replace(/\*\*(.+?)\*\*/g, '$1')
        .replace(/__(.+?)__/g, '$1')
        .replace(/\*(.+?)\*/g, '$1')
        .replace(/_(.+?)_/g, '$1')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n[ \t]+/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

/**
 * Palavras que continuam diálogo após ? — não são retomada da narração.
 */
function isDialogueContinuation(word) {
    const w = word.toLowerCase();

    return ['precisa', 'onde', 'moça', 'moca', 'como', 'quem', 'por', 'quê', 'que', 'eu', 'não', 'nao', 'sim', 'você', 'voce', 'me', 'te', 'se', 'já', 'ja', 'olá', 'ola', 'oi', 'de', 'do', 'da', 'um', 'uma'].includes(w);
}

function nextWordAt(text, pos) {
    const m = text.slice(pos).match(/^([A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ][A-Za-zÀ-ÿ]{0,30})/u);

    return m ? m[1] : null;
}

function findDialogueEnd(t, dashIdx) {
    for (let i = dashIdx + 1; i < t.length; i++) {
        if (!/[.!?]/.test(t[i])) continue;

        let next = i + 1;
        while (next < t.length && t[next] === ' ') next++;

        if (next >= t.length) return t.length;

        if (t[next] === '\u2014') {
            const inner = t.slice(next).match(/^\u2014\s*(ele|ela)\s+/i);
            if (inner) continue;
        }

        const word = nextWordAt(t, next);
        if (word && !isDialogueContinuation(word)) {
            return i + 1;
        }
    }

    return t.length;
}

/**
 * Texto colado do Word/Docs: um bloco só, falas com travessão (—) grudadas.
 */
function expandWallOfText(text) {
    if (!text) return text;

    let t = text.includes('\n') ? text : text.replace(/\s+/g, ' ').trim();
    if (!t) return text;

    t = t.replace(/([.!?,:;])([A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ])/g, '$1 $2');
    t = t.replace(/:\s*(\u2014)/g, ':\n\n$1');

    const segments = [];
    let cursor = 0;

    while (cursor < t.length) {
        const dashIdx = t.indexOf('\u2014', cursor);

        if (dashIdx === -1) {
            const prose = t.slice(cursor).trim();
            if (prose) segments.push({ type: 'prose', text: prose });
            break;
        }

        if (dashIdx > cursor) {
            const prose = t.slice(cursor, dashIdx).trim();
            if (prose) segments.push({ type: 'prose', text: prose });
        }

        const end = findDialogueEnd(t, dashIdx);
        segments.push({ type: 'dialogue', text: t.slice(dashIdx, end).trim() });
        cursor = end;
    }

    const lines = [];
    for (const seg of segments) {
        if (seg.type === 'dialogue') {
            lines.push(seg.text);
            continue;
        }

        let p = seg.text;
        p = p.replace(/([.!?])\s+((?:De repente|Subitamente|Naquele momento|Naquele instante|Por fim|Finalmente),)/gi, '$1\n\n$2');
        p = p.replace(/([.!?])\s+(Lentamente,)/gi, '$1\n\n$2');
        p = p.replace(/([.!?])\s+(O motor\b|No retrovisor\b)/gi, '$1\n\n$2');

        for (const line of p.split('\n\n')) {
            const trimmed = line.trim();
            if (trimmed) lines.push(trimmed);
        }
    }

    return lines.join('\n\n');
}

function isEmDashLine(line) {
    return /^[\u2014—]\s*.+/u.test(line.trim());
}

function formatEmDashDialogue(line) {
    let t = line.trim().replace(/^[\u2014—]\s*/, '');

    const attr = t.match(/^(.+?[.!?])\s+[\u2014—]\s+(ele|ela)\s+(.+)$/iu);
    if (attr) {
        const speech = attr[1].trim();
        const tag = `${attr[2].charAt(0).toUpperCase()}${attr[2].slice(1).toLowerCase()} ${attr[3].trim().replace(/\.$/, '')}`;

        return formatProseLine(`${tag}: ${speech}`, false);
    }

    return formatProseLine(t.replace(/\s+[\u2014—]\s+/g, ' ').trim(), false);
}

function isSceneMarker(line) {
    return SCENE_MARKER.test(line.trim());
}

function isStageDirection(line) {
    const t = line.trim();
    if (!STAGE_ONLY.test(t)) return false;

    return !SECTION_HEADER.test(t);
}

function parseSectionHeader(line) {
    const t = line.trim();
    if (isSceneMarker(t)) {
        return { label: t, title: titleFromMarker(t) };
    }
    const m = t.match(SECTION_HEADER);
    if (!m) return null;

    const label = m[1];
    const title = m[2]?.trim() || label.charAt(0).toUpperCase() + label.slice(1).toLowerCase();

    return { label, title };
}

function parseSpeakerLine(line) {
    const trimmed = line.trim();
    for (const pattern of SPEAKER_INLINE) {
        const m = trimmed.match(pattern);
        if (!m) continue;

        if (pattern.source.includes('disse|falou')) {
            return { speaker: m[2].trim(), text: m[1].trim() };
        }
        if (pattern.source.includes('[«""]')) {
            const quote = m[1].trim();
            const rest = (m[2] || '').trim();
            return rest ? parseSpeakerLine(rest) || { speaker: null, text: quote } : { speaker: null, text: quote };
        }
        if (pattern.source.startsWith('^\\(')) {
            if (!looksLikeName(m[1])) continue;
            return { speaker: m[1].trim(), text: m[2].trim() };
        }

        return { speaker: m[1].trim(), text: m[2].trim() };
    }

    return null;
}

function looksLikeName(name) {
    const n = name.trim();
    if (n.length < 2 || n.length > 40 || /^\d+$/.test(n)) return false;

    return /^(narra(?:dor|ção|cao)|voz|off|locu(?:ção|cao|tor))$/i.test(n)
        || /^[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ]/.test(n);
}

function isCharacterNameLine(line) {
    const t = line.trim();
    if (!CHARACTER_NAME.test(t)) return false;
    if (SECTION_HEADER.test(t) || isSceneMarker(t)) return false;
    if (parseSpeakerLine(t)) return false;

    return true;
}

function isVerseLine(line) {
    const t = line.trim();
    if (!t || t.length > 80) return false;
    if (parseSpeakerLine(t) || isCharacterNameLine(t)) return false;
    if (isSceneMarker(t) || parseSectionHeader(t)) return false;

    return !/[.!?;:]$/.test(t) || (t.length <= 52 && /[,;]$/.test(t));
}

function isRefrainHeader(header) {
    return header && /^(REFRÃO|REFRAO|CORO)$/i.test(header.label || header);
}

function shouldBreakProse(prevLine, nextLine) {
    const prev = prevLine?.trim();
    const next = nextLine?.trim();
    if (!prev || !next) return false;
    if (parseSectionHeader(next) || isSceneMarker(next)) return true;
    if (parseSpeakerLine(next) || isCharacterNameLine(next)) return true;
    if (/[.!?…"»]$/.test(prev) && /^[A-ZÀÁ"«([]/.test(next)) return true;

    return false;
}

function normalizeSpeakerName(name) {
    const n = String(name || '').trim().replace(/\s+/g, ' ');
    if (!n) return null;
    if (n === n.toUpperCase() && n.length > 2) {
        return n.charAt(0) + n.slice(1).toLowerCase();
    }

    return n;
}

function formatSpeaker(speaker) {
    const name = normalizeSpeakerName(speaker);
    if (!name) return null;
    if (/^(narra(?:dor|ção|cao)|voz|off|locu(?:ção|cao|tor))$/i.test(name)) return null;

    return name;
}

function formatDialogue(speaker, text) {
    const name = formatSpeaker(speaker);
    const clean = formatProseLine(text, false);
    if (!clean) return '';
    if (!name) return clean;

    const verb = /^(?:ele|ela|eles|elas)$/i.test(name) ? 'continuou' : 'disse';

    return `${name} ${verb}: ${clean}`;
}

function formatProseLine(text, forceEnd = true) {
    let t = String(text ?? '').replace(/\s+/g, ' ').trim();
    if (!t) return '';

    t = t.replace(/\s+([,.!?;:])/g, '$1');
    t = t.replace(/([,.!?;:])([^\s\d])/g, '$1 $2');
    t = t.replace(/\.{4,}/g, '...');

    if (forceEnd && !/[.!?…]$/.test(t) && t.length > 3) {
        t += '.';
    }

    return t.charAt(0).toUpperCase() + t.slice(1);
}

function formatVerseLines(lines) {
    return lines
        .map((line) => {
            let t = line.trim().replace(/\s+/g, ' ');
            if (!t) return '';
            t = t.replace(/^[-–—•*]\s*/, '');
            t = t.replace(/[,;]+$/, '');
            if (!/[.!?…]$/.test(t)) t += '.';

            return t.charAt(0).toUpperCase() + t.slice(1);
        })
        .filter(Boolean)
        .join(' ');
}

function titleFromMarker(marker) {
    const m = marker.match(/\[(?:CENA|SLIDE|PARTE|ATO|SCENE)\s*\d*\s*[-–—:]?\s*([^\]]+)\]/i);
    if (m) return truncateTitle(m[1]);
    const slide = marker.match(/(?:Slide|Cena|Parte|Ato)\s+\d+\s*[-–—:.]\s*(.+)/i);
    if (slide) return truncateTitle(slide[1]);

    return null;
}

function truncateTitle(text, max = 60) {
    const t = String(text ?? '').trim().replace(/\s+/g, ' ');
    if (!t) return null;

    return t.length <= max ? t : `${t.slice(0, max - 1).trim()}…`;
}

function makeProseBlock(lines, title, kind = 'prose') {
    const text = lines.map((l) => l.trim()).filter(Boolean).join(' ');
    if (!text) return null;

    return {
        narration_text: formatProseLine(text),
        title: title || truncateTitle(lines[0]),
        kind,
    };
}

function makeVerseBlock(lines, title, kind = 'verse') {
    if (!lines.length) return null;

    return {
        narration_text: formatVerseLines(lines),
        title: title || truncateTitle(lines[0]),
        kind,
    };
}

function parseLines(lines) {
    const blocks = [];
    let proseBuffer = [];
    let verseBuffer = [];
    let pendingSpeaker = null;
    let currentTitle = null;
    let currentKind = 'prose';

    const flushProse = () => {
        const block = makeProseBlock(proseBuffer, currentTitle, currentKind === 'refrain' ? 'refrain' : 'prose');
        proseBuffer = [];
        if (block) blocks.push(block);
    };

    const flushVerse = () => {
        const block = makeVerseBlock(verseBuffer, currentTitle, currentKind === 'refrain' ? 'refrain' : 'verse');
        verseBuffer = [];
        if (block) blocks.push(block);
    };

    const flushAll = () => {
        flushProse();
        flushVerse();
        pendingSpeaker = null;
    };

    const startSection = (header) => {
        flushAll();
        currentTitle = header.title;
        currentKind = isRefrainHeader(header) ? 'refrain' : /^(VERSO|ESTROFE|ESTRÓFE)$/i.test(header.label || '') ? 'verse' : 'prose';
    };

    for (let i = 0; i < lines.length; i++) {
        const raw = lines[i];
        const line = raw.trim();

        if (!line) {
            flushAll();
            currentTitle = null;
            currentKind = 'prose';
            continue;
        }

        if (isStageDirection(line)) {
            continue;
        }

        const section = parseSectionHeader(line);
        if (section) {
            startSection(section);
            continue;
        }

        if (pendingSpeaker) {
            const speaker = pendingSpeaker;
            pendingSpeaker = null;
            flushProse();
            flushVerse();
            blocks.push({
                narration_text: formatDialogue(speaker, line),
                title: formatSpeaker(speaker) || currentTitle,
                kind: 'dialogue',
            });
            continue;
        }

        if (isCharacterNameLine(line)) {
            flushAll();
            pendingSpeaker = line.trim();
            continue;
        }

        if (isEmDashLine(line)) {
            flushProse();
            flushVerse();
            blocks.push({
                narration_text: formatEmDashDialogue(line),
                title: 'Fala',
                kind: 'dialogue',
            });
            continue;
        }

        const speaker = parseSpeakerLine(line);
        if (speaker) {
            flushAll();
            blocks.push({
                narration_text: formatDialogue(speaker.speaker, speaker.text),
                title: formatSpeaker(speaker.speaker) || currentTitle,
                kind: 'dialogue',
            });
            continue;
        }

        const prevLine = i > 0 ? lines[i - 1] : '';
        if (proseBuffer.length && shouldBreakProse(proseBuffer[proseBuffer.length - 1], line)) {
            flushProse();
        }

        if (currentKind === 'refrain' || currentKind === 'verse' || isVerseLine(line)) {
            if (proseBuffer.length) flushProse();
            verseBuffer.push(line);
            continue;
        }

        if (verseBuffer.length) {
            if (isVerseLine(line)) {
                verseBuffer.push(line);
                continue;
            }
            flushVerse();
        }

        proseBuffer.push(line);

        if (proseBuffer.length > 1 && shouldBreakProse(prevLine, line)) {
            const last = proseBuffer.pop();
            flushProse();
            proseBuffer = [last];
        }
    }

    flushAll();

    return blocks;
}

export function formatNarrationText(text) {
    let normalized = normalizeRaw(text);
    if (!normalized) return '';

    normalized = expandWallOfText(normalized);

    const blocks = parseLines(normalized.split('\n'));
    if (!blocks.length) {
        return formatProseLine(normalized.replace(/\n+/g, ' '));
    }

    return blocks.map((b) => b.narration_text).join(' ');
}

export function parseScript(rawText) {
    let normalized = normalizeRaw(rawText);
    if (!normalized) {
        return {
            blocks: [],
            formattedScript: '',
            stats: { slides: 0, dialogues: 0, verses: 0, prose: 0, refrains: 0 },
        };
    }

    normalized = expandWallOfText(normalized);

    const blocks = parseLines(normalized.split('\n')).map((block, index) => ({
        narration_text: block.narration_text,
        title: block.title || `Slide ${index + 1}`,
        kind: block.kind,
    }));

    const stats = {
        slides: blocks.length,
        dialogues: blocks.filter((b) => b.kind === 'dialogue').length,
        verses: blocks.filter((b) => b.kind === 'verse').length,
        refrains: blocks.filter((b) => b.kind === 'refrain').length,
        prose: blocks.filter((b) => b.kind === 'prose').length,
    };

    return {
        blocks,
        formattedScript: blocks.map((b) => b.narration_text).join('\n\n'),
        stats,
    };
}
