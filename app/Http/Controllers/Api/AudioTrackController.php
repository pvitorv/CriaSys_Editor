<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AudioTrack;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AudioTrackController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json(
            $project->audioTracks()->with('asset')->orderBy('track_slot')->get()
        );
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:music,sfx'],
            'track_slot' => ['nullable', 'integer', 'min:0', 'max:2'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'file_path' => ['nullable', 'string'],
            'volume' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'start_at' => ['nullable', 'numeric', 'min:0'],
            'ducking_enabled' => ['nullable', 'boolean'],
        ]);

        $type = $data['type'] ?? 'music';
        $slot = (int) ($data['track_slot'] ?? 0);

        if ($type === 'music' && ($slot < 0 || $slot > 2)) {
            return response()->json(['message' => 'Trilha inválida — use slot 0, 1 ou 2.'], 422);
        }

        $track = $project->audioTracks()->updateOrCreate(
            ['type' => $type, 'track_slot' => $slot],
            [
                'asset_id' => $data['asset_id'] ?? null,
                'file_path' => $data['file_path'] ?? null,
                'volume' => $data['volume'] ?? 0.35,
                'start_at' => $data['start_at'] ?? 0,
                'ducking_enabled' => $data['ducking_enabled'] ?? true,
            ]
        );

        return response()->json($track->load('asset'), 201);
    }

    public function update(Request $request, Project $project, AudioTrack $audioTrack): JsonResponse
    {
        abort_unless($audioTrack->project_id === $project->id, 404);

        $data = $request->validate([
            'volume' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'start_at' => ['nullable', 'numeric', 'min:0'],
            'ducking_enabled' => ['nullable', 'boolean'],
            'file_path' => ['nullable', 'string'],
            'asset_id' => ['nullable', 'exists:assets,id'],
        ]);

        $audioTrack->update($data);

        return response()->json($audioTrack->fresh('asset'));
    }

    public function destroy(Project $project, AudioTrack $audioTrack): JsonResponse
    {
        abort_unless($audioTrack->project_id === $project->id, 404);
        $audioTrack->delete();

        return response()->json(['message' => 'Trilha removida.']);
    }
}
