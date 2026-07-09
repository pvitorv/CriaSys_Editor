<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ExportPackageJob;
use App\Models\ExportPackage;
use App\Models\Project;
use App\Services\Export\ProjectDownloadCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json($project->exportPackages()->latest()->get());
    }

    public function downloads(Project $project, ProjectDownloadCatalog $catalog): JsonResponse
    {
        return response()->json($catalog->list($project));
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'preset' => ['nullable', 'string', 'exists:export_presets,slug'],
        ]);

        $preset = $data['preset'] ?? 'youtube_landscape';

        $package = ExportPackage::create([
            'project_id' => $project->id,
            'status' => 'pending',
            'includes' => [
                'slides', 'audio', 'legendas.srt', 'timeline.json',
                'premiere.xml', 'credits.txt', 'thumbnail.jpg', 'README.txt',
            ],
        ]);

        ExportPackageJob::dispatch($package->id, $preset);

        return response()->json($package, 201);
    }

    public function show(Project $project, ExportPackage $exportPackage): JsonResponse
    {
        abort_unless($exportPackage->project_id === $project->id, 404);

        return response()->json($exportPackage);
    }

    public function subtitles(Project $project): JsonResponse
    {
        $project->load('slides');
        $srt = app(\App\Services\Export\SrtGenerator::class)->generate($project);

        $path = app(\App\Services\ProjectStorageService::class)
            ->exportPath($project, 'legendas.srt');
        \Illuminate\Support\Facades\File::put($path, $srt);

        return response()->json([
            'path' => $path,
            'content' => $srt,
            'url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'exports',
                'filename' => 'legendas.srt',
            ]),
        ]);
    }

    public function exportPsd(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'preset' => ['nullable', 'string', 'exists:export_presets,slug'],
        ]);

        try {
            $path = app(\App\Services\Export\PsdExportService::class)
                ->exportZip($project, $data['preset'] ?? 'youtube_landscape');
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'path' => $path,
            'url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'exports',
                'filename' => basename($path),
            ]),
        ]);
    }
}
