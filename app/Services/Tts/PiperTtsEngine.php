<?php

namespace App\Services\Tts;

use App\Services\Render\FfmpegRenderService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Piper — TTS local gratuito, boa qualidade em pt-BR.
 * Baixe piper.exe + modelo pt_BR em bin/piper/ (ver docs/DESENVOLVIMENTO.md).
 */
class PiperTtsEngine implements TtsEngineInterface
{
    public function __construct(private FfmpegRenderService $ffmpeg) {}

    public function synthesize(string $text, string $voice, string $outputPath): array
    {
        $factory = app(TtsEngineFactory::class);
        $piper = $factory->absPath((string) config('criasys.tts.piper_path'));
        $preset = $this->resolveVoice($voice);
        $model = $factory->absPath($preset['model']);

        if (! $piper || ! is_file($piper)) {
            throw new \RuntimeException('Piper não encontrado. Defina PIPER_PATH no .env ou rode scripts/setup-piper.ps1');
        }

        if (! $model || ! is_file($model)) {
            throw new \RuntimeException("Modelo Piper não encontrado: {$model}");
        }

        File::ensureDirectoryExists(dirname($outputPath));

        $wavPath = preg_replace('/\.mp3$/i', '.wav', $outputPath) ?: $outputPath.'.wav';

        $args = [
            basename($piper),
            '--model', $model,
            '--output_file', $wavPath,
        ];

        if (isset($preset['length_scale'])) {
            $args[] = '--length_scale';
            $args[] = (string) $preset['length_scale'];
        }
        if (isset($preset['sentence_silence'])) {
            $args[] = '--sentence_silence';
            $args[] = (string) $preset['sentence_silence'];
        }
        if (isset($preset['noise_scale'])) {
            $args[] = '--noise_scale';
            $args[] = (string) $preset['noise_scale'];
        }

        $result = Process::timeout(180)
            ->path(dirname($piper))
            ->input($this->normalizeText($text))
            ->run($args);

        if (! $result->successful() || ! is_file($wavPath)) {
            throw new \RuntimeException(
                'Piper TTS falhou: '.trim($result->errorOutput() ?: $result->output())
            );
        }

        $ffmpeg = config('criasys.ffmpeg_path');
        $convert = Process::timeout(60)->run([
            $ffmpeg, '-y', '-i', $wavPath, '-codec:a', 'libmp3lame', '-q:a', '2', $outputPath,
        ]);

        if (is_file($wavPath)) {
            File::delete($wavPath);
        }

        if (! $convert->successful() || ! is_file($outputPath)) {
            throw new \RuntimeException('Falha ao converter WAV Piper para MP3.');
        }

        return [
            'audio_path' => $outputPath,
            'duration_seconds' => $this->ffmpeg->getAudioDuration($outputPath),
        ];
    }

    /**
     * @return array{model: string, length_scale?: float, sentence_silence?: float, noise_scale?: float}
     */
    private function resolveVoice(string $voice): array
    {
        $voices = config('criasys.tts.piper_voices', []);
        if ($voice && isset($voices[$voice]['model'])) {
            return $voices[$voice];
        }

        $models = config('criasys.tts.piper_models', []);
        if ($voice && $voice !== 'default' && isset($models[$voice])) {
            return ['model' => $models[$voice]];
        }

        return ['model' => (string) config('criasys.tts.piper_model')];
    }

    /**
     * Reforça pausas em pontuação forte para leitura mais dramática,
     * mantendo o texto legível pelo espeak-ng do Piper.
     */
    private function normalizeText(string $text): string
    {
        $text = trim($text);
        // Garante espaço após pontuação para o phonemizer segmentar frases.
        $text = preg_replace('/([.!?;:])(?=\S)/u', '$1 ', $text) ?? $text;

        return $text;
    }
}
