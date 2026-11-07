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

        $preset = $this->preset($voice);
        app(TtsNodeRunner::class)->synthesize(
            $text,
            $preset['voice'],
            $outputPath,
            $preset['rate'],
            $preset['pitch'],
        );

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException('Áudio não foi gerado.');
        }

        return [
            'audio_path' => $outputPath,
            'duration_seconds' => $this->probeDuration($outputPath),
        ];
    }

    /**
     * @return array{voice: string, rate: string, pitch: string}
     */
    private function preset(string $id): array
    {
        $meta = config('criasys.tts.edge_voices.'.$id);
        if (is_array($meta)) {
            return [
                'voice' => (string) ($meta['voice'] ?? $id),
                'rate' => (string) ($meta['rate'] ?? '-8%'),
                'pitch' => (string) ($meta['pitch'] ?? '+0Hz'),
            ];
        }

        return [
            'voice' => $id,
            'rate' => '-8%',
            'pitch' => '+0Hz',
        ];
    }

    private function probeDuration(string $path): float
    {
        $ffprobe = app(TtsEngineFactory::class)->resolveFfprobePath();
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
