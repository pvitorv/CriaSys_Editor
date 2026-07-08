<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Slide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlideController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json($project->slides);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body_text' => ['nullable', 'string'],
            'narration_text' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0.5'],
            'transition_type' => ['nullable', 'in:fade,cut,slide'],
            'text_style' => ['nullable', 'array'],
        ]);

        $order = ($project->slides()->max('order') ?? -1) + 1;

        $slide = $project->slides()->create(array_merge($data, [
            'order' => $order,
            'duration_seconds' => $data['duration_seconds'] ?? 5,
            'transition_type' => $data['transition_type'] ?? 'fade',
        ]));

        return response()->json($slide, 201);
    }

    public function update(Request $request, Project $project, Slide $slide): JsonResponse
    {
        abort_unless($slide->project_id === $project->id, 404);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body_text' => ['nullable', 'string'],
            'narration_text' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0.5'],
            'transition_type' => ['nullable', 'in:fade,cut,slide'],
            'text_style' => ['nullable', 'array'],
            'image_path' => ['nullable', 'string'],
        ]);

        $slide->update($data);

        return response()->json($slide->fresh());
    }

    public function destroy(Project $project, Slide $slide): JsonResponse
    {
        abort_unless($slide->project_id === $project->id, 404);

        $slide->delete();

        $project->slides()->orderBy('order')->get()->each(function (Slide $s, int $index) {
            $s->update(['order' => $index]);
        });

        return response()->json(['message' => 'Slide removido.']);
    }

    public function reorder(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'slide_ids' => ['required', 'array'],
            'slide_ids.*' => ['integer', 'exists:slides,id'],
        ]);

        foreach ($data['slide_ids'] as $order => $slideId) {
            Slide::where('id', $slideId)->where('project_id', $project->id)->update(['order' => $order]);
        }

        return response()->json($project->fresh('slides')->slides);
    }
}
