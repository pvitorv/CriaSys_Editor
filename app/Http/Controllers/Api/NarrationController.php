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
            'engine' => ['nullable', 'string', 'in:edge,elevenlabs,openai,piper'],
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
            'audio_url' => '/api/projects/'.$project->id.'/files/audio/'.$filename,
            'duration_seconds' => $result['duration_seconds'],
            'engine_used' => $result['engine'] ?? $engine,
        ]);
    }

    public function generate(Request $request, Project $project, NarrationService $narrationService): JsonResponse
    {
        $data = $request->validate([
            'voice' => ['nullable', 'string'],
            'engine' => ['nullable', 'string', 'in:edge,elevenlabs,openai,piper'],
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

    public function update(Request $request, Project $project): JsonResponse
    {
        $narration = $project->latestNarration();
        if (! $narration) {
            return response()->json(['message' => 'Narração não encontrada.'], 404);
        }

        $data = $request->validate([
            'trim_in' => ['nullable', 'numeric', 'min:0'],
            'trim_out' => ['nullable', 'numeric', 'min:0'],
        ]);

        $narration->update($data);

        return response()->json($narration->fresh());
    }
}
