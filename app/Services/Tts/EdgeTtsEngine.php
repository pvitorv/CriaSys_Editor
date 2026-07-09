<?php

namespace App\Services\Tts;

use App\Support\NodeBinary;
use App\Support\Utf8;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class EdgeTtsEngine implements TtsEngineInterface
{
    public function synthesize(string $text, string $voice, string $outputPath): array
    {
        File::ensureDirectoryExists(dirname($outputPath));

        $node = NodeBinary::path();
        $script = base_path('scripts/generate-tts.mjs');
        $outputPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outputPath);
        $textFile = dirname($outputPath).DIRECTORY_SEPARATOR.'tts_input_'.uniqid().'.txt';
        File::put($textFile, Utf8::clean($text) ?? '');

        $command = [$node, $script, '--voice', $voice, '--input', $textFile, '--output', $outputPath];
        $result = Process::timeout(120)
            ->path(base_path())
            ->run($command);

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            // Node pode ter gravado via path.resolve (slashes normalizados)
            $resolved = dirname($outputPath).DIRECTORY_SEPARATOR.basename($outputPath);
            if ($resolved !== $outputPath && file_exists($resolved) && filesize($resolved) > 0) {
                $outputPath = $resolved;
            }
        }

        if (file_exists($outputPath) && filesize($outputPath) > 0) {
            File::delete($textFile);

            return [
                'audio_path' => $outputPath,
                'duration_seconds' => $this->probeDuration($outputPath),
            ];
        }

        File::delete($textFile);

        $detail = Utf8::clean(trim($result->errorOutput() ?: $result->output()));
        if ($detail === '') {
            $detail = 'exit='.$result->exitCode().', node='.$node;
        }

        throw new \RuntimeException(
            'Edge TTS indisponível. Defina NODE_PATH no .env (ex.: C:\\Program Files\\nodejs\\node.exe). Detalhe: '.$detail
        );
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
