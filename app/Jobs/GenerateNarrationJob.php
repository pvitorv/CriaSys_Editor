<?php

namespace App\Jobs;

use App\Services\NarrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateNarrationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $projectId,
        public string $voice,
    ) {}

    public function handle(NarrationService $narrationService): void
    {
        $project = \App\Models\Project::with('slides')->findOrFail($this->projectId);
        $narrationService->generate($project, $this->voice);
    }
}
