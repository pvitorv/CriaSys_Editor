<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProjectQuotaService;
use App\Support\DeploymentMode;
use Illuminate\Http\JsonResponse;

class DeploymentController extends Controller
{
    public function show(ProjectQuotaService $quota): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            ...DeploymentMode::meta(),
            'active_projects' => $user ? $quota->activeProjectCount($user) : 0,
            'can_create_project' => $user ? $quota->canCreateProject($user) : false,
        ]);
    }
}
