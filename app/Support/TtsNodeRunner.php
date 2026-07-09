<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class TtsNodeRunner
{
    public function synthesize(string $text, string $voice, string $outputPath): void
    {
        @set_time_limit(0);

        $outputPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outputPath);
        File::ensureDirectoryExists(dirname($outputPath));

        $text = Utf8::clean($text) ?? '';
        if (trim($text) === '') {
            throw new \RuntimeException('Texto de narração vazio.');
        }

        $node = NodeBinary::path();
        $script = base_path('scripts/generate-tts.cjs');

        $tmpDir = storage_path('framework/process-tmp');
        File::ensureDirectoryExists($tmpDir);

        $textFile = $tmpDir.DIRECTORY_SEPARATOR.'tts_in_'.uniqid('', false).'.txt';
        $tempOutput = $tmpDir.DIRECTORY_SEPARATOR.'tts_out_'.uniqid('', false).'.mp3';
        File::put($textFile, $text);

        $env = [
            'TEMP' => $tmpDir,
            'TMP' => $tmpDir,
            'TMPDIR' => $tmpDir,
        ];

        // php artisan serve sobrescreve $_SERVER com dados do request; sem SystemRoot/windir
        // o WinSock/TLS do Node trava ao resolver DNS. Repassamos o ambiente real do SO.
        foreach ([
            'SystemRoot', 'windir', 'SystemDrive', 'PATH', 'PATHEXT',
            'APPDATA', 'LOCALAPPDATA', 'USERPROFILE', 'HOMEDRIVE', 'HOMEPATH',
            'ProgramData', 'ProgramFiles', 'ProgramFiles(x86)', 'CommonProgramFiles',
            'NUMBER_OF_PROCESSORS', 'PROCESSOR_ARCHITECTURE', 'COMPUTERNAME', 'USERNAME',
        ] as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== '') {
                $env[$key] = $value;
            }
        }

        $env['FFMPEG_PATH'] = config('criasys.ffmpeg_path', 'ffmpeg');
        if ($dbg = getenv('TTS_DEBUG_LOG')) {
            $env['TTS_DEBUG_LOG'] = $dbg;
        }

        try {
            $result = Process::timeout(180)
                ->path(base_path())
                ->env($env)
                ->run([
                    $node,
                    $script,
                    '--voice', $voice,
                    '--input', $textFile,
                    '--output', $tempOutput,
                ]);

            $stdout = trim($result->output());
            $stderr = trim($result->errorOutput());
            $exists = file_exists($tempOutput);
            $size = $exists ? filesize($tempOutput) : 0;

            Log::info('TTS node run', [
                'context' => app()->runningInConsole() ? 'cli' : 'web',
                'node' => $node,
                'exit' => $result->exitCode(),
                'stdout' => $stdout,
                'stderr' => $stderr,
                'file_exists' => $exists,
                'size' => $size,
            ]);

            if (! $exists || $size === 0) {
                $detail = $stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'exit='.$result->exitCode());

                if (app(TtsPythonRunner::class)->isAvailable()) {
                    try {
                        app(TtsPythonRunner::class)->synthesize($text, $voice, $outputPath);

                        return;
                    } catch (\Throwable $pyError) {
                        $detail .= ' | Fallback Python: '.$pyError->getMessage();
                    }
                }

                throw new \RuntimeException($this->friendlyEdgeError($detail));
            }

            File::copy($tempOutput, $outputPath);
        } finally {
            @File::delete($textFile);
            @File::delete($tempOutput);
        }
    }

    private function friendlyEdgeError(string $detail): string
    {
        if (stripos($detail, 'Output has been disabled') !== false || stripos($detail, 'No audio was received') !== false) {
            return 'Microsoft bloqueou o Edge TTS. O sistema tentará Piper automaticamente — selecione Piper no motor TTS.';
        }

        return 'Edge TTS falhou. '.$detail;
    }
}
