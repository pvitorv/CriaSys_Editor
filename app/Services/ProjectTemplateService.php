<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\User;

class ProjectTemplateService
{
    public function __construct(private ProjectStorageService $storage) {}

    public function createProjectFromTemplate(
        User $user,
        ProjectTemplate $template,
        string $name,
        ?string $description = null,
    ): Project {
        $project = $user->projects()->create([
            'name' => $name,
            'description' => $description,
            'settings' => ['aspect_ratio' => $template->aspect_ratio],
        ]);

        $this->storage->ensureStructure($project);

        foreach ($template->slides as $order => $slideData) {
            $project->slides()->create(array_merge($slideData, [
                'order' => $order,
                'duration_seconds' => $slideData['duration_seconds'] ?? 5,
                'transition_type' => $slideData['transition_type'] ?? 'fade',
            ]));
        }

        return $project->fresh('slides');
    }
}
