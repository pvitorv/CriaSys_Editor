<?php

namespace App\Services\Render;

use App\Models\ExportPreset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\File;

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
}
