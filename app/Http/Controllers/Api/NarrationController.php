<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateNarrationJob;
use App\Models\Project;
use App\Services\NarrationService;
use App\Support\SafeJson;
use App\Support\Utf8;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NarrationController extends Controller
{
    public function show(Project $project): JsonResponse
    {
        $narration = $project->latestNarration();

        return response()->json($narration ?? (object) []);
    }

    public function preview(Request $request, Project $project, NarrationService $narrationService): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'min:1', 'max:5000'],
            'voice' => ['nullable', 'string'],
            'engine' => ['nullable', 'string', 'in:edge,coqui,elevenlabs,openai'],
        ]);

        $voice = $data['voice'] ?? config('criasys.tts.default_voice');
        $engine = $data['engine'] ?? config('criasys.tts.default_engine');

        try {
            $result = $narrationService->previewText($project, Utf8::clean($data['text']) ?? '', $voice, $engine);
        } catch (\Throwable $e) {
            return SafeJson::message($e->getMessage() ?: 'Erro ao gerar teste de voz.');
        }

        $filename = basename($result['audio_path']);

        return SafeJson::response([
            'audio_url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'audio',
                'filename' => $filename,
            ]),
            'duration_seconds' => $result['duration_seconds'],
        ]);
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
            return SafeJson::message($e->getMessage() ?: 'Erro ao gerar narração.');
        }

        return SafeJson::response($narration);
    }

    public function sync(Project $project, NarrationService $narrationService): JsonResponse
    {
        try {
            $narrationService->syncSlideDurations($project);
        } catch (\Throwable $e) {
            return SafeJson::message($e->getMessage() ?: 'Erro ao sincronizar narração.');
        }

        return SafeJson::response($project->fresh('slides')->slides);
    }
}
