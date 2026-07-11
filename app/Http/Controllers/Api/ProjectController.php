<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectDuplicationService;
use App\Services\ProjectQuotaService;
use App\Services\ProjectStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectStorageService $storage,
        private ProjectDuplicationService $duplication,
        private ProjectQuotaService $quota,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            auth()->user()->projects()->latest()->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->quota->assertCanCreate(auth()->user());

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
        $warning = $this->quota->deleteWarning($project);
        $project->delete();

        return response()->json([
            'message' => 'Projeto excluído.',
            'warning' => $warning,
        ]);
    }

    public function markExported(Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $project = $this->quota->markExported($project);

        return response()->json([
            'message' => 'Projeto marcado como exportado.',
            'project' => $project,
        ]);
    }

    public function duplicate(Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $this->quota->assertCanDuplicate(auth()->user());
        $this->quota->assertCanCreate(auth()->user());
        $copy = $this->duplication->duplicate($project, auth()->id());

        return response()->json($copy, 201);
    }

    public function archive(Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $project->update(['status' => 'archived']);

        return response()->json($project);
    }
}
