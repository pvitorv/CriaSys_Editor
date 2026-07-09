<?php

namespace App\Services\Tts;

use App\Services\Render\FfmpegRenderService;
use App\Support\ExternalHttp;

class OpenAiTtsEngine implements TtsEngineInterface
{
    public function __construct(
        private FfmpegRenderService $ffmpeg,
        private TtsCredentials $credentials,
    ) {}

    public function synthesize(string $text, string $voice, string $outputPath): array
    {
        $apiKey = $this->credentials->apiKey('openai');
        if (! $apiKey) {
            throw new \RuntimeException('OpenAI TTS sem chave. Configure em Integrações (ou OPENAI_API_KEY no .env).');
        }

        $voice = $voice ?: $this->credentials->defaultVoice('openai') ?: 'nova';

        $response = ExternalHttp::client(120)->withToken($apiKey)
            ->post('https://api.openai.com/v1/audio/speech', [
                'model' => 'tts-1',
                'input' => $text,
                'voice' => $voice,
                'response_format' => 'mp3',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI TTS falhou: '.$response->body());
        }

        file_put_contents($outputPath, $response->body());

        return [
            'audio_path' => $outputPath,
            'duration_seconds' => $this->ffmpeg->getAudioDuration($outputPath),
        ];
    }
}
