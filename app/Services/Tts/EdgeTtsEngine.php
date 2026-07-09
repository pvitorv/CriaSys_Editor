<?php

namespace App\Services\Tts;

use App\Support\NodeBinary;
use App\Support\Utf8;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class EdgeTtsEngine implements TtsEngineInterface
{
    public function synthesize(string $text, string $voice, string $outputPath): array
    {
        $outputPath = $this->normalizePath($outputPath);
        File::ensureDirectoryExists(dirname($outputPath));

        $node = NodeBinary::path();
        $script = $this->resolveScript();
        $workDir = base_path();

        $textFile = $this->normalizePath(storage_path('app/tts/tts_input_'.uniqid('in_', false).'.txt'));
        File::ensureDirectoryExists(dirname($textFile));
        File::put($textFile, Utf8::clean($text) ?? '');

        if (trim(File::get($textFile)) === '') {
            File::delete($textFile);
            throw new \RuntimeException('Texto de narração vazio após sanitização.');
        }

        $tempOutput = $this->normalizePath(storage_path('app/tts/tts_out_'.uniqid('out_', false).'.mp3'));

        $command = [$node, $script, '--voice', $voice, '--input', $textFile, '--output', $tempOutput];

        $result = Process::timeout(120)
            ->path($workDir)
            ->run($command);

        $this->waitForOutputFile($tempOutput, 45);

        $detail = Utf8::clean(trim($result->errorOutput() ?: $result->output()));

        if (! file_exists($tempOutput) || filesize($tempOutput) === 0) {
            Log::warning('Edge TTS falhou', [
                'exit' => $result->exitCode(),
                'node' => $node,
                'script' => $script,
                'text_file' => $textFile,
                'temp_output' => $tempOutput,
                'detail' => $detail,
            ]);

            File::delete($textFile);
            @File::delete($tempOutput);

            throw new \RuntimeException(
                'Falha ao gerar áudio (Edge TTS). '
                .($detail ?: 'exit='.$result->exitCode())
            );
        }

        File::copy($tempOutput, $outputPath);
        File::delete($textFile);
        File::delete($tempOutput);

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException('Áudio gerado mas não foi possível salvar em: '.$outputPath);
        }

        return [
            'audio_path' => $outputPath,
            'duration_seconds' => $this->probeDuration($outputPath),
        ];
    }

    private function resolveScript(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $launch = base_path('scripts/generate-tts-launch.cjs');
            if (file_exists($launch)) {
                return $launch;
            }
        }

        $cjs = base_path('scripts/generate-tts.cjs');
        if (file_exists($cjs)) {
            return $cjs;
        }

        return base_path('scripts/generate-tts.mjs');
    }

    private function waitForOutputFile(string $path, int $maxSeconds = 30): void
    {
        for ($i = 0; $i < $maxSeconds * 10; $i++) {
            if (file_exists($path) && filesize($path) > 0) {
                return;
            }
            usleep(100000);
        }
    }

    private function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
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
