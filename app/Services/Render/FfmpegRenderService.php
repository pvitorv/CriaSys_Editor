<?php

namespace App\Services\Render;

use App\Models\ExportPreset;
use App\Models\Project;
use App\Models\RenderJob;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class FfmpegRenderService
{
    public function __construct(
        private ProjectStorageService $storage,
        private SlideshowBuilder $slideshowBuilder,
    ) {}

    public function render(Project $project, RenderJob $job, ExportPreset $preset): string
    {
        $this->storage->ensureStructure($project);
        $job->update(['progress' => 10, 'started_at' => now()]);

        $slidePaths = $this->slideshowBuilder->buildSlideImages($project, $preset);
        $durations = $project->slides->pluck('duration_seconds')->map(fn ($d) => (float) $d)->all();

        $exportDir = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'exports';
        $listPath = $exportDir.DIRECTORY_SEPARATOR.'concat_'.$job->id.'.txt';
        $outputPath = $this->storage->exportPath($project, "render_{$job->id}_{$preset->slug}.mp4");
        $tempVideo = $exportDir.DIRECTORY_SEPARATOR."temp_video_{$job->id}.mp4";

        $this->slideshowBuilder->buildConcatList($slidePaths, $durations, $listPath);
        $job->update(['progress' => 30]);

        $ffmpeg = config('criasys.ffmpeg_path');
        $result = Process::timeout(600)->run([
            $ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $listPath,
            '-vsync', 'vfr', '-pix_fmt', 'yuv420p',
            '-s', "{$preset->width}x{$preset->height}",
            $tempVideo,
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException('FFmpeg concat falhou: '.$result->errorOutput());
        }

        $job->update(['progress' => 60]);

        $narration = $project->latestNarration();
        if ($narration?->audio_path && file_exists($narration->audio_path)) {
            $result = Process::timeout(600)->run([
                $ffmpeg, '-y',
                '-i', $tempVideo,
                '-i', $narration->audio_path,
                '-c:v', 'copy',
                '-c:a', 'aac', '-b:a', '192k',
                '-shortest',
                $outputPath,
            ]);

            if (! $result->successful()) {
                throw new \RuntimeException('FFmpeg mix falhou: '.$result->errorOutput());
            }

            File::delete($tempVideo);
        } else {
            File::move($tempVideo, $outputPath);
        }

        $job->update(['progress' => 90]);

        File::delete($listPath);
        foreach ($slidePaths as $path) {
            if (file_exists($path)) {
                @File::delete($path);
            }
        }

        return $outputPath;
    }

    public function generateThumbnail(Project $project, ExportPreset $preset, ?int $slideIndex = 0): string
    {
        $this->storage->ensureStructure($project);
        $slide = $project->slides[$slideIndex] ?? $project->slides->first();

        if (! $slide) {
            throw new \RuntimeException('Projeto sem slides para gerar thumbnail.');
        }

        $thumbPreset = ExportPreset::where('slug', 'thumbnail')->first() ?? $preset;
        $outputPath = $this->storage->thumbPath($project);

        app(SlideImageRenderer::class)->render($slide, $thumbPreset, $outputPath);

        return $outputPath;
    }

    public function getAudioDuration(string $audioPath): float
    {
        $ffprobe = config('criasys.ffprobe_path');
        $result = Process::timeout(30)->run([
            $ffprobe, '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $audioPath,
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException('FFprobe falhou: '.$result->errorOutput());
        }

        return (float) trim($result->output());
    }
}
