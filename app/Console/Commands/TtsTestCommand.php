<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Tts\EdgeTtsEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TtsTestCommand extends Command
{
    protected $signature = 'tts:test {--project=}';

    protected $description = 'Testa geração de áudio Edge TTS neste PC';

    public function handle(EdgeTtsEngine $engine): int
    {
        $projectId = $this->option('project') ?: Project::query()->value('id');

        if (! $projectId) {
            $this->error('Nenhum projeto encontrado.');

            return self::FAILURE;
        }

        $path = storage_path('app/tts/test_'.uniqid('', true).'.mp3');
        File::ensureDirectoryExists(dirname($path));

        try {
            $result = $engine->synthesize(
                'Teste de voz do CriaSys Editor.',
                config('criasys.tts.default_voice'),
                $path
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $bytes = filesize($result['audio_path']);
        $this->info("OK: {$bytes} bytes, {$result['duration_seconds']}s");
        $this->line($result['audio_path']);

        return self::SUCCESS;
    }
}
