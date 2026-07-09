<?php

namespace App\Services\Render;

use App\Models\ExportPreset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class SlideshowBuilder
{
    private const TRANSITION_SECONDS = 0.5;

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
        $scalePad = "scale={$w}:{$h}:force_original_aspect_ratio=decrease,pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2";

        foreach ($project->slides as $index => $slide) {
            $segmentPath = $tempDir.DIRECTORY_SEPARATOR.sprintf('seg_%03d.mp4', $index + 1);
            $duration = max(0.5, (float) $slide->duration_seconds);
            $hasText = trim((string) ($slide->title.$slide->subtitle.$slide->body_text)) !== '';

            if ($slide->video_path && file_exists($slide->video_path)) {
                if ($hasText) {
                    $overlayPath = $tempDir.DIRECTORY_SEPARATOR.sprintf('overlay_%03d.png', $index + 1);
                    $this->renderer->renderTextOverlay($slide, $preset, $overlayPath);
                    $filter = "[0:v]{$scalePad},format=yuv420p[bg];[bg][1:v]overlay=0:0:format=auto,format=yuv420p[vout]";
                    $result = Process::timeout(300)->run([
                        $ffmpeg, '-y',
                        '-stream_loop', '-1',
                        '-i', $slide->video_path,
                        '-loop', '1',
                        '-i', $overlayPath,
                        '-t', (string) $duration,
                        '-filter_complex', $filter,
                        '-map', '[vout]',
                        '-an', '-r', '30',
                        $segmentPath,
                    ]);
                    if (file_exists($overlayPath)) {
                        @File::delete($overlayPath);
                    }
                } else {
                    $vf = "{$scalePad},format=yuv420p";
                    $result = Process::timeout(300)->run([
                        $ffmpeg, '-y',
                        '-stream_loop', '-1',
                        '-i', $slide->video_path,
                        '-t', (string) $duration,
                        '-vf', $vf,
                        '-an', '-r', '30',
                        $segmentPath,
                    ]);
                }
            } else {
                $pngPath = $tempDir.DIRECTORY_SEPARATOR.sprintf('frame_%03d.png', $index + 1);
                $this->renderer->render($slide, $preset, $pngPath);
                $vf = "{$scalePad},format=yuv420p";

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

    /**
     * Une segmentos aplicando fade/slide/corte conforme transition_type de cada slide.
     *
     * @param  array<int, string>  $segmentPaths
     */
    public function mergeSegmentsWithTransitions(Project $project, array $segmentPaths, string $outputPath): void
    {
        $project->loadMissing('slides');
        $slides = $project->slides->sortBy('order')->values();
        $paths = array_values($segmentPaths);
        $n = count($paths);

        if ($n === 0) {
            throw new \RuntimeException('Nenhum segmento para renderizar.');
        }

        if ($n === 1) {
            File::copy($paths[0], $outputPath);

            return;
        }

        $durations = $slides->take($n)->map(fn ($slide) => max(0.5, (float) $slide->duration_seconds))->all();
        $ffmpeg = config('criasys.ffmpeg_path');

        $needsTransitions = false;
        for ($i = 0; $i < $n - 1; $i++) {
            if (($slides[$i]->transition_type ?? 'fade') !== 'cut') {
                $needsTransitions = true;
                break;
            }
        }

        if (! $needsTransitions) {
            $listPath = dirname($outputPath).DIRECTORY_SEPARATOR.'concat_'.uniqid().'.txt';
            $this->buildVideoConcatList($segmentPaths, $listPath);
            $result = Process::timeout(600)->run([
                $ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $listPath,
                '-c', 'copy', $outputPath,
            ]);

            if (! $result->successful()) {
                $result = Process::timeout(600)->run([
                    $ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $listPath,
                    '-pix_fmt', 'yuv420p', $outputPath,
                ]);
            }

            File::delete($listPath);

            if (! $result->successful()) {
                throw new \RuntimeException('FFmpeg concat falhou: '.$result->errorOutput());
            }

            return;
        }

        $inputs = [];
        foreach ($paths as $path) {
            $inputs[] = '-i';
            $inputs[] = $path;
        }

        $parts = [];
        $currentLabel = '[0:v]';
        $currentDuration = $durations[0];

        for ($i = 0; $i < $n - 1; $i++) {
            $nextInput = '['.($i + 1).':v]';
            $outLabel = ($i === $n - 2) ? '[vout]' : '[vxf'.$i.']';
            $transType = $slides[$i]->transition_type ?? 'fade';

            if ($transType === 'cut') {
                $parts[] = "{$currentLabel}{$nextInput}concat=n=2:v=1:a=0{$outLabel}";
                $currentDuration += $durations[$i + 1];
            } else {
                $xfade = $transType === 'slide' ? 'slideleft' : 'fade';
                $offset = max(0, round($currentDuration - self::TRANSITION_SECONDS, 3));
                $parts[] = "{$currentLabel}{$nextInput}xfade=transition={$xfade}:duration=".self::TRANSITION_SECONDS.":offset={$offset}{$outLabel}";
                $currentDuration += $durations[$i + 1] - self::TRANSITION_SECONDS;
            }

            $currentLabel = $outLabel;
        }

        $filter = implode(';', $parts);
        $cmd = array_merge(
            [$ffmpeg, '-y'],
            $inputs,
            ['-filter_complex', $filter, '-map', '[vout]', '-pix_fmt', 'yuv420p', '-r', '30', $outputPath]
        );

        $result = Process::timeout(900)->run($cmd);

        if (! $result->successful()) {
            throw new \RuntimeException('FFmpeg transições falhou: '.$result->errorOutput());
        }
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
