<?php

namespace App\Services\Tts;

use App\Services\Render\FfmpegRenderService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class CoquiTtsEngine implements TtsEngineInterface
{
    public function __construct(private FfmpegRenderService $ffmpeg) {}

    public function synthesize(string $text, string $voice, string $outputPath): array
    {
        $python = config('criasys.tts.coqui_python', 'python');
        $script = base_path('scripts/coqui-tts.py');

        if (! File::exists($script)) {
            throw new \RuntimeException('Coqui TTS: script scripts/coqui-tts.py não encontrado.');
        }

        $textFile = dirname($outputPath).DIRECTORY_SEPARATOR.'coqui_'.uniqid().'.txt';
        File::put($textFile, $text);

        $result = Process::timeout(300)->run([
            $python, $script,
            '--text-file', $textFile,
            '--output', $outputPath,
            '--language', 'pt',
        ]);

        File::delete($textFile);

        if (! $result->successful()) {
            throw new \RuntimeException(
                'Coqui TTS falhou. Instale: pip install TTS && defina COQUI_PYTHON no .env. '
                .$result->errorOutput()
            );
        }

        return [
            'audio_path' => $outputPath,
            'duration_seconds' => $this->ffmpeg->getAudioDuration($outputPath),
        ];
    }
}
