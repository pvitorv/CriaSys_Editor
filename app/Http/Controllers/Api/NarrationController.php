<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Narration;
use App\Models\Project;
use App\Models\User;
use App\Services\NarrationService;
use App\Support\SafeJson;
use App\Support\Utf8;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class NarrationController extends Controller
{
    public function show(Project $project): JsonResponse
    {
        $narration = $project->narrations()->where('status', 'processing')->latest()->first()
            ?? $project->latestNarration();

        if (! $narration) {
            return response()->json((object) []);
        }

        $payload = SafeJson::prepare($narration->toArray());
        $segments = collect($narration->segments ?? [])
            ->filter(fn ($segment) => is_array($segment) && ! empty($segment['slide_id']))
            ->values()
            ->all();

        $payload['segments'] = $segments;

        if ($narration->status === 'failed') {
            $error = null;
            foreach ($narration->segments ?? [] as $segment) {
                if (is_array($segment) && isset($segment['_error'])) {
                    $error = (string) $segment['_error'];
                    break;
                }
            }
            $payload['error'] = $error ?: 'Falha ao gerar narração.';
        }

        if ($narration->status === 'processing') {
            $payload['progress'] = [
                'current' => count($segments),
            ];
        }

        if ($narration->status === 'completed' && $narration->audio_path) {
            $payload['audio_url'] = '/api/projects/'.$project->id.'/files/audio/'
                .rawurlencode(basename($narration->audio_path));
        }

        if ($narration->status === 'completed' && ! $narration->audio_path) {
            return response()->json((object) []);
        }

        return SafeJson::response($payload);
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
        @set_time_limit(0);

        $data = $request->validate([
            'voice' => ['nullable', 'string'],
            'engine' => ['nullable', 'string', 'in:edge,elevenlabs,openai,piper'],
            'async' => ['nullable', 'boolean'],
            'slide_ids' => ['nullable', 'array'],
            'slide_ids.*' => ['integer'],
        ]);

        $voice = $data['voice'] ?? config('criasys.tts.default_voice');
        $engine = $data['engine'] ?? config('criasys.tts.default_engine');
        $slideIds = $data['slide_ids'] ?? null;

        $project->load('slides');
        if (! $narrationService->hasNarratableSlides($project, $slideIds)) {
            return SafeJson::message(
                'Nenhum slide selecionado com texto para narração. Marque os slides desejados e preencha narração ou corpo.',
                422
            );
        }

        $useAsync = $data['async'] ?? true;

        if ($useAsync) {
            $narration = Narration::create([
                'project_id' => $project->id,
                'engine' => $engine,
                'voice' => $voice,
                'status' => 'processing',
                'segments' => [],
            ]);

            $narrationId = $narration->id;
            $projectId = $project->id;

            App::terminating(function () use ($narrationId, $projectId, $voice, $engine, $slideIds) {
                @set_time_limit(0);

                $project = Project::with('slides')->find($projectId);
                $record = Narration::find($narrationId);
                if (! $project || ! $record) {
                    return;
                }

                if ($owner = User::find($project->user_id)) {
                    auth()->setUser($owner);
                }

                try {
                    app(NarrationService::class)->generate(
                        $project,
                        $voice,
                        $engine,
                        $slideIds,
                        $record,
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            });

            $selectedCount = $slideIds !== null && $slideIds !== []
                ? count($slideIds)
                : $project->slides->count();

            return response()->json([
                'id' => $narration->id,
                'status' => 'processing',
                'message' => 'Narração em andamento. Aguarde…',
                'progress' => ['current' => 0, 'total' => $selectedCount],
            ], 202);
        }

        try {
            $narration = $narrationService->generate($project, $voice, $engine, $slideIds);
        } catch (\Throwable $e) {
            return SafeJson::message($e->getMessage() ?: 'Erro ao gerar narração.');
        }

        $payload = SafeJson::prepare($narration->toArray());
        if (! empty($narration->audio_path)) {
            $payload['audio_url'] = '/api/projects/'.$project->id.'/files/audio/'.rawurlencode(basename($narration->audio_path));
        }

        return SafeJson::response($payload);
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
