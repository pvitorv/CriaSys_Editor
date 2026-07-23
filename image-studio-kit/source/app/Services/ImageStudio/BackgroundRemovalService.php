<?php

namespace App\Services\ImageStudio;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackgroundRemovalService
{
    public function isAvailable(): bool
    {
        $python = $this->pythonBinary();

        if (! $python) {
            return false;
        }

        $check = Process::timeout(15)->run([$python, '-c', 'import rembg']);

        return $check->successful();
    }

    public function remove(string $inputPath, string $outputPath): void
    {
        if (! file_exists($inputPath)) {
            throw new \InvalidArgumentException('Arquivo de entrada não encontrado.');
        }

        File::ensureDirectoryExists(dirname($outputPath));

        $python = $this->pythonBinary();
        if (! $python) {
            throw new \RuntimeException('Python não encontrado. Instale Python 3 e rode: pip install rembg pillow');
        }

        $script = base_path('scripts/remove-background.py');
        $result = Process::timeout(120)->run([$python, $script, $inputPath, $outputPath]);

        if (! $result->successful() || ! file_exists($outputPath)) {
            $msg = trim($result->errorOutput() ?: $result->output()) ?: 'Falha ao remover fundo.';
            throw new \RuntimeException($msg);
        }
    }

    private function pythonBinary(): ?string
    {
        foreach ([
            config('criasys.image_studio.rembg_python'),
            config('criasys.tts.edge_python'),
            'python3',
            'python',
        ] as $candidate) {
            if (! $candidate) {
                continue;
            }
            $probe = Process::timeout(5)->run([$candidate, '--version']);
            if ($probe->successful()) {
                return $candidate;
            }
        }

        return null;
    }
}
