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

    public function generate(Project $project, string $voice, ?string $engine = null): Narration
    {
        $engineSlug = $engine ?? config('criasys.tts.default_engine');

        $this->storage->ensureStructure($project);
        $project->load('slides');

        $narration = Narration::create([
            'project_id' => $project->id,
            'engine' => $engineSlug,
            'voice' => $voice,
            'status' => 'processing',
        ]);

        $engineUsed = $engineSlug;

        try {
            $segments = [];
            $segmentFiles = [];
            $fullScript = '';

            foreach ($project->slides as $index => $slide) {
                $text = trim($slide->narration_text ?? '');
                if ($text === '') {
                    $text = trim(collect([$slide->title, $slide->subtitle, $slide->body_text])->filter()->implode('. '));
                }

                if ($text === '') {
                    continue;
                }

                $text = $this->scriptParser->formatNarrationText($text);

                $fullScript .= ($fullScript ? "\n\n" : '').$text;
                $segmentPath = $this->storage->audioPath($project, "segment_{$narration->id}_{$index}.mp3");
                $result = $this->ttsFactory->synthesizeWithFallback($text, $voice, $segmentPath, $engineSlug);
                $engineUsed = $result['engine'] ?? $engineUsed;

                $segments[] = [
                    'slide_id' => $slide->id,
                    'slide_order' => $slide->order,
                    'text' => $text,
                    'audio_path' => $result['audio_path'],
                    'duration_seconds' => $result['duration_seconds'],
                ];
                $segmentFiles[] = $result['audio_path'];
            }

            if (empty($segmentFiles)) {
                throw new \RuntimeException('Nenhum slide possui texto para narração.');
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
            $narration->update(['status' => 'failed']);
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
                Slide::where('id', $segment['slide_id'])->update([
                    'duration_seconds' => max(0.5, (float) $segment['duration_seconds']),
                ]);
            }
        });
    }

    private function concatAudio(array $files, string $outputPath): void
    {
        if (count($files) === 1) {
            File::copy($files[0], $outputPath);

            return;
        }

        $listPath = dirname($outputPath).DIRECTORY_SEPARATOR.'concat_audio_'.uniqid().'.txt';
        $lines = array_map(fn ($f) => "file '".str_replace("'", "'\\''", $f)."'", $files);
        File::put($listPath, implode(PHP_EOL, $lines));

        $ffmpeg = config('criasys.ffmpeg_path');
        $result = Process::timeout(120)->run([
            $ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $listPath,
            '-c', 'copy', $outputPath,
        ]);

        File::delete($listPath);

        if (! $result->successful()) {
            throw new \RuntimeException('Falha ao concatenar áudio: '.$result->errorOutput());
        }
    }
}
