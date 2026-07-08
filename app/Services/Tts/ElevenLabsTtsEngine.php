<?php

namespace App\Services\Tts;

use App\Services\Render\FfmpegRenderService;
use Illuminate\Support\Facades\Http;

class ElevenLabsTtsEngine implements TtsEngineInterface
{
    public function __construct(private FfmpegRenderService $ffmpeg) {}

    public function synthesize(string $text, string $voice, string $outputPath): array
    {
        $apiKey = config('criasys.tts.elevenlabs_api_key');
        if (! $apiKey) {
            throw new \RuntimeException('ElevenLabs: defina ELEVENLABS_API_KEY no .env');
        }

        $voiceId = $voice ?: config('criasys.tts.elevenlabs_voice_id', '21m00Tcm4TlvDq8ikWAM');

        $response = Http::timeout(120)
            ->withHeaders(['xi-api-key' => $apiKey])
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
