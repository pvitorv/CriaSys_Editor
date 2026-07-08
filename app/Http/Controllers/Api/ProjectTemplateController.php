<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectTemplate;
use Illuminate\Http\JsonResponse;

class ProjectTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            ProjectTemplate::where('is_active', true)->orderBy('name')->get()
        );
    }
}
