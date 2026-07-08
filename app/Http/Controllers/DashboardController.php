<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $projects = Project::query()
            ->where('status', '!=', 'archived')
            ->latest()
            ->get();

        return view('dashboard', compact('projects'));
    }
}
