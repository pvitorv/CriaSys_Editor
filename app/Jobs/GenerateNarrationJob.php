<?php

namespace App\Jobs;

use App\Models\Narration;
use App\Models\Project;
use App\Models\User;
use App\Services\NarrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateNarrationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 0;

    /**
     * @param  list<int>|null  $slideIds
     */
    public function __construct(
        public int $projectId,
        public string $voice,
        public ?string $engine = null,
        public ?array $slideIds = null,
        public ?int $narrationId = null,
    ) {}

    public function handle(NarrationService $narrationService): void
    {
        @set_time_limit(0);

        $project = Project::with('slides')->findOrFail($this->projectId);

        if ($owner = User::find($project->user_id)) {
            auth()->setUser($owner);
        }

        $narration = $this->narrationId
            ? Narration::where('project_id', $project->id)->findOrFail($this->narrationId)
            : null;

        $narrationService->generate(
            $project,
            $this->voice,
            $this->engine,
            $this->slideIds,
            $narration,
        );
    }
}
