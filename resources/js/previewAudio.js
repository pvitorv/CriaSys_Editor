/** Mix de narração + trilhas + efeitos no preview do editor. */

export class PreviewAudioMixer {
    constructor() {
        this.players = [];
        this.timeouts = [];
    }

    stop() {
        this.timeouts.forEach((id) => clearTimeout(id));
        this.timeouts = [];
        this.players.forEach((player) => {
            try {
                player.pause();
                player.src = '';
            } catch (_) {
                //
            }
        });
        this.players = [];
    }

    /**
     * Áudio acompanha o preview — não controla início/parada da reprodução.
     *
     * @param {object} options
     * @param {string|null} options.narrationUrl
     * @param {Array<{volume?: number, loop_enabled?: boolean, segments?: Array}>} options.musicTracks
     * @param {Array<{audio_url?: string, volume?: number, start_at?: number}>} options.soundEffects
     * @param {number} [options.totalDuration]
     * @param {number} [options.startOffsetSec]
     */
    play({
        narrationUrl,
        musicTracks = [],
        soundEffects = [],
        totalDuration = 0,
        startOffsetSec = 0,
    }) {
        this.stop();

        const effectiveTotal = totalDuration > 0 ? totalDuration : 300;
        const offset = Math.max(0, startOffsetSec);

        if (narrationUrl) {
            const narration = new Audio(narrationUrl);
            narration.volume = 1;
            if (offset > 0.05) {
                narration.addEventListener('loadedmetadata', () => {
                    narration.currentTime = offset;
                }, { once: true });
            }
            this.players.push(narration);
            narration.play().catch(() => {});
        }

        musicTracks.forEach((track) => {
            this.scheduleMusicTrack(track, effectiveTotal, offset);
        });

        soundEffects
            .filter((fx) => fx?.audio_url)
            .forEach((fx) => {
                const fxStart = fx.start_at ?? 0;
                if (fxStart >= effectiveTotal) {
                    return;
                }
                if (fxStart + 0.05 >= offset) {
                    this.scheduleAudio(fx.audio_url, fxStart - offset, fx.volume ?? 1);
                }
            });
    }

    scheduleMusicTrack(track, totalDuration, startOffsetSec) {
        const segments = (track.segments || []).filter((s) => s?.audio_url);
        if (!segments.length) {
            return;
        }

        const loopEnabled = track.loop_enabled !== false;
        const volume = track.volume ?? 0.35;
        const events = [];

        const patternStart = Math.min(...segments.map((s) => s.start_at ?? 0));
        const patternEnd = Math.max(
            ...segments.map((s) => (s.start_at ?? 0) + (s.duration ?? 30)),
        );
        const patternLen = Math.max(0.1, patternEnd - patternStart);

        let cycleOffset = 0;
        let guard = 0;

        while (patternStart + cycleOffset < totalDuration && guard < 200) {
            guard++;
            segments.forEach((seg) => {
                const absStart = (seg.start_at ?? 0) + cycleOffset;
                const segDur = seg.duration ?? 30;
                const absEnd = absStart + segDur;

                if (absEnd <= startOffsetSec + 0.05 || absStart >= totalDuration) {
                    return;
                }

                events.push({
                    url: seg.audio_url,
                    absStart,
                    segDur,
                    offsetInClip: Math.max(0, startOffsetSec - absStart),
                    playDuration: Math.min(segDur, totalDuration - Math.max(absStart, startOffsetSec)),
                });
            });

            if (!loopEnabled || patternStart + cycleOffset + patternLen >= totalDuration) {
                break;
            }

            cycleOffset += patternLen;
        }

        events.forEach((ev) => {
            const delaySec = Math.max(0, ev.absStart - startOffsetSec);
            this.scheduleAudio(ev.url, delaySec, volume, {
                offsetInClip: ev.offsetInClip,
                maxDuration: ev.playDuration,
            });
        });
    }

    scheduleAudio(url, startAt, volume, { offsetInClip = 0, maxDuration = null } = {}) {
        const startMs = Math.max(0, startAt) * 1000;

        const id = setTimeout(() => {
            const audio = new Audio(url);
            audio.volume = Math.min(1, Math.max(0, volume));
            if (offsetInClip > 0.05) {
                const applyOffset = () => {
                    audio.currentTime = offsetInClip;
                };
                if (audio.readyState >= 1) {
                    applyOffset();
                } else {
                    audio.addEventListener('loadedmetadata', applyOffset, { once: true });
                }
            }
            if (maxDuration != null && maxDuration > 0) {
                const stopId = setTimeout(() => {
                    try {
                        audio.pause();
                    } catch (_) {
                        //
                    }
                }, maxDuration * 1000);
                this.timeouts.push(stopId);
            }
            this.players.push(audio);
            audio.play().catch(() => {});
        }, startMs);

        this.timeouts.push(id);
    }
}
