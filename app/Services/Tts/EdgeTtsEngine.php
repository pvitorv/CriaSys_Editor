<?php

namespace App\Services\Tts;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class EdgeTtsEngine implements TtsEngineInterface
{
    public function synthesize(string $text, string $voice, string $outputPath): array
    {
        File::ensureDirectoryExists(dirname($outputPath));

        $script = base_path('scripts/generate-tts.mjs');
        $textFile = dirname($outputPath).DIRECTORY_SEPARATOR.'tts_input_'.uniqid().'.txt';
        File::put($textFile, $text);

        $commands = [
            ['node', $script, '--voice', $voice, '--input', $textFile, '--output', $outputPath],
            ['python', '-m', 'edge_tts', '--voice', $voice, '--file', $textFile, '--write-media', $outputPath],
            ['edge-tts', '--voice', $voice, '--file', $textFile, '--write-media', $outputPath],
        ];

        $lastError = null;

        foreach ($commands as $command) {
            $result = Process::timeout(120)->run($command);
            if ($result->successful() && file_exists($outputPath) && filesize($outputPath) > 0) {
                File::delete($textFile);
                $duration = $this->probeDuration($outputPath);

                return [
                    'audio_path' => $outputPath,
                    'duration_seconds' => $duration,
                ];
            }
            $lastError = $result->errorOutput() ?: $result->output();
        }

        File::delete($textFile);

        throw new \RuntimeException(
            'Edge TTS indisponível. Instale Python edge-tts (`pip install edge-tts`) ou Node. Erro: '.$lastError
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
