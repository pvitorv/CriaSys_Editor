<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProjectStorageService
{
    public function basePath(): string
    {
        return config('criasys.projects_path');
    }

    public function projectPath(Project $project): string
    {
        return $this->basePath().DIRECTORY_SEPARATOR.$project->id;
    }

    public function ensureStructure(Project $project): string
    {
        $path = $this->projectPath($project);
        $dirs = ['slides', 'audio', 'assets', 'exports', 'thumbs'];

        foreach ($dirs as $dir) {
            File::ensureDirectoryExists($path.DIRECTORY_SEPARATOR.$dir);
        }

        $jsonPath = $path.DIRECTORY_SEPARATOR.'project.json';
        if (! File::exists($jsonPath)) {
            File::put($jsonPath, json_encode([
                'id' => $project->id,
                'name' => $project->name,
                'created_at' => $project->created_at?->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $path;
    }

    public function slideImagePath(Project $project, string $filename): string
    {
        return $this->projectPath($project).DIRECTORY_SEPARATOR.'slides'.DIRECTORY_SEPARATOR.$filename;
    }

    public function audioPath(Project $project, string $filename): string
    {
        return $this->projectPath($project).DIRECTORY_SEPARATOR.'audio'.DIRECTORY_SEPARATOR.$filename;
    }

    public function exportPath(Project $project, string $filename): string
    {
        return $this->projectPath($project).DIRECTORY_SEPARATOR.'exports'.DIRECTORY_SEPARATOR.$filename;
    }

    public function thumbPath(Project $project, string $filename = 'thumbnail.jpg'): string
    {
        return $this->projectPath($project).DIRECTORY_SEPARATOR.'thumbs'.DIRECTORY_SEPARATOR.$filename;
    }

    public function relativeUrl(string $absolutePath): ?string
    {
        $base = $this->basePath();
        if (! str_starts_with($absolutePath, $base)) {
            return null;
        }

        $relative = ltrim(str_replace($base, '', $absolutePath), DIRECTORY_SEPARATOR);

        return '/storage/criasys/projetos/'.$relative;
    }
}
