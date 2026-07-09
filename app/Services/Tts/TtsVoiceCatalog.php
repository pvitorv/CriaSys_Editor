<?php

namespace App\Services\Tts;

use App\Support\ExternalHttp;
use Illuminate\Support\Facades\Cache;

class TtsVoiceCatalog
{
    public function __construct(private TtsCredentials $credentials) {}

    /**
     * @return array<int, array{id: string, name: string, group?: string}>
     */
    public function voices(string $provider): array
    {
        return match ($provider) {
            'edge' => $this->edgeVoices(),
            'piper' => $this->piperVoices(),
            'openai' => $this->openAiVoices(),
            'elevenlabs' => $this->elevenLabsVoices(),
            default => [],
        };
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function edgeVoices(): array
    {
        $voices = config('criasys.tts.voices', []);
        $out = [];
        foreach ($voices as $id => $name) {
            $out[] = ['id' => $id, 'name' => $name];
        }

        return $out;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function piperVoices(): array
    {
        $factory = app(TtsEngineFactory::class);
        $voices = config('criasys.tts.piper_voices', []);
        $out = [];

        foreach ($voices as $id => $meta) {
            $model = $meta['model'] ?? null;
            if ($model && is_file($factory->absPath((string) $model))) {
                $out[] = ['id' => $id, 'name' => $meta['label'] ?? $id];
            }
        }

        if ($out === [] && is_file($factory->absPath((string) config('criasys.tts.piper_model')))) {
            $out[] = ['id' => 'default', 'name' => 'Voz padrão Piper (pt-BR)'];
        }

        return $out;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function openAiVoices(): array
    {
        return array_map(
            fn (string $v) => ['id' => $v, 'name' => ucfirst($v)],
            ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer']
        );
    }

    /**
     * @return array<int, array{id: string, name: string, group?: string}>
     */
    private function elevenLabsVoices(): array
    {
        $apiKey = $this->credentials->apiKey('elevenlabs');
        if (! $apiKey) {
            return [];
        }

        $cacheKey = 'tts:voices:elevenlabs:'.md5($apiKey);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $response = ExternalHttp::client(30)
            ->withHeaders(['xi-api-key' => $apiKey])
            ->get('https://api.elevenlabs.io/v1/voices');

        if ($response->successful()) {
            $voices = array_map(function (array $v) {
                $category = $v['category'] ?? 'premade';
                $base = $v['name'] ?? $v['voice_id'];

                return [
                    'id' => $v['voice_id'],
                    'name' => $base.$this->categoryTag($category),
                    'group' => $category,
                ];
            }, $response->json('voices', []));

            // Vozes "premade" (grátis na API) primeiro, para facilitar no plano free.
            usort($voices, fn ($a, $b) => ($a['group'] === 'premade' ? 0 : 1) <=> ($b['group'] === 'premade' ? 0 : 1));

            if ($voices !== []) {
                Cache::put($cacheKey, $voices, now()->addMinutes(10));
            }

            return $voices;
        }

        // Chave sem permissão voices_read (ou outro erro): usa a voz padrão
        // configurada em Integrações para não travar a síntese.
        return $this->fallbackDefaultVoice();
    }

    private function categoryTag(string $category): string
    {
        return match ($category) {
            'cloned' => ' (minha voz)',
            'premade' => ' (grátis)',
            'generated' => ' (gerada)',
            default => ' (biblioteca — plano pago)',
        };
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function fallbackDefaultVoice(): array
    {
        $default = $this->credentials->defaultVoice('elevenlabs');

        if ($default && trim($default) !== '') {
            return [['id' => $default, 'name' => 'Voz padrão configurada']];
        }

        return [];
    }

    public function forgetElevenLabsCache(?string $apiKey): void
    {
        if ($apiKey) {
            Cache::forget('tts:voices:elevenlabs:'.md5($apiKey));
        }
    }
}
