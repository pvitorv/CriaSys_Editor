<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class TtsNodeRunner
{
    public function synthesize(string $text, string $voice, string $outputPath): void
    {
        $outputPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outputPath);
        File::ensureDirectoryExists(dirname($outputPath));

        $node = NodeBinary::path();
        $script = $this->resolveScript();
        $textFile = storage_path('app/tts/tts_input_'.uniqid('in_', false).'.txt');
        File::ensureDirectoryExists(dirname($textFile));
        File::put($textFile, Utf8::clean($text) ?? '');

        if (trim(File::get($textFile)) === '') {
            File::delete($textFile);
            throw new \RuntimeException('Texto de narração vazio.');
        }

        $tempOutput = storage_path('app/tts/tts_out_'.uniqid('out_', false).'.mp3');
        $tempOutput = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $tempOutput);

        $result = Process::timeout(120)
            ->path(base_path())
            ->run([$node, $script, '--voice', $voice, '--input', $textFile, '--output', $tempOutput]);

        $detail = Utf8::clean(trim($result->errorOutput() ?: $result->output()));

        if (! file_exists($tempOutput) || filesize($tempOutput) === 0) {
            File::delete($textFile);
            @File::delete($tempOutput);

            throw new \RuntimeException(
                'Node TTS falhou. '.($detail ?: 'exit='.$result->exitCode())
            );
        }

        File::copy($tempOutput, $outputPath);
        File::delete($textFile);
        File::delete($tempOutput);

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException('Não foi possível salvar áudio em: '.$outputPath);
        }
    }

    private function resolveScript(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $launch = base_path('scripts/generate-tts-launch.cjs');
            if (file_exists($launch)) {
                return $launch;
            }
        }

        return base_path('scripts/generate-tts.cjs');
    }
}
