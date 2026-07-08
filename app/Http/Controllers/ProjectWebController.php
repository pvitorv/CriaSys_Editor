<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectWebController extends Controller
{
    public function __construct(private ProjectStorageService $storage) {}

    public function create(): View
    {
        return view('projects.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'aspect_ratio' => ['required', 'in:16:9,9:16'],
        ]);

        $project = auth()->user()->projects()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'settings' => ['aspect_ratio' => $data['aspect_ratio']],
        ]);

        $this->storage->ensureStructure($project);

        return redirect()->route('projects.editor', $project);
    }

    public function editor(Project $project): View
    {
        $this->authorize('view', $project);
        $project->load('slides');

        return view('projects.editor', compact('project'));
    }
}
