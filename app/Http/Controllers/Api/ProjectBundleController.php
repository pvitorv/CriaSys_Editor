<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectBundle\ProjectBundleExporter;
use App\Services\ProjectBundle\ProjectBundleImporter;
use App\Services\ProjectQuotaService;
use App\Support\DeploymentMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectBundleController extends Controller
{
    public function __construct(
        private ProjectBundleExporter $exporter,
        private ProjectBundleImporter $importer,
        private ProjectQuotaService $quota,
    ) {}

    public function export(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $result = $this->exporter->export($project);

        if (DeploymentMode::isOnline()) {
            $this->quota->markExported($project->fresh());
        }

        return response()->json([
            'message' => 'Bundle do projeto gerado.',
            'filename' => $result['filename'],
            'url' => $result['url'],
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $this->quota->assertCanCreate($request->user());

        $data = $request->validate([
            'bundle' => ['required', 'file', 'mimes:zip', 'max:512000'],
        ]);

        $project = $this->importer->import($request->user(), $data['bundle']);

        return response()->json([
            'message' => 'Projeto importado com sucesso.',
            'project' => $project,
            'editor_url' => route('projects.editor', $project),
        ], 201);
    }
}
