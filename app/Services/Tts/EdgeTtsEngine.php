<?php

namespace App\Services\Tts;

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

        if (app()->runningInConsole()) {
            app(\App\Support\TtsNodeRunner::class)->synthesize($text, $voice, $outputPath);
        } else {
            $this->synthesizeViaArtisanCli($text, $voice, $outputPath);
        }

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException('Áudio não foi gerado.');
        }

        return [
            'audio_path' => $outputPath,
            'duration_seconds' => $this->probeDuration($outputPath),
        ];
    }

    private function synthesizeViaArtisanCli(string $text, string $voice, string $outputPath): void
    {
        $inputFile = storage_path('app/tts/web_in_'.uniqid('', false).'.txt');
        File::ensureDirectoryExists(dirname($inputFile));
        File::put($inputFile, $text);

        $php = PHP_BINARY;
        $artisan = base_path('artisan');

        $result = Process::timeout(120)
            ->path(base_path())
            ->run([
                $php,
                $artisan,
                'tts:generate',
                '--input='.$inputFile,
                '--output='.$outputPath,
                '--voice='.$voice,
            ]);

        File::delete($inputFile);

        if (! $result->successful() || ! file_exists($outputPath) || filesize($outputPath) === 0) {
            $detail = Utf8::clean(trim($result->errorOutput() ?: $result->output()));

            throw new \RuntimeException(
                'Falha ao gerar áudio (Edge TTS). '.($detail ?: 'exit='.$result->exitCode())
            );
        }
    }

    private function probeDuration(string $path): float
    {
        $ffprobe = config('criasys.ffprobe_path');
        $result = Process::timeout(30)->run([
            $ffprobe, '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $path,
        ]);

        if ($result->successful()) {
            return (float) trim($result->output());
        }

        return max(1, strlen(file_get_contents($path) ?: '') / 16000);
    }
}
