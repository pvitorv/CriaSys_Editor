<?php

namespace App\Http\Controllers\Api;

use App\Enums\RenderStatus;
use App\Http\Controllers\Controller;
use App\Jobs\RenderVideoJob;
use App\Models\ExportPreset;
use App\Models\Project;
use App\Models\RenderJob;
use App\Services\Render\FfmpegRenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RenderController extends Controller
{
    public function presets(): JsonResponse
    {
        return response()->json(ExportPreset::all());
    }

    public function index(Project $project): JsonResponse
    {
        return response()->json(
            $project->renderJobs()->latest()->get()
        );
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'preset' => ['required', 'string', 'exists:export_presets,slug'],
            'generate_thumb' => ['nullable', 'boolean'],
            'burn_subtitles' => ['nullable', 'boolean'],
        ]);

        $job = RenderJob::create([
            'project_id' => $project->id,
            'preset' => $data['preset'],
            'burn_subtitles' => $data['burn_subtitles'] ?? false,
            'status' => RenderStatus::Pending,
            'progress' => 0,
        ]);

        RenderVideoJob::dispatch($job->id, $data['generate_thumb'] ?? false);

        return response()->json($job, 201);
    }

    public function show(Project $project, RenderJob $renderJob): JsonResponse
    {
        abort_unless($renderJob->project_id === $project->id, 404);

        return response()->json($renderJob);
    }

    public function retry(Project $project, RenderJob $renderJob): JsonResponse
    {
        abort_unless($renderJob->project_id === $project->id, 404);

        $renderJob->update([
            'status' => RenderStatus::Pending,
            'progress' => 0,
            'error_log' => null,
            'output_path' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);

        RenderVideoJob::dispatch($renderJob->id);

        return response()->json($renderJob->fresh());
    }

    public function thumbnail(Request $request, Project $project, FfmpegRenderService $ffmpeg): JsonResponse
    {
        $data = $request->validate([
            'slide_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $project->load('slides');
        $preset = ExportPreset::where('slug', 'youtube_landscape')->firstOrFail();

        try {
            $path = $ffmpeg->generateThumbnail($project, $preset, $data['slide_index'] ?? 0);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'path' => $path,
            'url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'thumbs',
                'filename' => basename($path),
            ]),
        ]);
    }
}
