<?php

namespace App\Http\Controllers;

use App\Services\ProjectQuotaService;
use App\Support\DeploymentMode;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private ProjectQuotaService $quota) {}

    public function index(): View
    {
        $projects = auth()->user()
            ->projects()
            ->where('status', '!=', 'archived')
            ->latest()
            ->get();

        $deployment = DeploymentMode::meta();
        $canCreate = $this->quota->canCreateProject(auth()->user());

        return view('dashboard', compact('projects', 'deployment', 'canCreate'));
    }
}
