<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Services\ImageStudio\ImageStudioService;
use App\Services\ProjectQuotaService;
use App\Services\ProjectStorageService;
use App\Services\ProjectTemplateService;
use App\Support\DeploymentMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectWebController extends Controller
{
    public function __construct(
        private ProjectStorageService $storage,
        private ProjectQuotaService $quota,
    ) {}

    public function create(): View
    {
        $templates = ProjectTemplate::where('is_active', true)->orderBy('name')->get();
        $deployment = DeploymentMode::meta();
        $canCreate = $this->quota->canCreateProject(auth()->user());

        return view('projects.create', compact('templates', 'deployment', 'canCreate'));
    }

    public function store(Request $request, ProjectTemplateService $templates): RedirectResponse
    {
        $this->quota->assertCanCreate(auth()->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'aspect_ratio' => ['required_without:template_id', 'in:16:9,9:16'],
            'template_id' => ['nullable', 'integer', 'exists:project_templates,id'],
        ]);

        if (! empty($data['template_id'])) {
            $template = ProjectTemplate::findOrFail($data['template_id']);
            $project = $templates->createProjectFromTemplate(
                auth()->user(),
                $template,
                $data['name'],
                $data['description'] ?? null,
            );

            return redirect()->route('projects.editor', $project);
        }

        $project = auth()->user()->projects()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'settings' => ['aspect_ratio' => $data['aspect_ratio']],
        ]);

        $this->storage->ensureStructure($project);

        return redirect()->route('projects.editor', $project);
    }

    public function editor(Project $project, ImageStudioService $imageStudio): View
    {
        $this->authorize('view', $project);
        $project->load('slides');

        $imageStudioCatalog = $imageStudio->catalog(auth()->user());
        $deployment = DeploymentMode::meta();

        return view('projects.editor', compact('project', 'imageStudioCatalog', 'deployment'));
    }
}
