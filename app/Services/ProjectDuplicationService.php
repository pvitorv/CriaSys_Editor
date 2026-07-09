<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;

class ProjectDuplicationService
{
    public function __construct(private ProjectStorageService $storage) {}

    public function duplicate(Project $project, int $userId): Project
    {
        $project->load(['slides', 'assets', 'audioTracks', 'narrations']);

        $copy = $project->replicate(['status']);
        $copy->name = $project->name.' (cópia)';
        $copy->user_id = $userId;
        $copy->save();

        $this->storage->ensureStructure($copy);

        $assetMap = [];
        foreach ($project->assets as $asset) {
            $newAsset = $asset->replicate();
            $newAsset->project_id = $copy->id;
            $newAsset->file_path = $this->copyFile($asset->file_path, $copy, 'assets') ?? $asset->file_path;
            $newAsset->save();
            $assetMap[$asset->id] = $newAsset->id;
        }

        foreach ($project->slides as $slide) {
            $newSlide = $slide->replicate();
            $newSlide->project_id = $copy->id;
            if ($slide->image_path) {
                $subdir = str_contains(str_replace('/', DIRECTORY_SEPARATOR, $slide->image_path), DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR)
                    ? 'assets'
                    : 'slides';
                $newSlide->image_path = $this->copyFile($slide->image_path, $copy, $subdir) ?? $slide->image_path;
            }
            if ($slide->video_path) {
                $newSlide->video_path = $this->copyFile($slide->video_path, $copy, 'assets') ?? $slide->video_path;
            }
            $newSlide->save();
        }

        foreach ($project->audioTracks as $track) {
            $newTrack = $track->replicate();
            $newTrack->project_id = $copy->id;
            $newTrack->asset_id = $track->asset_id ? ($assetMap[$track->asset_id] ?? null) : null;
            $newTrack->file_path = $this->copyFile($track->file_path, $copy, 'audio') ?? $track->file_path;
            $newTrack->save();
        }

        $narration = $project->latestNarration();
        if ($narration) {
            $newNarration = $narration->replicate();
            $newNarration->project_id = $copy->id;
            $newNarration->audio_path = $this->copyFile($narration->audio_path, $copy, 'audio') ?? $narration->audio_path;
            $newNarration->save();
        }

        return $copy->load(['slides', 'assets', 'audioTracks', 'narrations']);
    }

    private function copyFile(?string $source, Project $copy, string $subdir): ?string
    {
        if (! $source || ! file_exists($source)) {
            return null;
        }

        $filename = basename($source);
        $dest = $this->storage->projectPath($copy).DIRECTORY_SEPARATOR.$subdir.DIRECTORY_SEPARATOR.$filename;

        if (! File::exists($dest)) {
            File::copy($source, $dest);
        }

        return $dest;
    }
}
