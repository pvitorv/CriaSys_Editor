<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ImageStudio\BackgroundRemovalService;
use App\Services\ImageStudio\ImageStudioService;
use App\Services\ProjectStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImageStudioController extends Controller
{
    public function catalog(Request $request, ImageStudioService $studio): JsonResponse
    {
        return response()->json($studio->catalog($request->user()));
    }

    public function show(Project $project, Request $request, ImageStudioService $studio): JsonResponse
    {
        $preset = $request->query('preset');

        return response()->json($studio->loadDesign($project, $preset));
    }

    public function update(Request $request, Project $project, ImageStudioService $studio): JsonResponse
    {
        $data = $request->validate([
            'preset' => ['required', 'string'],
            'canvas' => ['required', 'array'],
        ]);

        $saved = $studio->saveDesign($project, $data['preset'], $data['canvas']);

        return response()->json([
            'message' => 'Design salvo.',
            'design' => $saved,
        ]);
    }

    public function export(Request $request, Project $project, ImageStudioService $studio): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'format' => ['required', 'string', 'in:png,jpg,jpeg,svg,json,psd,pdf'],
            'preset' => ['nullable', 'string'],
        ]);

        $format = $data['format'] === 'jpeg' ? 'jpg' : $data['format'];
        $result = $studio->storeExport($project, $request->file('file'), $format, $data['preset'] ?? null);

        return response()->json([
            'message' => 'Exportado com sucesso.',
            'export' => $result,
        ]);
    }

    public function removeBackground(
        Request $request,
        Project $project,
        BackgroundRemovalService $remover,
        ProjectStorageService $storage,
        ImageStudioService $studio
    ): JsonResponse {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:20480'],
        ]);

        $storage->ensureStructure($project);
        $input = $data['image'];
        $tmpIn = $studio->designsDir($project).DIRECTORY_SEPARATOR.'bg_in_'.Str::random(8).'.png';
        $tmpOut = $studio->designsDir($project).DIRECTORY_SEPARATOR.'bg_out_'.Str::random(8).'.png';
        $input->move(dirname($tmpIn), basename($tmpIn));

        try {
            $remover->remove($tmpIn, $tmpOut);
        } finally {
            if (file_exists($tmpIn)) {
                File::delete($tmpIn);
            }
        }

        $filename = 'nobg_'.Str::random(8).'.png';
        $final = $studio->designsDir($project).DIRECTORY_SEPARATOR.$filename;
        File::move($tmpOut, $final);

        return response()->json([
            'message' => 'Fundo removido.',
            'url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'designs',
                'filename' => $filename,
            ]).'?t='.time(),
            'filename' => $filename,
        ]);
    }

    public function pushThumbnail(Request $request, Project $project, ImageStudioService $studio): JsonResponse
    {
        $data = $request->validate([
            'filename' => ['required', 'string'],
            'platform' => ['nullable', 'string'],
            'preset' => ['nullable', 'string'],
        ]);

        $path = $studio->designsDir($project).DIRECTORY_SEPARATOR.basename($data['filename']);
        if (! file_exists($path)) {
            return response()->json(['message' => 'Arquivo não encontrado. Exporte o design primeiro.'], 404);
        }

        $result = $studio->pushToThumbnail(
            $project,
            $path,
            $data['platform'] ?? null,
            $data['preset'] ?? null
        );

        return response()->json([
            'message' => 'Arte do Image Studio vinculada ao módulo Thumbnail.',
            'thumbnail' => $result,
            'platform' => $result['platform'],
            'settings' => $result['settings'],
        ]);
    }

    public function pushLibrary(Request $request, Project $project, ImageStudioService $studio): JsonResponse
    {
        $data = $request->validate([
            'filename' => ['required', 'string'],
            'preset' => ['nullable', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $studio->designsDir($project).DIRECTORY_SEPARATOR.basename($data['filename']);
        if (! file_exists($path)) {
            return response()->json(['message' => 'Arquivo não encontrado.'], 404);
        }

        $asset = $studio->pushToAssetLibrary(
            $project,
            $path,
            $data['title'] ?? $data['filename'],
            $data['preset'] ?? null
        );

        $formatted = app(AssetController::class)->formatAsset($asset, $project);

        return response()->json([
            'message' => 'Adicionado à biblioteca do projeto.',
            'asset' => $formatted,
        ]);
    }

    public function framePreview(Request $request, Project $project, ImageStudioService $studio): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string'],
            'width' => ['required', 'integer', 'min:100', 'max:8000'],
            'height' => ['required', 'integer', 'min:100', 'max:8000'],
            'color' => ['nullable', 'string', 'max:32'],
            'secondary_color' => ['nullable', 'string', 'max:32'],
            'frame_width' => ['nullable', 'integer', 'min:4', 'max:200'],
            'opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'inset' => ['nullable', 'integer', 'min:0', 'max:80'],
        ]);

        $result = $studio->renderFramePreview(
            $project,
            (int) $data['width'],
            (int) $data['height'],
            $data['slug'],
            [
                'color' => $data['color'] ?? null,
                'secondary_color' => $data['secondary_color'] ?? null,
                'frame_width' => $data['frame_width'] ?? null,
                'opacity' => $data['opacity'] ?? null,
                'inset' => $data['inset'] ?? null,
            ]
        );

        return response()->json($result);
    }
}
