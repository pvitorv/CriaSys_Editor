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
            $segmentFiles = [];
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
                $segmentFiles[] = $result['audio_path'];

                $narration->update(['segments' => $segments]);
            }

            if ($segmentFiles === []) {
                throw new \RuntimeException('Nenhum slide selecionado possui texto para narração.');
            }

            $outputPath = $this->storage->audioPath($project, "narracao_{$narration->id}.mp3");
            $this->concatAudio($segmentFiles, $outputPath);
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
                ]);
            }
        });
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
        $args = [$ffmpeg, '-y'];
        foreach ($files as $file) {
            $args[] = '-i';
            $args[] = $file;
        }

        $args = array_merge($args, [
            '-filter_complex', 'concat=n='.count($files).':v=0:a=1[out]',
            '-map', '[out]',
            '-c:a', 'libmp3lame', '-q:a', '2',
            $outputPath,
        ]);

        $result = Process::timeout(300)->run($args);

        if (! $result->successful() || ! is_file($outputPath)) {
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
