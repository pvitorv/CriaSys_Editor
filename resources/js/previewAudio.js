/** Mix de narração + trilhas + efeitos no preview do editor. */

const SILENT_WAV = 'data:audio/wav;base64,UklGRigAAABXQVZFZm10IBIAAAABAAEARKwAAIhYAQACABAAZGF0YQQAAAAAAA==';

export class PreviewAudioMixer {
    constructor() {
        /** @type {AudioContext|null} */
        this.ctx = null;
        /** @type {Array<AudioBufferSourceNode>} */
        this.sources = [];
        /** @type {Array<GainNode>} */
        this.gains = [];
        /** @type {Array<{ audio: HTMLAudioElement, role: string, baseVolume: number, slot?: number, fxId?: number }>} */
        this.htmlPlayers = [];
        this.timeouts = [];
        /** @type {{ narration?: GainNode, music?: GainNode, sfx?: GainNode }} */
        this.roleGains = {};
        /** @type {Map<string, GainNode>} */
        this.trackGains = new Map();
        this.mixVolumes = { narration: 1, music: 1, sfx: 1 };
        this.bufferCache = new Map();
    }

    /** Chamar sincronamente no clique do usuário (Play / Testar). */
    beginFromUserGesture() {
        if (!this.ctx) {
            this.ctx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (this.ctx.state === 'suspended') {
            void this.ctx.resume();
        }
        try {
            const unlock = new Audio(SILENT_WAV);
            unlock.volume = 0.001;
            void unlock.play().then(() => unlock.pause()).catch(() => {});
        } catch (_) {
            //
        }
    }

    stop() {
        this.timeouts.forEach((id) => clearTimeout(id));
        this.timeouts = [];
        this.sources.forEach((source) => {
            try {
                source.stop();
            } catch (_) {
                //
            }
            try {
                source.disconnect();
            } catch (_) {
                //
            }
        });
        this.sources = [];
        this.gains.forEach((g) => {
            try {
                g.disconnect();
            } catch (_) {
                //
            }
        });
        this.gains = [];
        this.htmlPlayers.forEach(({ audio }) => {
            try {
                audio.pause();
                audio.src = '';
            } catch (_) {
                //
            }
        });
        this.htmlPlayers = [];
        this.roleGains = {};
        this.trackGains.clear();
    }

    setMixVolumes(volumes = {}) {
        this.mixVolumes = { ...this.mixVolumes, ...volumes };
        if (this.roleGains.narration) {
            this.roleGains.narration.gain.value = this.mixVolumes.narration ?? 1;
        }
        if (this.roleGains.music) {
            this.roleGains.music.gain.value = this.mixVolumes.music ?? 1;
        }
        if (this.roleGains.sfx) {
            this.roleGains.sfx.gain.value = this.mixVolumes.sfx ?? 1;
        }
        this.htmlPlayers.forEach((entry) => this.applyHtmlVolume(entry));
    }

    updateTrackVolume(role, slot, volume) {
        const key = `${role}:${slot}`;
        const gain = this.trackGains.get(key);
        if (gain) {
            gain.gain.value = Math.min(1, Math.max(0, volume));
        }
    }

    updateSfxVolume(fxId, volume) {
        this.htmlPlayers
            .filter((p) => p.role === 'sfx' && p.fxId === fxId)
            .forEach((p) => {
                p.baseVolume = volume;
                this.applyHtmlVolume(p);
            });
    }

    applyHtmlVolume(entry) {
        const mix = this.mixVolumes[entry.role] ?? 1;
        entry.audio.volume = Math.min(1, Math.max(0, (entry.baseVolume ?? 1) * mix));
    }

    ensureRoleGains() {
        if (!this.ctx) {
            return;
        }
        const ctx = this.ctx;
        if (!this.roleGains.narration) {
            const g = ctx.createGain();
            g.gain.value = this.mixVolumes.narration ?? 1;
            g.connect(ctx.destination);
            this.roleGains.narration = g;
            this.gains.push(g);
        }
        if (!this.roleGains.music) {
            const g = ctx.createGain();
            g.gain.value = this.mixVolumes.music ?? 1;
            g.connect(ctx.destination);
            this.roleGains.music = g;
            this.gains.push(g);
        }
    }

    createTrackGain(role, trackKey, volume, roleGain) {
        if (!this.ctx || !roleGain) {
            return null;
        }
        const gain = this.ctx.createGain();
        gain.gain.value = Math.min(1, Math.max(0, volume));
        gain.connect(roleGain);
        this.gains.push(gain);
        this.trackGains.set(`${role}:${trackKey}`, gain);

        return gain;
    }

    async loadBuffer(url) {
        if (this.bufferCache.has(url)) {
            return this.bufferCache.get(url);
        }
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'audio/*,*/*' },
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.arrayBuffer();
        const buffer = await this.ctx.decodeAudioData(data.slice(0));
        this.bufferCache.set(url, buffer);

        return buffer;
    }

    scheduleBuffer(buffer, {
        when = 0,
        offset = 0,
        duration = null,
        loop = false,
        gainNode,
    }) {
        if (!this.ctx || !gainNode) {
            return;
        }

        const source = this.ctx.createBufferSource();
        source.buffer = buffer;
        source.loop = loop;
        if (loop && offset > 0) {
            source.loopStart = offset;
        }
        source.connect(gainNode);

        const startAt = Math.max(this.ctx.currentTime + 0.02, when);
        const playDuration = duration != null ? Math.max(0.01, duration) : undefined;

        try {
            if (playDuration != null) {
                source.start(startAt, Math.max(0, offset), playDuration);
            } else {
                source.start(startAt, Math.max(0, offset));
            }
        } catch (_) {
            return;
        }

        this.sources.push(source);
    }

    /**
     * Efeitos sonoros — HTMLAudioElement (confiável para MP3/OGG curtos).
     * Deve ser chamado sincronamente durante o gesto do usuário (clique Play).
     */
    scheduleSoundEffectsHtml(soundEffects, offset, effectiveTotal) {
        for (const fx of soundEffects) {
            if (!fx?.audio_url) {
                continue;
            }

            const fxStart = parseFloat(fx.start_at) || 0;
            if (fxStart >= effectiveTotal || fxStart + 0.05 < offset) {
                continue;
            }

            const trimIn = (parseFloat(fx.trim_in) || 0) + Math.max(0, offset - fxStart);
            const delayMs = Math.max(0, (fxStart - offset) * 1000);
            const baseVolume = parseFloat(fx.volume) >= 0 ? parseFloat(fx.volume) : 1;

            const audio = new Audio(fx.audio_url);
            audio.preload = 'auto';

            const entry = {
                audio,
                role: 'sfx',
                baseVolume,
                fxId: fx.id,
            };
            this.htmlPlayers.push(entry);

            const fire = () => {
                this.applyHtmlVolume(entry);
                if (trimIn > 0.05) {
                    try {
                        audio.currentTime = trimIn;
                    } catch (_) {
                        //
                    }
                }
                void audio.play().catch(() => {});
            };

            if (delayMs <= 0) {
                if (audio.readyState >= 2) {
                    fire();
                } else {
                    audio.addEventListener('canplaythrough', fire, { once: true });
                    audio.addEventListener('error', () => {}, { once: true });
                    audio.load();
                }
            } else {
                const id = setTimeout(() => {
                    if (audio.readyState >= 2) {
                        fire();
                    } else {
                        audio.addEventListener('canplaythrough', fire, { once: true });
                        audio.addEventListener('error', () => {}, { once: true });
                        audio.load();
                    }
                }, delayMs);
                this.timeouts.push(id);
            }
        }
    }

    /**
     * Toca um efeito imediatamente (botão Testar).
     */
    async playSfxNow(url, volume = 1, trimIn = 0) {
        this.beginFromUserGesture();

        return new Promise((resolve) => {
            const audio = new Audio(url);
            audio.preload = 'auto';
            audio.volume = Math.min(1, Math.max(0, volume));

            const play = () => {
                if (trimIn > 0.05) {
                    try {
                        audio.currentTime = trimIn;
                    } catch (_) {
                        //
                    }
                }
                audio.volume = Math.min(1, Math.max(0, volume));
                audio.play()
                    .then(() => resolve(true))
                    .catch(() => resolve(false));
            };

            audio.addEventListener('error', () => resolve(false), { once: true });

            if (audio.readyState >= 2) {
                play();
            } else {
                audio.addEventListener('canplaythrough', play, { once: true });
                audio.load();
            }
        });
    }

    /**
     * @param {object} options
     */
    play({
        narrationUrl,
        musicTracks = [],
        soundEffects = [],
        totalDuration = 0,
        startOffsetSec = 0,
        mixVolumes = {},
    }) {
        this.stop();
        this.beginFromUserGesture();
        this.setMixVolumes(mixVolumes);

        const offset = Math.max(0, startOffsetSec);
        const effectiveTotal = totalDuration > 0 ? totalDuration : 86400;

        // Efeitos: agendar já no clique (antes de qualquer await)
        this.scheduleSoundEffectsHtml(soundEffects, offset, effectiveTotal);

        if (!this.ctx) {
            return;
        }

        const baseWhen = this.ctx.currentTime + 0.05;
        this.ensureRoleGains();

        void this.runPlay({
            narrationUrl,
            musicTracks,
            effectiveTotal,
            offset,
            baseWhen,
        });
    }

    async runPlay({
        narrationUrl,
        musicTracks,
        effectiveTotal,
        offset,
        baseWhen,
    }) {
        if (!this.ctx) {
            return;
        }

        if (narrationUrl) {
            try {
                const buffer = await this.loadBuffer(narrationUrl);
                const trimOffset = Math.min(offset, Math.max(0, buffer.duration - 0.01));
                const duration = buffer.duration - trimOffset;
                this.scheduleBuffer(buffer, {
                    when: baseWhen,
                    offset: trimOffset,
                    duration,
                    gainNode: this.roleGains.narration,
                });
            } catch (_) {
                //
            }
        }

        await Promise.all(
            musicTracks
                .filter((t) => t?.audio_url)
                .map(async (track) => {
                    const trackStart = track.start_at ?? 0;
                    if (trackStart >= effectiveTotal) {
                        return;
                    }

                    try {
                        const buffer = await this.loadBuffer(track.audio_url);
                        const trimIn = (track.trim_in ?? 0) + Math.max(0, offset - trackStart);
                        const when = baseWhen + Math.max(0, trackStart - offset);
                        const playSpan = effectiveTotal - Math.max(trackStart, offset);
                        const trackGain = this.createTrackGain(
                            'music',
                            track.slot ?? 0,
                            track.volume ?? 0.35,
                            this.roleGains.music,
                        );

                        this.scheduleBuffer(buffer, {
                            when,
                            offset: Math.min(trimIn, buffer.duration - 0.01),
                            duration: track.loop_enabled !== false
                                ? playSpan
                                : Math.min(playSpan, buffer.duration - trimIn),
                            loop: track.loop_enabled !== false,
                            gainNode: trackGain,
                        });
                    } catch (_) {
                        //
                    }
                }),
        );
    }
}
