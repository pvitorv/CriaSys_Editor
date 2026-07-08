<?php

namespace App\Services;

use App\Models\Narration;
use App\Models\Project;
use App\Models\Slide;
use App\Services\Render\FfmpegRenderService;
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
    ) {}

    public function generate(Project $project, string $voice, ?string $engine = null): Narration
    {
        $engineSlug = $engine ?? config('criasys.tts.default_engine');

        if (! $this->ttsFactory->isAvailable($engineSlug)) {
            throw new \RuntimeException("Motor TTS '{$engineSlug}' indisponível neste ambiente.");
        }

        $this->storage->ensureStructure($project);
        $project->load('slides');

        $narration = Narration::create([
            'project_id' => $project->id,
            'engine' => $engineSlug,
            'voice' => $voice,
            'status' => 'processing',
        ]);

        $tts = $this->ttsFactory->resolve($engineSlug);

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

                $fullScript .= ($fullScript ? "\n\n" : '').$text;
                $segmentPath = $this->storage->audioPath($project, "segment_{$narration->id}_{$index}.mp3");
                $result = $tts->synthesize($text, $voice, $segmentPath);

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
            ]);
        } catch (\Throwable $e) {
            $narration->update(['status' => 'failed']);
            throw $e;
        }

        return $narration->fresh();
    }
