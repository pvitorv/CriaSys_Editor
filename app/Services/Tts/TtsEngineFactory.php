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
                : (in_array($voice, ['default'], true) ? $voice : (string) (env('TTS_DEFAULT_VOICE') ?: 'pt-br-narrador')),
            'openai' => in_array($voice, ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'], true)
                ? $voice
                : (config('criasys.tts.openai_voice') ?: 'onyx'),
            'edge' => str_contains($voice, 'Neural')
                ? $voice
                : (config('criasys.tts.default_voice') ?: 'pt-BR-FranciscaNeural'),
            default => $voice,
        };
    }

    public function friendlyFailureMessage(string $detail): string
    {
        if (stripos($detail, 'No audio was received') !== false || stripos($detail, 'Output has been disabled') !== false) {
            return 'Edge TTS bloqueado pela Microsoft. Selecione **Piper** no motor TTS (grátis, já instalado) ou conecte OpenAI em Integrações.';
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
        foreach (['openai', 'piper', config('criasys.tts.default_engine'), 'edge', 'elevenlabs'] as $slug) {
            if ($slug && $this->isAvailable($slug)) {
                return $slug;
            }
        }

        return config('criasys.tts.default_engine', 'edge');
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
