<?php

namespace App\Services\Tts;

use App\Models\User;

class TtsCredentials
{
    private const CONFIG_MAP = [
        'elevenlabs' => ['key' => 'criasys.tts.elevenlabs_api_key', 'voice' => 'criasys.tts.elevenlabs_voice_id'],
        'openai' => ['key' => 'criasys.tts.openai_api_key', 'voice' => 'criasys.tts.openai_voice'],
    ];

    private ?User $user = null;

    /**
     * Define explicitamente o usuário dono das credenciais (usado em jobs/fila,
     * onde não existe usuário autenticado).
     */
    public function forUser(?User $user): self
    {
        $clone = new self;
        $clone->user = $user;

        return $clone;
    }

    public function apiKey(string $provider): ?string
    {
        $integration = $this->integration($provider);
        if ($integration && $integration->enabled && $integration->hasApiKey()) {
            return $integration->apiKey();
        }

        $configKey = self::CONFIG_MAP[$provider]['key'] ?? null;

        return $configKey ? config($configKey) : null;
    }

    public function defaultVoice(string $provider): ?string
    {
        $integration = $this->integration($provider);
        if ($integration && trim((string) $integration->default_voice) !== '') {
            return $integration->default_voice;
        }

        $configKey = self::CONFIG_MAP[$provider]['voice'] ?? null;

        return $configKey ? config($configKey) : null;
    }

    public function hasKey(string $provider): bool
    {
        return trim((string) $this->apiKey($provider)) !== '';
    }

    private function integration(string $provider): ?\App\Models\UserIntegration
    {
        $user = $this->user ?? auth()->user();

        return $user?->integrationFor($provider);
    }
}
