<?php

namespace App\Services\Tts;

use App\Services\Render\FfmpegRenderService;
use App\Support\ExternalHttp;

class ElevenLabsTtsEngine implements TtsEngineInterface
{
    public function __construct(
        private FfmpegRenderService $ffmpeg,
        private TtsCredentials $credentials,
    ) {}

    public function synthesize(string $text, string $voice, string $outputPath): array
    {
        $apiKey = $this->credentials->apiKey('elevenlabs');
        if (! $apiKey) {
            throw new \RuntimeException('ElevenLabs sem chave. Configure em Integrações (ou ELEVENLABS_API_KEY no .env).');
        }

        $voiceId = $voice ?: $this->credentials->defaultVoice('elevenlabs') ?: '21m00Tcm4TlvDq8ikWAM';

        $response = ExternalHttp::client(120)->withHeaders(['xi-api-key' => $apiKey])
            ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                'text' => $text,
                'model_id' => 'eleven_multilingual_v2',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('ElevenLabs falhou: '.$response->body());
        }

        file_put_contents($outputPath, $response->body());

        return [
            'audio_path' => $outputPath,
            'duration_seconds' => $this->ffmpeg->getAudioDuration($outputPath),
        ];
    }
}
