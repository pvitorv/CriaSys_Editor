<?php

namespace App\Services\Tts;

class TtsEngineFactory
{
    public function __construct(private TtsCredentials $credentials) {}

    public function resolve(?string $engine = null): TtsEngineInterface
    {
        $engine ??= config('criasys.tts.default_engine');

        return match ($engine) {
            'coqui' => app(CoquiTtsEngine::class),
            'elevenlabs' => app(ElevenLabsTtsEngine::class),
            'openai' => app(OpenAiTtsEngine::class),
            'edge' => app(EdgeTtsEngine::class),
            default => app(EdgeTtsEngine::class),
        };
    }

    /**
     * @return array<int, array{slug: string, name: string, available: bool, note: string|null}>
     */
    public function listEngines(): array
    {
        $engines = config('criasys.tts.engines', []);

        return array_map(function (array $engine) {
            $available = $this->isAvailable($engine['slug']);

            return [
                'slug' => $engine['slug'],
                'name' => $engine['name'],
                'available' => $available,
                'note' => $available ? null : ($engine['unavailable_note'] ?? 'Indisponível neste ambiente.'),
            ];
        }, $engines);
    }

    public function isAvailable(string $slug): bool
    {
        return match ($slug) {
            'edge' => true,
            'coqui' => (bool) config('criasys.tts.coqui_python'),
            'elevenlabs' => $this->credentials->hasKey('elevenlabs'),
            'openai' => $this->credentials->hasKey('openai'),
            default => false,
        };
    }
}
