<?php

namespace App\Services\Tts;

use App\Support\TtsNodeRunner;
use App\Support\Utf8;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class EdgeTtsEngine implements TtsEngineInterface
{
    public function synthesize(string $text, string $voice, string $outputPath): array
    {
        $outputPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outputPath);
        File::ensureDirectoryExists(dirname($outputPath));

        $text = Utf8::clean($text) ?? '';
        if (trim($text) === '') {
            throw new \RuntimeException('Texto de narração vazio.');
        }

        app(TtsNodeRunner::class)->synthesize($text, $voice, $outputPath);

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException('Áudio não foi gerado.');
        }

        return [
            'audio_path' => $outputPath,
            'duration_seconds' => $this->probeDuration($outputPath),
        ];
    }

    private function probeDuration(string $path): float
    {
        $ffprobe = config('criasys.ffprobe_path');
        $result = Process::timeout(30)->quietly()->run([
            $ffprobe, '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $path,
        ]);

        if ($result->successful()) {
            return (float) trim($result->output());
        }

        return max(1, strlen(file_get_contents($path) ?: '') / 16000);
    }
}
