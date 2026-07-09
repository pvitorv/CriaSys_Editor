<?php

namespace App\Services\Render;

use App\Models\ExportPreset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class SlideshowBuilder
{
    public function __construct(
        private ProjectStorageService $storage,
        private SlideImageRenderer $renderer,
    ) {}

    /**
     * @return array<int, string> slide index => png path
     */
    public function buildSlideImages(Project $project, ExportPreset $preset): array
    {
        $this->storage->ensureStructure($project);
        $paths = [];
        $tempDir = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'exports'.DIRECTORY_SEPARATOR.'render_'.time();

        File::ensureDirectoryExists($tempDir);

        foreach ($project->slides as $index => $slide) {
            $filename = sprintf('%03d.png', $index + 1);
            $path = $tempDir.DIRECTORY_SEPARATOR.$filename;
            $this->renderer->render($slide, $preset, $path);
            $paths[$index] = $path;
        }

        return $paths;
    }

    public function buildConcatList(array $slidePaths, array $durations, string $listPath): void
    {
        $lines = [];
        foreach ($slidePaths as $index => $path) {
            $duration = $durations[$index] ?? 5;
            $escaped = str_replace("'", "'\\''", $path);
            $lines[] = "file '{$escaped}'";
            $lines[] = "duration {$duration}";
        }
        $last = end($slidePaths);
        $lines[] = "file '".str_replace("'", "'\\''", $last)."'";

        File::put($listPath, implode(PHP_EOL, $lines));
    }

    /**
     * @return array<int, string> slide index => mp4 segment path
     */
    public function buildSlideSegments(Project $project, ExportPreset $preset, string $tempDir): array
    {
        $this->storage->ensureStructure($project);
        File::ensureDirectoryExists($tempDir);

        $segments = [];
        $ffmpeg = config('criasys.ffmpeg_path');
        $w = $preset->width;
        $h = $preset->height;

        foreach ($project->slides as $index => $slide) {
            $segmentPath = $tempDir.DIRECTORY_SEPARATOR.sprintf('seg_%03d.mp4', $index + 1);
            $duration = max(0.5, (float) $slide->duration_seconds);
            $vf = "scale={$w}:{$h}:force_original_aspect_ratio=decrease,pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2,format=yuv420p";

            if ($slide->video_path && file_exists($slide->video_path)) {
                $result = Process::timeout(300)->run([
                    $ffmpeg, '-y',
                    '-stream_loop', '-1',
                    '-i', $slide->video_path,
                    '-t', (string) $duration,
                    '-vf', $vf,
                    '-an', '-r', '30',
                    $segmentPath,
                ]);
            } else {
                $pngPath = $tempDir.DIRECTORY_SEPARATOR.sprintf('frame_%03d.png', $index + 1);
                $this->renderer->render($slide, $preset, $pngPath);

                $result = Process::timeout(120)->run([
                    $ffmpeg, '-y',
                    '-loop', '1',
                    '-i', $pngPath,
                    '-t', (string) $duration,
                    '-vf', $vf,
                    '-r', '30',
                    $segmentPath,
                ]);

                if (file_exists($pngPath)) {
                    @File::delete($pngPath);
                }
            }

            if (! $result->successful() || ! file_exists($segmentPath)) {
                throw new \RuntimeException('FFmpeg segmento slide '.($index + 1).' falhou: '.$result->errorOutput());
            }

            $segments[$index] = $segmentPath;
        }

        return $segments;
    }

    public function buildVideoConcatList(array $segmentPaths, string $listPath): void
    {
        $lines = [];
        foreach ($segmentPaths as $path) {
            $escaped = str_replace("'", "'\\''", $path);
            $lines[] = "file '{$escaped}'";
        }

        File::put($listPath, implode(PHP_EOL, $lines));
    }
}
