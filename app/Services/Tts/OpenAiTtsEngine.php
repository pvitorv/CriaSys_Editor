<?php

namespace App\Services\Tts;

use App\Services\Render\FfmpegRenderService;
use Illuminate\Support\Facades\Http;

class OpenAiTtsEngine implements TtsEngineInterface
{
    public function __construct(private FfmpegRenderService $ffmpeg) {}

    public function synthesize(string $text, string $voice, string $outputPath): array
    {
        $apiKey = config('criasys.tts.openai_api_key');
        if (! $apiKey) {
            throw new \RuntimeException('OpenAI TTS: defina OPENAI_API_KEY no .env');
        }

        $voice = $voice ?: config('criasys.tts.openai_voice', 'nova');

        $response = Http::timeout(120)
            ->withToken($apiKey)
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
