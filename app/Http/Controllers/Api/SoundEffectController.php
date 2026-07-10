<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SoundEffect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SoundEffectController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json(
            $project->soundEffects()->with('asset')->orderBy('start_at')->get()
        );
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'file_path' => ['nullable', 'string'],
            'start_at' => ['nullable', 'numeric', 'min:0'],
            'trim_in' => ['nullable', 'numeric', 'min:0'],
            'trim_out' => ['nullable', 'numeric', 'min:0'],
            'source_duration' => ['nullable', 'numeric', 'min:0'],
            'clip_duration' => ['nullable', 'numeric', 'min:0.1'],
            'volume' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $effect = $project->soundEffects()->create([
            'label' => $data['label'] ?? 'Efeito',
            'asset_id' => $data['asset_id'] ?? null,
            'file_path' => $data['file_path'] ?? null,
            'start_at' => $data['start_at'] ?? 0,
            'trim_in' => $data['trim_in'] ?? 0,
            'trim_out' => $data['trim_out'] ?? null,
            'source_duration' => $data['source_duration'] ?? null,
            'clip_duration' => $data['clip_duration'] ?? null,
            'volume' => $data['volume'] ?? 1,
        ]);

        return response()->json($effect->load('asset'), 201);
    }

    public function update(Request $request, Project $project, SoundEffect $soundEffect): JsonResponse
    {
        abort_unless($soundEffect->project_id === $project->id, 404);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'start_at' => ['nullable', 'numeric', 'min:0'],
            'trim_in' => ['nullable', 'numeric', 'min:0'],
            'trim_out' => ['nullable', 'numeric', 'min:0'],
            'source_duration' => ['nullable', 'numeric', 'min:0'],
            'clip_duration' => ['nullable', 'numeric', 'min:0.1'],
            'volume' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'file_path' => ['nullable', 'string'],
            'asset_id' => ['nullable', 'exists:assets,id'],
        ]);

        $soundEffect->update($data);

        return response()->json($soundEffect->fresh('asset'));
    }

    public function destroy(Project $project, SoundEffect $soundEffect): JsonResponse
    {
        abort_unless($soundEffect->project_id === $project->id, 404);
        $soundEffect->delete();

        return response()->json(['message' => 'Efeito removido.']);
    }
}
