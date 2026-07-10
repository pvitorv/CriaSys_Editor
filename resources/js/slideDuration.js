/** Duração automática de slides — narração proporcional (3–15s) ou vídeo. */

export const DURATION_MIN = 3;
export const DURATION_MAX = 15;

export function narrationWeight(text) {
    const t = String(text ?? '').trim();
    if (!t) return 1;

    const lines = t.split(/\n/).filter((line) => line.trim()).length;
    const words = t.split(/\s+/).filter(Boolean).length;

    return Math.max(1, words + lines * 2);
}

export function slideNarrationSource(slide) {
    return (slide?.narration_text || slide?.body_text || '').trim();
}

export function roundDuration(seconds) {
    return Math.round(Math.max(DURATION_MIN, Math.min(DURATION_MAX, seconds)) * 10) / 10;
}

/**
 * Tempos proporcionais entre slides (min 3s, max 15s) conforme palavras/linhas.
 *
 * @param {Array<object>} slides
 * @param {(slide: object) => string} [textSelector]
 * @returns {number[]}
 */
export function proportionalDurations(slides, textSelector = slideNarrationSource) {
    if (!slides.length) return [];

    const weights = slides.map((slide) => narrationWeight(textSelector(slide)));
    const minW = Math.min(...weights);
    const maxW = Math.max(...weights);

    return weights.map((weight) => {
        if (maxW === minW) {
            return roundDuration((DURATION_MIN + DURATION_MAX) / 2);
        }

        const ratio = (weight - minW) / (maxW - minW);

        return roundDuration(DURATION_MIN + ratio * (DURATION_MAX - DURATION_MIN));
    });
}

/**
 * Aplica duração automática nos slides (respeita modo manual).
 *
 * @param {Array<object>} slides
 * @returns {Array<object>}
 */
export function applyAutomaticDurations(slides) {
    const narrationIndexes = [];

    slides.forEach((slide, index) => {
        if (slide.duration_mode === 'manual') return;

        if (slide.duration_mode === 'video' || (slide.video_path && slide.duration_mode !== 'narration' && slide.duration_mode !== 'manual')) {
            if (slide.video_duration_seconds > 0) {
                slide.duration_seconds = Math.round(Math.max(slide.video_duration_seconds, 0.5) * 10) / 10;
            }
            return;
        }

        narrationIndexes.push(index);
    });

    if (!narrationIndexes.length) return slides;

    const subset = narrationIndexes.map((i) => slides[i]);
    const durations = proportionalDurations(subset);

    narrationIndexes.forEach((slideIndex, i) => {
        if (slides[slideIndex].duration_mode !== 'manual') {
            slides[slideIndex].duration_seconds = durations[i];
        }
    });

    return slides;
}

export function probeVideoFileDuration(file) {
    return new Promise((resolve) => {
        if (!file) {
            resolve(null);
            return;
        }

        const video = document.createElement('video');
        video.preload = 'metadata';
        const url = URL.createObjectURL(file);

        const finish = (value) => {
            URL.revokeObjectURL(url);
            resolve(value);
        };

        video.onloadedmetadata = () => finish(Number.isFinite(video.duration) ? video.duration : null);
        video.onerror = () => finish(null);
        video.src = url;
    });
}
