<?php

namespace App\Jobs;

use App\Enums\RenderStatus;
use App\Models\ExportPreset;
use App\Models\RenderJob;
use App\Services\Render\FfmpegRenderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RenderVideoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $renderJobId,
        public bool $generateThumb = false,
    ) {}

    public function handle(FfmpegRenderService $ffmpeg): void
    {
        $job = RenderJob::with(['project.slides', 'project.audioTracks'])->findOrFail($this->renderJobId);
        $preset = ExportPreset::where('slug', $job->preset)->firstOrFail();

        $job->update([
            'status' => RenderStatus::Processing,
            'progress' => 5,
            'started_at' => now(),
            'error_log' => null,
        ]);

        try {
            $outputPath = $ffmpeg->render($job->project, $job, $preset);

            if ($this->generateThumb) {
                $ffmpeg->generateThumbnail($job->project, $preset);
            }

            $job->update([
                'status' => RenderStatus::Completed,
                'progress' => 100,
                'output_path' => $outputPath,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('RenderVideoJob failed', ['job' => $job->id, 'error' => $e->getMessage()]);
            $job->update([
                'status' => RenderStatus::Failed,
                'error_log' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
