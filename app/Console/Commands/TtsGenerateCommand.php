<?php

namespace App\Console\Commands;

use App\Support\TtsNodeRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TtsGenerateCommand extends Command
{
    protected $signature = 'tts:generate {--input=} {--output=} {--voice=}';

    protected $description = 'Gera MP3 via Node (uso interno — chamado pelo servidor web)';

    public function handle(TtsNodeRunner $runner): int
    {
        $input = $this->option('input');
        $output = $this->option('output');
        $voice = $this->option('voice') ?: config('criasys.tts.default_voice');

        if (! $input || ! $output || ! file_exists($input)) {
            $this->error('Parâmetros --input e --output são obrigatórios.');

            return self::FAILURE;
        }

        $text = trim(File::get($input));
        if ($text === '') {
            $this->error('Arquivo de entrada vazio.');

            return self::FAILURE;
        }

        try {
            $runner->synthesize($text, $voice, $output);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $bytes = filesize($output);
        $this->line("OK:{$bytes}");

        return self::SUCCESS;
    }
}
