<?php

namespace App\Support;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ProcessRunner
{
    public static function run(array $command, ?string $cwd = null, int $timeout = 120): ProcessResult
    {
        return Process::timeout($timeout)
            ->path($cwd ?? base_path())
            ->quietly()
            ->run($command);
    }

    /**
     * Windows: cmd /c aguarda o Node terminar de verdade (evita exit=0 sem MP3).
     */
    public static function runWindowsBatch(string $batchContent, int $timeout = 120): ProcessResult
    {
        $batch = storage_path('framework/process-tmp/run_'.uniqid('', false).'.cmd');
        File::ensureDirectoryExists(dirname($batch));
        File::put($batch, $batchContent);

        try {
            return Process::timeout($timeout)
                ->path(base_path())
                ->quietly()
                ->run(['cmd.exe', '/c', $batch]);
        } finally {
            @File::delete($batch);
        }
    }
}
