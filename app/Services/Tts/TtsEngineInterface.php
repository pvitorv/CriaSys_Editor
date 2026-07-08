<?php

namespace App\Services\Tts;

interface TtsEngineInterface
{
    /**
     * @return array{audio_path: string, duration_seconds: float}
     */
    public function synthesize(string $text, string $voice, string $outputPath): array;
}
