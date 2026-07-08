<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateNarrationJob;
use App\Models\Project;
use App\Services\NarrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NarrationController extends Controller
{
    public function show(Project $project): JsonResponse
    {
        return response()->json($project->latestNarration());
    }

    public function generate(Request $request, Project $project, NarrationService $narrationService): JsonResponse
    {
        $data = $request->validate([
            'voice' => ['nullable', 'string'],
            'engine' => ['nullable', 'string', 'in:edge,coqui,elevenlabs,openai'],
            'async' => ['nullable', 'boolean'],
        ]);

        $voice = $data['voice'] ?? config('criasys.tts.default_voice');
        $engine = $data['engine'] ?? config('criasys.tts.default_engine');

        if ($data['async'] ?? false) {
            GenerateNarrationJob::dispatch($project->id, $voice, $engine);

            return response()->json(['message' => 'Narração enfileirada.'], 202);
        }

        try {
            $narration = $narrationService->generate($project, $voice, $engine);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($narration);
    }

    public function sync(Project $project, NarrationService $narrationService): JsonResponse
    {
        try {
            $narrationService->syncSlideDurations($project);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($project->fresh('slides')->slides);
    }
}
