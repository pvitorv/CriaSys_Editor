<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ExportPackageJob;
use App\Models\ExportPackage;
use App\Models\Project;
use App\Services\Export\PlatformPostDescriptionService;
use App\Services\Export\ProjectAttributionCatalog;
use App\Services\Export\ProjectCreditsClipboard;
use App\Services\Export\ProjectDownloadCatalog;
use App\Services\Export\ProjectPublishAutoSyncService;
use App\Services\Export\PublishKitExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function credits(Project $project, ProjectCreditsClipboard $clipboard): JsonResponse
    {
        $lines = $clipboard->lines($project);
        $text = $clipboard->asText($project);

        return response()->json([
            'lines' => $lines,
            'text' => $text,
            'count' => count($lines),
        ]);
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
                'premiere.xml', 'credits.txt', 'creditos_materiais.txt', 'descricoes', 'thumbnail.jpg', 'README.txt',
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

    public function platformDescriptions(Project $project, PlatformPostDescriptionService $descriptions): JsonResponse
    {
        return response()->json($descriptions->generateAll($project));
    }

    public function savePlatformDescriptions(Project $project, PlatformPostDescriptionService $descriptions): JsonResponse
    {
        $paths = $descriptions->saveToProject($project);

        $files = [];
        foreach ($paths as $key => $path) {
            $files[$key] = [
                'path' => $path,
                'url' => route('api.projects.files', [
                    'project' => $project->id,
                    'type' => 'exports',
                    'filename' => basename($path),
                ]),
            ];
        }

        return response()->json([
            'message' => 'Descrições e créditos gerados.',
            'files' => $files,
            'descriptions' => $descriptions->generateAll($project),
        ]);
    }

    public function updatePlatformDescription(Request $request, Project $project, PlatformPostDescriptionService $descriptions): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'platform' => ['required', 'string', Rule::in(array_keys(config('publish_platforms', [])))],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        $settings = $project->settings ?? [];
        $custom = $settings['platform_descriptions'] ?? [];

        if ($data['description'] === null || trim($data['description']) === '') {
            unset($custom[$data['platform']]);
        } else {
            $custom[$data['platform']] = trim($data['description']);
        }

        $settings['platform_descriptions'] = $custom;
        $project->update(['settings' => $settings]);

        $descriptions->saveToProject($project->fresh());

        return response()->json([
            'message' => 'Descrição salva.',
            'descriptions' => $descriptions->generateAll($project->fresh()),
        ]);
    }

    public function publishKit(Project $project, PublishKitExporter $kit): JsonResponse
    {
        $this->authorize('view', $project);

        $result = $kit->export($project);

        return response()->json([
            'message' => 'Publish Kit gerado.',
            'filename' => $result['filename'],
            'url' => $result['url'],
        ]);
    }

    public function syncPublish(Project $project, ProjectPublishAutoSyncService $sync): JsonResponse
    {
        return response()->json($sync->sync($project));
    }
}
