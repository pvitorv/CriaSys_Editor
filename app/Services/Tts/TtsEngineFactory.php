<?php

namespace App\Services\Tts;

use Illuminate\Support\Facades\Log;

class TtsEngineFactory
{
    public function __construct(private TtsCredentials $credentials) {}

    public function resolve(?string $engine = null): TtsEngineInterface
    {
        $engine ??= config('criasys.tts.default_engine');

        return match ($engine) {
            'elevenlabs' => app(ElevenLabsTtsEngine::class),
            'openai' => app(OpenAiTtsEngine::class),
            'piper' => app(PiperTtsEngine::class),
            'edge' => app(EdgeTtsEngine::class),
            default => app(EdgeTtsEngine::class),
        };
    }

    /**
     * Tenta o motor pedido e, se falhar, usa Piper/OpenAI antes de desistir.
     *
     * @return array{audio_path: string, duration_seconds: float, engine: string}
     */
    public function synthesizeWithFallback(string $text, string $voice, string $outputPath, ?string $preferredEngine = null): array
    {
        $lastError = null;

        foreach ($this->fallbackChain($preferredEngine) as $slug) {
            if (! $this->isAvailable($slug)) {
                continue;
            }

            try {
                $mappedVoice = $this->mapVoiceForEngine($slug, $voice);
                $result = $this->resolve($slug)->synthesize($text, $mappedVoice, $outputPath);

                return array_merge($result, ['engine' => $slug]);
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning('TTS motor falhou', ['engine' => $slug, 'error' => $e->getMessage()]);
            }
        }

        throw new \RuntimeException(
            $this->friendlyFailureMessage($lastError?->getMessage() ?? 'Nenhum motor TTS respondeu.')
        );
    }

    /**
     * @return list<string>
     */
    public function fallbackChain(?string $preferred = null): array
    {
        $candidates = array_filter([
            $preferred,
            $this->piperAvailable() ? 'piper' : null,
            config('criasys.tts.default_engine'),
            'openai',
            'piper',
            'edge',
            'elevenlabs',
        ]);

        return array_values(array_unique($candidates));
    }

    public function mapVoiceForEngine(string $engine, string $voice): string
    {
        return match ($engine) {
            'piper' => array_key_exists($voice, config('criasys.tts.piper_voices', []))
                ? $voice
                : ($voice === 'default' ? $voice : $this->defaultVoiceFor('piper')),
            'openai' => in_array($voice, ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'], true)
                ? $voice
                : (config('criasys.tts.openai_voice') ?: 'nova'),
            'edge' => $this->isEdgeVoice($voice)
                ? $voice
                : $this->defaultVoiceFor('edge'),
            default => $voice,
        };
    }

    public function defaultVoiceFor(string $engine): string
    {
        return match ($engine) {
            'piper' => $this->defaultPiperVoice(),
            'openai' => (string) (config('criasys.tts.openai_voice') ?: 'nova'),
            'edge' => $this->defaultEdgeVoice(),
            'elevenlabs' => $this->credentials->defaultVoice('elevenlabs')
                ?: (string) config('criasys.tts.elevenlabs_voice_id'),
            default => $this->defaultEdgeVoice(),
        };
    }

    public function resolveFfmpegPath(): string
    {
        foreach ($this->ffmpegCandidates() as $candidate) {
            $abs = $this->absPath($candidate);
            if ($abs !== '' && is_file($abs)) {
                return $abs;
            }
        }

        return (string) config('criasys.ffmpeg_path', 'ffmpeg');
    }

    public function resolveFfprobePath(): string
    {
        foreach ($this->ffprobeCandidates() as $candidate) {
            $abs = $this->absPath($candidate);
            if ($abs !== '' && is_file($abs)) {
                return $abs;
            }
        }

        return (string) config('criasys.ffprobe_path', 'ffprobe');
    }

    public function friendlyFailureMessage(string $detail): string
    {
        if (stripos($detail, 'No audio was received') !== false || stripos($detail, 'Output has been disabled') !== false) {
            return 'Edge TTS bloqueado pela Microsoft. Use **Piper** (grátis, local) ou conecte OpenAI em Integrações.';
        }

        if (stripos($detail, 'Invalid voice') !== false) {
            return 'Voz incompatível com o motor TTS. Troque o motor para Piper ou escolha uma voz da lista.';
        }

        if (stripos($detail, 'converter WAV Piper') !== false || stripos($detail, 'ffmpeg') !== false) {
            return 'Piper precisa do FFmpeg para gerar MP3. Defina FFMPEG_PATH no .env ou use o app Electron (FFmpeg embutido).';
        }

        return $detail !== '' ? $detail : 'Falha ao gerar narração.';
    }

    /**
     * @return array<int, array{slug: string, name: string, available: bool, note: string|null, price_hint: string|null, recommended: bool}>
     */
    public function listEngines(): array
    {
        $engines = config('criasys.tts.engines', []);
        $recommended = $this->recommendedEngine();

        return array_map(function (array $engine) use ($recommended) {
            $available = $this->isAvailable($engine['slug']);

            return [
                'slug' => $engine['slug'],
                'name' => $engine['name'],
                'available' => $available,
                'recommended' => $engine['slug'] === $recommended,
                'price_hint' => $engine['price_hint'] ?? null,
                'note' => $available ? null : ($engine['unavailable_note'] ?? 'Indisponível neste ambiente.'),
            ];
        }, $engines);
    }

    public function recommendedEngine(): string
    {
        foreach (['piper', 'openai', config('criasys.tts.default_engine'), 'edge', 'elevenlabs'] as $slug) {
            if ($slug && $this->isAvailable($slug)) {
                return $slug;
            }
        }

        return config('criasys.tts.default_engine', 'piper');
    }

    public function isAvailable(string $slug): bool
    {
        return match ($slug) {
            'edge' => true,
            'piper' => $this->piperAvailable(),
            'elevenlabs' => $this->credentials->hasKey('elevenlabs'),
            'openai' => $this->credentials->hasKey('openai'),
            default => false,
        };
    }

    private function piperAvailable(): bool
    {
        $piper = $this->absPath((string) config('criasys.tts.piper_path'));
        $model = $this->absPath((string) config('criasys.tts.piper_model'));

        return $piper !== '' && is_file($piper) && $model !== '' && is_file($model);
    }

    private function isEdgeVoice(string $voice): bool
    {
        if ($voice === '') {
            return false;
        }

        if (str_contains($voice, 'Neural')) {
            return true;
        }

        return (bool) preg_match('/^pt-[A-Z]{2}-/i', $voice);
    }

    private function defaultEdgeVoice(): string
    {
        $voices = config('criasys.tts.voices', []);
        $first = array_key_first($voices);

        return is_string($first) && $first !== '' ? $first : 'pt-BR-FranciscaNeural';
    }

    private function defaultPiperVoice(): string
    {
        $voices = config('criasys.tts.piper_voices', []);
        foreach (['pt-br-faber-calmo', 'pt-br-faber', 'pt-br-narrador', 'pt-br-edresson'] as $id) {
            $model = $voices[$id]['model'] ?? null;
            if ($model && is_file($this->absPath((string) $model))) {
                return $id;
            }
        }

        return is_file($this->absPath((string) config('criasys.tts.piper_model'))) ? 'default' : 'pt-br-faber';
    }

    /** @return list<string> */
    private function ffmpegCandidates(): array
    {
        $platform = PHP_OS_FAMILY === 'Windows' ? 'win' : (PHP_OS_FAMILY === 'Darwin' ? 'mac' : 'linux');
        $binary = PHP_OS_FAMILY === 'Windows' ? 'ffmpeg.exe' : 'ffmpeg';

        return array_values(array_filter([
            config('criasys.ffmpeg_path'),
            "electron/ffmpeg/{$platform}/{$binary}",
            "bin/ffmpeg/{$binary}",
            $binary,
        ]));
    }

    /** @return list<string> */
    private function ffprobeCandidates(): array
    {
        $platform = PHP_OS_FAMILY === 'Windows' ? 'win' : (PHP_OS_FAMILY === 'Darwin' ? 'mac' : 'linux');
        $binary = PHP_OS_FAMILY === 'Windows' ? 'ffprobe.exe' : 'ffprobe';

        return array_values(array_filter([
            config('criasys.ffprobe_path'),
            "electron/ffmpeg/{$platform}/{$binary}",
            "bin/ffmpeg/{$binary}",
            $binary,
        ]));
    }

    public function absPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (preg_match('#^[A-Za-z]:\\\\#', $normalized) || str_starts_with($normalized, DIRECTORY_SEPARATOR)) {
            return $normalized;
        }

        return base_path($normalized);
    }
}
