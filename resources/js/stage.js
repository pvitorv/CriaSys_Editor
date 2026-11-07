import { PreviewAudioMixer } from './previewAudio';

const body = document.body;
const projectId = body.dataset.project;
const aspect = body.dataset.aspect === '9:16' ? '9:16' : '16:9';
const showCc = body.dataset.cc === '1';
const designWidth = aspect === '9:16' ? 1080 : 1920;

const frame = document.getElementById('frame');
const img = frame.querySelector('img');
const video = frame.querySelector('video');
const text = document.getElementById('text');
const heading = text.querySelector('h1');
const subline = text.querySelector('p');
const clockEl = document.getElementById('clock');
const bar = document.querySelector('#bar i');

const mixer = new PreviewAudioMixer();
let slides = [];
let total = 0;
let narrationUrl = null;
let musicTracks = [];
let soundEffects = [];
let playing = true;
let origin = 0;
let elapsedBase = 0;
let shownIndex = -1;
let audioUnlocked = false;
let hasAudio = false;
let uiTimer = 0;

function fileUrl(type, filePath) {
    const name = String(filePath).split(/[/\\]/).pop();
    return `/api/projects/${projectId}/files/${type}/${encodeURIComponent(name)}`;
}

async function getJson(url) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (response.status === 401) {
        window.location.href = '/login';
        throw new Error('auth');
    }
    if (!response.ok) {
        throw new Error(String(response.status));
    }
    return response.json();
}

function durationOf(slide) {
    const value = parseFloat(slide?.duration_seconds);
    return Number.isFinite(value) && value > 0 ? value : 5;
}

function locate(sec) {
    let acc = 0;
    const clamped = Math.max(0, Math.min(total, sec));
    for (let i = 0; i < slides.length; i++) {
        const span = durationOf(slides[i]);
        if (clamped < acc + span || i === slides.length - 1) {
            return { index: i, offset: Math.max(0, clamped - acc) };
        }
        acc += span;
    }
    return { index: 0, offset: 0 };
}

function slideStart(index) {
    let sec = 0;
    for (let i = 0; i < index; i++) sec += durationOf(slides[i]);
    return sec;
}

function nowSec() {
    if (!playing) return elapsedBase;
    return Math.min(total, elapsedBase + (performance.now() - origin) / 1000);
}

function clock(sec) {
    const whole = Math.max(0, Math.floor(sec));
    const m = Math.floor(whole / 60);
    const s = whole % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

function fit() {
    const [aw, ah] = aspect.split(':').map(Number);
    const scale = Math.min(window.innerWidth / aw, window.innerHeight / ah);
    frame.style.width = `${Math.max(1, Math.floor(aw * scale))}px`;
    frame.style.height = `${Math.max(1, Math.floor(ah * scale))}px`;
}

function visibleCopy(slide) {
    const bodyText = (slide.body_text || '').trim();
    const subtitle = (slide.subtitle || '').trim();
    const narration = (slide.narration_text || '').trim();
    const hasMedia = !!(slide.image_url || slide.video_url);
    if (hasMedia && !showCc) return { body: '', sub: '' };
    if (hasMedia) return { body: bodyText || narration, sub: '' };
    return { body: bodyText || narration, sub: subtitle };
}

function show(index, offset) {
    const slide = slides[index];
    if (!slide) return;
    const copy = visibleCopy(slide);
    const mode = slide.video_url ? 'mode-video' : (slide.image_url ? 'mode-image' : 'mode-text');
    frame.className = mode;

    if (slide.image_url && img.getAttribute('src') !== slide.image_url) {
        img.src = slide.image_url;
    }
    if (slide.video_url) {
        if (video.getAttribute('src') !== slide.video_url) {
            video.src = slide.video_url;
        }
        const seek = () => {
            const limit = Math.max(0, durationOf(slide) - 0.05);
            video.currentTime = Math.min(offset, limit);
            if (playing) video.play().catch(() => {});
        };
        if (video.readyState >= 1) seek();
        else video.addEventListener('loadedmetadata', seek, { once: true });
    } else if (!video.paused) {
        video.pause();
    }

    heading.textContent = copy.body;
    subline.textContent = copy.sub;
    text.hidden = !copy.body && !copy.sub;
    text.classList.toggle('cc', !!(slide.image_url || slide.video_url));
    const align = slide.text_style?.align || 'center';
    const vertical = slide.text_style?.vertical_align || 'center';
    text.style.alignItems = align === 'left' ? 'flex-start' : (align === 'right' ? 'flex-end' : 'center');
    text.style.textAlign = align;
    text.style.justifyContent = text.classList.contains('cc')
        ? 'flex-end'
        : (vertical === 'top' ? 'flex-start' : (vertical === 'bottom' ? 'flex-end' : 'center'));
    const size = parseInt(slide.text_style?.body_size, 10);
    const hasMedia = !!(slide.image_url || slide.video_url);
    const px = hasMedia
        ? 32
        : (Number.isFinite(size) && size > 12 ? size : (copy.body.length > 80 ? 28 : 64));
    heading.style.fontSize = `${Math.max(18, (frame.clientWidth / designWidth) * px)}px`;
    heading.style.color = slide.text_style?.body_color || '#ffffff';
    subline.style.fontSize = `${Math.max(12, (frame.clientWidth / designWidth) * 28)}px`;
}

function paint() {
    const sec = nowSec();
    const { index, offset } = locate(sec);
    if (index !== shownIndex) {
        shownIndex = index;
        show(index, offset);
    }
    clockEl.textContent = `${clock(sec)} / ${clock(total)}${playing ? '' : '  ·  pausa'}`;
    bar.style.width = `${total > 0 ? Math.min(100, (sec / total) * 100) : 0}%`;
    if (playing && sec >= total - 0.02) {
        pause();
    }
    if (playing) requestAnimationFrame(paint);
}

function startMix() {
    if (!hasAudio) return;
    try {
        mixer.stop();
        mixer.beginFromUserGesture();
        mixer.play({
            narrationUrl,
            musicTracks,
            soundEffects,
            totalDuration: total,
            startOffsetSec: nowSec(),
            mixVolumes: { narration: 1, music: 1, sfx: 1 },
        });
    } catch (_) {
        // O navegador só libera o som depois de um clique nesta aba.
    }
}

function play() {
    if (nowSec() >= total - 0.05) {
        elapsedBase = 0;
        shownIndex = -1;
    }
    playing = true;
    origin = performance.now();
    body.classList.remove('paused');
    startMix();
    requestAnimationFrame(paint);
}

function pause() {
    elapsedBase = nowSec();
    playing = false;
    body.classList.add('paused');
    mixer.stop();
    if (!video.paused) video.pause();
    paint();
}

function toggle() {
    if (playing) pause();
    else play();
}

function jump(delta) {
    const { index } = locate(nowSec());
    const next = Math.max(0, Math.min(slides.length - 1, index + delta));
    elapsedBase = slideStart(next);
    shownIndex = -1;
    if (playing) {
        origin = performance.now();
        startMix();
    } else {
        paint();
    }
}

function poke() {
    body.classList.add('show-ui');
    clearTimeout(uiTimer);
    uiTimer = setTimeout(() => body.classList.remove('show-ui'), 1600);
}

function onGesture(event) {
    if (!audioUnlocked && hasAudio) {
        audioUnlocked = true;
        if (playing) startMix();
        if (event) event.preventDefault();
        return;
    }
    toggle();
}

window.addEventListener('resize', () => {
    fit();
    shownIndex = -1;
    paint();
});
window.addEventListener('mousemove', poke);
document.addEventListener('keydown', (event) => {
    if (event.key === ' ' || event.key === 'Enter') {
        event.preventDefault();
        onGesture(event);
        return;
    }
    if (event.key === 'ArrowRight') {
        event.preventDefault();
        jump(1);
    }
    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        jump(-1);
    }
    if (event.key === 'f' || event.key === 'F') {
        if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(() => {});
        else document.exitFullscreen();
    }
});
frame.addEventListener('click', onGesture);
document.getElementById('bar').addEventListener('click', (event) => {
    event.stopPropagation();
    const rect = event.currentTarget.getBoundingClientRect();
    elapsedBase = ((event.clientX - rect.left) / rect.width) * total;
    shownIndex = -1;
    if (playing) {
        origin = performance.now();
        if (audioUnlocked || !hasAudio) startMix();
    } else {
        paint();
    }
});

function basenameUrl(assetId, filePath) {
    if (assetId) return `/api/projects/${projectId}/assets/${assetId}`;
    if (filePath) return fileUrl('assets', filePath);
    return null;
}

async function boot() {
    const [slideRows, narration, tracks, effects] = await Promise.all([
        getJson(`/api/projects/${projectId}/slides`),
        getJson(`/api/projects/${projectId}/narration`).catch(() => ({})),
        getJson(`/api/projects/${projectId}/audio-tracks`).catch(() => []),
        getJson(`/api/projects/${projectId}/sound-effects`).catch(() => []),
    ]);

    slides = (Array.isArray(slideRows) ? slideRows : []).map((slide) => {
        if (slide.image_path) slide.image_url = fileUrl('assets', slide.image_path);
        if (slide.video_path) slide.video_url = fileUrl('assets', slide.video_path);
        return slide;
    });
    total = slides.reduce((sum, slide) => sum + durationOf(slide), 0);

    if (!slides.length || total <= 0) {
        body.classList.add('empty');
        return;
    }

    narrationUrl = narration?.audio_url || null;
    musicTracks = (Array.isArray(tracks) ? tracks : [])
        .filter((track) => (track.type || 'music') === 'music')
        .flatMap((track) => {
            const main = basenameUrl(track.asset_id, track.file_path);
            const list = [];
            if (main) {
                list.push({
                    audio_url: main,
                    volume: Number.isFinite(parseFloat(track.volume)) ? parseFloat(track.volume) : 0.35,
                    start_at: parseFloat(track.start_at) || 0,
                    trim_in: parseFloat(track.trim_in) || 0,
                    loop_enabled: track.loop_enabled !== false,
                    slot: track.track_slot ?? 0,
                });
            }
            (track.clips || []).forEach((clip) => {
                const url = basenameUrl(clip.asset_id, clip.file_path);
                if (!url) return;
                list.push({
                    audio_url: url,
                    volume: Number.isFinite(parseFloat(track.volume)) ? parseFloat(track.volume) : 0.35,
                    start_at: parseFloat(clip.start_at) || 0,
                    trim_in: parseFloat(clip.trim_in) || 0,
                    loop_enabled: false,
                    slot: track.track_slot ?? 0,
                });
            });
            return list;
        });
    soundEffects = (Array.isArray(effects) ? effects : [])
        .map((fx) => ({
            id: fx.id,
            audio_url: basenameUrl(fx.asset_id, fx.file_path || fx.asset?.file_path),
            volume: Number.isFinite(parseFloat(fx.volume)) ? parseFloat(fx.volume) : 1,
            start_at: parseFloat(fx.start_at) || 0,
            trim_in: parseFloat(fx.trim_in) || 0,
        }))
        .filter((fx) => fx.audio_url);

    hasAudio = !!(narrationUrl || musicTracks.length || soundEffects.length);
    fit();
    origin = performance.now();
    body.classList.add('paused');
    playing = false;
    paint();
    play();
}

boot().catch(() => {
    body.classList.add('empty');
    document.getElementById('empty').textContent = 'Não foi possível abrir a tela.';
});
