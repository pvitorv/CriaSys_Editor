<?php

namespace App\Http\Controllers\Api;

use App\Enums\LicenseType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController extends Controller
{
    public function __construct(private ProjectStorageService $storage) {}

    public function upload(Request $request, Project $project): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'type' => ['nullable', 'in:image,audio'],
        ]);

        $type = $request->input('type', 'image');
        $mime = $request->file('file')->getMimeType() ?? '';
        if ($type === 'image') {
            $request->validate(['file' => ['image']]);
        } elseif (! str_starts_with($mime, 'audio/')) {
            return response()->json(['message' => 'Arquivo deve ser de áudio.'], 422);
        }

        $this->storage->ensureStructure($project);
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());
        $filename = 'local_'.$hash.'.'.$file->getClientOriginalExtension();
        $path = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$filename;

        $file->move(dirname($path), basename($path));

        $asset = Asset::create([
            'project_id' => $project->id,
            'type' => $type,
            'file_path' => $path,
            'file_hash' => $hash,
            'source' => 'local',
            'license_type' => LicenseType::Local->value,
            'requires_attribution' => false,
            'downloaded_at' => now(),
        ]);

        return response()->json($asset, 201);
    }

    public function serve(Project $project, Asset $asset): BinaryFileResponse
    {
        abort_unless($asset->project_id === $project->id, 404);
        abort_unless(file_exists($asset->file_path), 404);

        return response()->file($asset->file_path);
    }

    public function serveFile(Project $project, string $type, string $filename): BinaryFileResponse
    {
        $allowed = ['audio', 'exports', 'thumbs', 'slides', 'assets'];
        abort_unless(in_array($type, $allowed, true), 404);

        $path = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.$type.DIRECTORY_SEPARATOR.$filename;
        abort_unless(file_exists($path), 404);

        return response()->file($path, [
            'Content-Type' => match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'mp4' => 'video/mp4',
                'srt' => 'text/plain',
                'zip' => 'application/zip',
                default => null,
            },
        ]);
    }
}
