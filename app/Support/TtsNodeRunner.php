<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class TtsNodeRunner
{
    public function synthesize(string $text, string $voice, string $outputPath): void
    {
        $outputPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outputPath);
        File::ensureDirectoryExists(dirname($outputPath));

        $node = NodeBinary::path();
        $script = $this->resolveScript();
        $tmpDir = storage_path('framework/process-tmp');
        File::ensureDirectoryExists($tmpDir);

        $textFile = $tmpDir.DIRECTORY_SEPARATOR.'tts_in_'.uniqid('', false).'.txt';
        File::put($textFile, Utf8::clean($text) ?? '');

        if (trim(File::get($textFile)) === '') {
            File::delete($textFile);
            throw new \RuntimeException('Texto de narração vazio.');
        }

        $tempOutput = $tmpDir.DIRECTORY_SEPARATOR.'tts_out_'.uniqid('', false).'.mp3';

        if (PHP_OS_FAMILY === 'Windows') {
            $this->runOnWindows($node, $script, $voice, $textFile, $tempOutput, $tmpDir);
        } else {
            ProcessRunner::run([$node, $script, '--voice', $voice, '--input', $textFile, '--output', $tempOutput]);
        }

        $this->waitForFile($tempOutput, 60);

        if (! file_exists($tempOutput) || filesize($tempOutput) === 0) {
            File::delete($textFile);
            @File::delete($tempOutput);

            throw new \RuntimeException('Áudio não gerado pelo Node.');
        }

        File::copy($tempOutput, $outputPath);
        File::delete($textFile);
        File::delete($tempOutput);
    }

    private function runOnWindows(string $node, string $script, string $voice, string $textFile, string $tempOutput, string $tmpDir): void
    {
        $batch = implode("\r\n", [
            '@echo off',
            'setlocal',
            'set TEMP='.$tmpDir,
            'set TMP='.$tmpDir,
            'cd /d "'.base_path().'"',
            '"'.$node.'" "'.$script.'" --voice '.$voice.' --input "'.$textFile.'" --output "'.$tempOutput.'"',
            'exit /b %ERRORLEVEL%',
        ]);

        $result = ProcessRunner::runWindowsBatch($batch);

        if (! $result->successful() && (! file_exists($tempOutput) || filesize($tempOutput) === 0)) {
            throw new \RuntimeException('Node TTS falhou. exit='.$result->exitCode());
        }
    }

    private function waitForFile(string $path, int $maxSeconds): void
    {
        for ($i = 0; $i < $maxSeconds * 10; $i++) {
            if (file_exists($path) && filesize($path) > 0) {
                return;
            }
            usleep(100000);
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
