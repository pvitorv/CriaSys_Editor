<?php

namespace App\Services;

use App\Models\Narration;
use App\Models\Project;
use App\Models\Slide;
use App\Services\Render\FfmpegRenderService;
use App\Services\Script\ScriptParser;
use App\Services\Tts\TtsEngineFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class NarrationService
{
    public function __construct(
        private ProjectStorageService $storage,
        private FfmpegRenderService $ffmpeg,
        private TtsEngineFactory $ttsFactory,
        private ScriptParser $scriptParser,
    ) {}

    /**
     * Gera áudio de teste (slide ou trecho) sem salvar narração completa.
     *
     * @return array{audio_path: string, duration_seconds: float}
     */
    public function previewText(Project $project, string $text, string $voice, ?string $engine = null): array
    {
        $this->storage->ensureStructure($project);
        $path = $this->storage->audioPath($project, 'preview_'.uniqid().'.mp3');

        return $this->ttsFactory->synthesizeWithFallback(
            $this->scriptParser->formatNarrationText(trim($text)),
            $voice,
            $path,
            $engine
        );
    }

    public function hasNarratableSlides(Project $project, ?array $slideIds = null): bool
    {
        foreach ($this->slidesForNarration($project, $slideIds) as $slide) {
            if ($this->slideNarrationSource($slide) !== '') {
                return true;
            }
        }

        return false;
    }

    public function generate(
        Project $project,
        string $voice,
        ?string $engine = null,
        ?array $slideIds = null,
        ?Narration $narration = null,
    ): Narration {
        @set_time_limit(0);

        $engineSlug = $engine ?? config('criasys.tts.default_engine');

        $this->storage->ensureStructure($project);
        $project->load('slides');

        $slides = $this->slidesForNarration($project, $slideIds);
        if ($slides->isEmpty()) {
            throw new \RuntimeException('Nenhum slide selecionado para narração.');
        }

        $narration ??= Narration::create([
            'project_id' => $project->id,
            'engine' => $engineSlug,
            'voice' => $voice,
            'status' => 'processing',
            'segments' => [],
        ]);

        if ($narration->status !== 'processing') {
            $narration->update([
                'engine' => $engineSlug,
                'voice' => $voice,
                'status' => 'processing',
                'segments' => [],
                'audio_path' => null,
                'duration_seconds' => null,
                'full_script' => null,
            ]);
        }

        $engineUsed = $engineSlug;

        try {
            $segments = [];
            $fullScript = '';

            foreach ($slides as $index => $slide) {
                $text = $this->slideNarrationSource($slide);

                if ($text === '') {
                    continue;
                }

                $text = $this->scriptParser->formatNarrationText($text);

                $fullScript .= ($fullScript ? "\n\n" : '').$text;
                $segmentBase = $this->storage->audioPath($project, "segment_{$narration->id}_{$slide->id}");
                $result = $this->ttsFactory->synthesizeWithFallback($text, $voice, $segmentBase.'.mp3', $engineSlug);
                $engineUsed = $result['engine'] ?? $engineUsed;

                $segments[] = [
                    'slide_id' => $slide->id,
                    'slide_order' => $slide->order,
                    'text' => $text,
                    'audio_path' => $result['audio_path'],
                    'duration_seconds' => $result['duration_seconds'],
                ];
                $narration->update(['segments' => $segments]);
            }

            if ($segments === []) {
                throw new \RuntimeException('Nenhum slide selecionado possui texto para narração.');
            }

            $outputPath = $this->storage->audioPath($project, "narracao_{$narration->id}.mp3");
            $segments = $this->alignNarrationToTimeline($project, $segments, $slideIds, $outputPath);
            $duration = $this->ffmpeg->getAudioDuration($outputPath);

            $narration->update([
                'full_script' => $fullScript,
                'audio_path' => $outputPath,
                'duration_seconds' => $duration,
                'segments' => $segments,
                'status' => 'completed',
                'engine' => $engineUsed,
            ]);
        } catch (\Throwable $e) {
            $narration->update([
                'status' => 'failed',
                'segments' => array_merge($narration->segments ?? [], [
                    '_error' => $e->getMessage(),
                ]),
            ]);
            throw $e;
        }

        return $narration->fresh();
    }

    public function syncSlideDurations(Project $project, ?Narration $narration = null): void
    {
        $narration ??= $project->latestNarration();
        if (! $narration?->segments) {
            throw new \RuntimeException('Narração sem segmentos para sincronizar.');
        }

        DB::transaction(function () use ($narration) {
            foreach ($narration->segments as $segment) {
                if (! is_array($segment) || empty($segment['slide_id'])) {
                    continue;
                }
                Slide::where('id', $segment['slide_id'])->update([
                    'duration_seconds' => max(0.5, (float) ($segment['duration_seconds'] ?? 0)),
                    'duration_mode' => 'manual',
                ]);
            }
        });
    }

    /**
     * Um arquivo só, do tamanho da linha do tempo. Cada slide falado ocupa
     * o maior entre o tempo que você planejou e o tempo que a voz levou.
     * O que sobra fica em silêncio no fim do slide. Slide sem fala vira
     * silêncio do tamanho dele, para a voz não adiantar a imagem.
     *
     * @param  list<array<string, mixed>>  $segments
     * @param  list<int>|null  $slideIds
     * @return list<array<string, mixed>>
     */
    private function alignNarrationToTimeline(Project $project, array $segments, ?array $slideIds, string $outputPath): array
    {
        $bySlide = [];
        foreach ($segments as $segment) {
            if (is_array($segment) && ! empty($segment['slide_id'])) {
                $bySlide[(int) $segment['slide_id']] = $segment;
            }
        }

        $selected = null;
        if ($slideIds !== null && $slideIds !== []) {
            $selected = array_flip(array_map('intval', $slideIds));
        }

        $ffmpeg = $this->ttsFactory->resolveFfmpegPath();
        $dir = dirname($outputPath);
        $files = [];
        $temps = [];
        $stored = [];
        $updates = [];

        $project->load('slides');

        foreach ($project->slides->sortBy('order') as $slide) {
            $planned = max(0.5, (float) ($slide->duration_seconds ?: 5));
            $inSelection = $selected === null || isset($selected[$slide->id]);
            $segment = $bySlide[$slide->id] ?? null;
            $speech = is_array($segment) ? ($segment['audio_path'] ?? null) : null;

            if ($inSelection && is_string($speech) && is_file($speech)) {
                $spoken = max(0.1, (float) ($segment['duration_seconds'] ?? 0));
                $hold = round(max($planned, $spoken), 2);
                $files[] = $speech;
                $pad = round($hold - $spoken, 2);
                if ($pad >= 0.12) {
                    $silence = $this->makeSilence($dir, $pad, $ffmpeg);
                    $files[] = $silence;
                    $temps[] = $silence;
                }
                $updates[] = ['id' => $slide->id, 'duration_seconds' => $hold];
                $stored[] = [
                    'slide_id' => $slide->id,
                    'slide_order' => $slide->order,
                    'text' => $segment['text'] ?? '',
                    'audio_path' => $speech,
                    'duration_seconds' => $hold,
                    'spoken_seconds' => round($spoken, 2),
                ];

                continue;
            }

            $silence = $this->makeSilence($dir, round($planned, 2), $ffmpeg);
            $files[] = $silence;
            $temps[] = $silence;
        }

        try {
            $this->concatAudioReencode($files, $outputPath, $ffmpeg);
        } finally {
            foreach ($temps as $temp) {
                if (is_file($temp)) {
                    File::delete($temp);
                }
            }
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $update) {
                Slide::where('id', $update['id'])->update([
                    'duration_seconds' => $update['duration_seconds'],
                    'duration_mode' => 'manual',
                ]);
            }
        });

        return $stored;
    }

    private function makeSilence(string $dir, float $seconds, string $ffmpeg): string
    {
        $seconds = round(max(0.1, $seconds), 2);
        $path = $dir.DIRECTORY_SEPARATOR.'silencio_'.str_replace('.', 'p', (string) $seconds).'_'.uniqid('', false).'.mp3';

        $result = Process::timeout(60)->run([
            $ffmpeg, '-y',
            '-f', 'lavfi',
            '-i', 'anullsrc=r=24000:cl=mono',
            '-t', number_format($seconds, 2, '.', ''),
            '-c:a', 'libmp3lame', '-q:a', '4',
            $path,
        ]);

        if (! $result->successful() || ! is_file($path) || filesize($path) === 0) {
            throw new \RuntimeException(
                'Falha ao gerar a pausa entre slides: '.trim($result->errorOutput() ?: $result->output())
            );
        }

        return $path;
    }

    private function concatAudio(array $files, string $outputPath): void
    {
        File::ensureDirectoryExists(dirname($outputPath));

        if ($files === []) {
            throw new \RuntimeException('Nenhum segmento de áudio para concatenar.');
        }

        $ffmpeg = $this->ttsFactory->resolveFfmpegPath();
        $normalized = [];

        foreach ($files as $index => $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext === 'mp3') {
                $normalized[] = $file;

                continue;
            }

            $tempMp3 = dirname($outputPath).DIRECTORY_SEPARATOR."seg_norm_{$index}_".uniqid().'.mp3';
            $this->transcodeToMp3($file, $tempMp3, $ffmpeg);
            $normalized[] = $tempMp3;
        }

        if (count($normalized) === 1) {
            File::copy($normalized[0], $outputPath);
            $this->cleanupTempSegments($normalized, $files);

            return;
        }

        $listPath = dirname($outputPath).DIRECTORY_SEPARATOR.'concat_audio_'.uniqid().'.txt';
        $lines = array_map(fn ($f) => "file '".str_replace("'", "'\\''", $f)."'", $normalized);
        File::put($listPath, implode(PHP_EOL, $lines));

        $result = Process::timeout(300)->run([
            $ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $listPath,
            '-c', 'copy', $outputPath,
        ]);

        File::delete($listPath);

        if (! $result->successful() || ! is_file($outputPath)) {
            $this->concatAudioReencode($normalized, $outputPath, $ffmpeg);
        }

        $this->cleanupTempSegments($normalized, $files);
    }

    /** @param  list<string>  $normalized  @param  list<string>  $original */
    private function cleanupTempSegments(array $normalized, array $original): void
    {
        foreach ($normalized as $path) {
            if (! in_array($path, $original, true) && is_file($path)) {
                File::delete($path);
            }
        }
    }

    /** @param  list<string>  $files */
    private function concatAudioReencode(array $files, string $outputPath, string $ffmpeg): void
    {
        if ($files === []) {
            throw new \RuntimeException('Nenhum segmento de áudio para concatenar.');
        }

        $listPath = dirname($outputPath).DIRECTORY_SEPARATOR.'concat_audio_'.uniqid().'.txt';
        $lines = array_map(function (string $file) {
            $normalized = str_replace('\\', '/', $file);

            return "file '".str_replace("'", "'\\''", $normalized)."'";
        }, $files);
        File::put($listPath, implode(PHP_EOL, $lines));

        $result = Process::timeout(600)->run([
            $ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $listPath,
            '-c:a', 'libmp3lame', '-q:a', '2',
            $outputPath,
        ]);

        File::delete($listPath);

        if (! $result->successful() || ! is_file($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException(
                'Falha ao concatenar áudio: '.trim($result->errorOutput() ?: $result->output())
            );
        }
    }

    private function transcodeToMp3(string $input, string $output, string $ffmpeg): void
    {
        $result = Process::timeout(120)->run([
            $ffmpeg, '-y', '-i', $input, '-codec:a', 'libmp3lame', '-q:a', '2', $output,
        ]);

        if (! $result->successful() || ! is_file($output)) {
            throw new \RuntimeException(
                'Falha ao converter áudio para MP3: '.trim($result->errorOutput() ?: $result->output())
            );
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Slide>
     */
    private function slidesForNarration(Project $project, ?array $slideIds)
    {
        $slides = $project->slides->sortBy('order')->values();

        if ($slideIds === null || $slideIds === []) {
            return $slides;
        }

        $allowed = array_flip(array_map('intval', $slideIds));

        return $slides->filter(fn (Slide $slide) => isset($allowed[$slide->id]))->values();
    }

    private function slideNarrationSource(Slide $slide): string
    {
        $text = trim($slide->narration_text ?? '');
        if ($text !== '') {
            return $text;
        }

        $text = trim($slide->body_text ?? '');
        if ($text !== '') {
            return $text;
        }

        return trim(collect([$slide->title, $slide->subtitle])->filter()->implode('. '));
    }
}
