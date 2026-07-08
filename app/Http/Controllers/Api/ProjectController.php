<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(private ProjectStorageService $storage) {}

    public function index(): JsonResponse
    {
        return response()->json(
            auth()->user()->projects()->latest()->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
        ]);

        $project = auth()->user()->projects()->create($data);
        $this->storage->ensureStructure($project);

        return response()->json($project, 201);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json($project->load('slides'));
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string'],
            'settings' => ['nullable', 'array'],
        ]);

        $project->update($data);

        return response()->json($project->fresh('slides'));
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return response()->json(['message' => 'Projeto excluído.']);
    }

    public function duplicate(Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $copy = $project->replicate(['status']);
        $copy->name = $project->name.' (cópia)';
        $copy->user_id = auth()->id();
        $copy->save();

        foreach ($project->slides as $slide) {
            $newSlide = $slide->replicate();
            $newSlide->project_id = $copy->id;
            $newSlide->save();
        }

        $this->storage->ensureStructure($copy);

        return response()->json($copy->load('slides'), 201);
    }

    public function archive(Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $project->update(['status' => 'archived']);

        return response()->json($project);
    }
}
