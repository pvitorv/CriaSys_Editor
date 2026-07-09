<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class TtsPythonRunner
{
    public function synthesize(string $text, string $voice, string $outputPath): void
    {
        $python = $this->pythonBinary();
        if (! $python) {
            throw new \RuntimeException('Python com edge-tts não disponível.');
        }

        $script = base_path('scripts/generate-tts-edge.py');
        $tmpDir = storage_path('framework/process-tmp');
        File::ensureDirectoryExists($tmpDir);

        $textFile = $tmpDir.DIRECTORY_SEPARATOR.'tts_py_in_'.uniqid('', false).'.txt';
        $tempOutput = $tmpDir.DIRECTORY_SEPARATOR.'tts_py_out_'.uniqid('', false).'.mp3';
        File::put($textFile, Utf8::clean($text) ?? '');

        try {
            $result = Process::timeout(120)->run([
                $python, $script,
                '--voice', $voice,
                '--input', $textFile,
                '--output', $tempOutput,
            ]);

            if (! file_exists($tempOutput) || filesize($tempOutput) === 0) {
                $detail = trim($result->errorOutput() ?: $result->output()) ?: 'exit='.$result->exitCode();
                throw new \RuntimeException('Python edge-tts falhou. '.$detail);
            }

            File::copy($tempOutput, $outputPath);
        } finally {
            @File::delete($textFile);
            @File::delete($tempOutput);
        }
    }

    public function isAvailable(): bool
    {
        return $this->pythonBinary() !== null;
    }

    private function pythonBinary(): ?string
    {
        $candidates = array_filter([
            config('criasys.tts.edge_python'),
            env('PYTHON_PATH'),
            $this->laragonPython(),
            'py',
            'python3',
            'python',
        ]);

        foreach ($candidates as $bin) {
            if ($this->hasEdgeTts($bin)) {
                return $bin;
            }
        }

        return null;
    }

    private function laragonPython(): ?string
    {
        $glob = 'C:/laragon/bin/python/python-*/python.exe';
        $matches = glob($glob);

        return $matches[0] ?? null;
    }

    private function hasEdgeTts(string $bin): bool
    {
        $result = Process::timeout(15)->run([$bin, '-c', 'import edge_tts']);

        return $result->successful();
    }
}
