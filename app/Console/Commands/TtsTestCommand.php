<?php

namespace App\Console\Commands;

use App\Support\TtsNodeRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TtsTestCommand extends Command
{
    protected $signature = 'tts:test {--project=}';

    protected $description = 'Testa geração de áudio Edge TTS neste PC';

    public function handle(TtsNodeRunner $runner): int
    {
        $path = storage_path('app/tts/test_'.uniqid('', true).'.mp3');
        File::ensureDirectoryExists(dirname($path));

        try {
            $runner->synthesize(
                'Teste de voz do CriaSys Editor.',
                config('criasys.tts.default_voice'),
                $path
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $bytes = filesize($path);
        $this->info("OK: {$bytes} bytes");
        $this->line($path);

        return self::SUCCESS;
    }
}
