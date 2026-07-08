<?php

namespace App\Services\Tts;

class CoquiTtsEngine implements TtsEngineInterface
{
    public function synthesize(string $text, string $voice, string $outputPath): array
    {
        throw new \RuntimeException('Coqui TTS ainda não implementado (Fase 4).');
    }
}
