/** Mix de narração + trilhas + efeitos no preview do editor. */

export class PreviewAudioMixer {
    constructor() {
        this.players = [];
        this.timeouts = [];
        this.onEnd = null;
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
        this.onEnd = null;
    }

    /**
     * @param {object} options
     * @param {string|null} options.narrationUrl
     * @param {Array<{audio_url?: string, volume?: number, start_at?: number}>} options.musicTracks
     * @param {Array<{audio_url?: string, volume?: number, start_at?: number}>} options.soundEffects
     * @param {() => void} [options.onEnd]
     */
    play({ narrationUrl, musicTracks = [], soundEffects = [], onEnd = null }) {
        this.stop();
        this.onEnd = onEnd;

        let narrationEnded = !narrationUrl;

        const maybeFinish = () => {
            if (narrationEnded && typeof this.onEnd === 'function') {
                this.onEnd();
            }
        };

        if (narrationUrl) {
            const narration = new Audio(narrationUrl);
            narration.volume = 1;
            narration.addEventListener('ended', () => {
                narrationEnded = true;
                maybeFinish();
            });
            narration.addEventListener('error', () => {
                narrationEnded = true;
                maybeFinish();
            });
            this.players.push(narration);
            narration.play().catch(() => {
                narrationEnded = true;
                maybeFinish();
            });
        }

        musicTracks
            .filter((track) => track?.audio_url)
            .forEach((track) => {
                this.scheduleAudio(track.audio_url, track.start_at ?? 0, track.volume ?? 0.35);
            });

        soundEffects
            .filter((fx) => fx?.audio_url)
            .forEach((fx) => {
                this.scheduleAudio(fx.audio_url, fx.start_at ?? 0, fx.volume ?? 1);
            });

        if (!narrationUrl && typeof onEnd === 'function') {
            const maxStart = Math.max(
                0,
                ...musicTracks.map((t) => t.start_at ?? 0),
                ...soundEffects.map((f) => f.start_at ?? 0),
            );
            const id = setTimeout(onEnd, Math.max(3000, (maxStart + 5) * 1000));
            this.timeouts.push(id);
        }
    }

    scheduleAudio(url, startAt, volume) {
        const startMs = Math.max(0, startAt) * 1000;

        const id = setTimeout(() => {
            const audio = new Audio(url);
            audio.volume = Math.min(1, Math.max(0, volume));
            this.players.push(audio);
            audio.play().catch(() => {});
        }, startMs);

        this.timeouts.push(id);
    }
}
