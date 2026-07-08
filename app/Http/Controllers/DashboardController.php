<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $projects = auth()->user()
            ->projects()
            ->where('status', '!=', 'archived')
            ->latest()
            ->get();

        return view('dashboard', compact('projects'));
    }
}
