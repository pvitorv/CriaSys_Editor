<?php

namespace App\Http\Controllers\Api;

use App\Enums\LicenseType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Project;
use App\Models\ProjectStockLicense;
use App\Services\MediaLibrary\MediaAttribution;
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
            'type' => ['nullable', 'in:image,audio,video'],
            'stock_license_id' => ['nullable', 'integer', 'exists:project_stock_licenses,id'],
            'item_title' => ['nullable', 'string', 'max:255'],
            'item_external_id' => ['nullable', 'string', 'max:128'],
        ]);

        $type = $request->input('type', 'image');
        $mime = $request->file('file')->getMimeType() ?? '';
        if ($type === 'image') {
            $request->validate(['file' => ['image']]);
        } elseif ($type === 'video') {
            if (! str_starts_with($mime, 'video/')) {
                return response()->json(['message' => 'Arquivo deve ser de vídeo.'], 422);
            }
        } elseif (! str_starts_with($mime, 'audio/')) {
            return response()->json(['message' => 'Arquivo deve ser de áudio.'], 422);
        }

        $this->storage->ensureStructure($project);
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());
        $filename = 'local_'.$hash.'.'.$file->getClientOriginalExtension();
        $path = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$filename;

        $file->move(dirname($path), basename($path));

        $stockLicense = $this->resolveStockLicense($project, $request->integer('stock_license_id') ?: null);
        if ($request->filled('stock_license_id') && ! $stockLicense) {
            return response()->json(['message' => 'Licença não pertence a este projeto.'], 422);
        }

        $itemTitle = $request->input('item_title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $licenseType = LicenseType::Local;
        $source = 'local';
        $attributionText = null;
        $requiresAttribution = false;

        if ($stockLicense) {
            $licenseType = $this->licenseTypeForProvider($stockLicense->provider);
            $source = $stockLicense->provider;
            $attribution = MediaAttribution::forPaidSubscription($stockLicense, new Asset([
                'item_title' => $itemTitle,
                'item_external_id' => $request->input('item_external_id'),
            ]));
            $attributionText = $attribution['attribution_text'];
            $requiresAttribution = true;
        }

        $asset = Asset::create([
            'project_id' => $project->id,
            'stock_license_id' => $stockLicense?->id,
            'type' => $type,
            'file_path' => $path,
            'file_hash' => $hash,
            'source' => $source,
            'item_title' => $itemTitle,
            'item_external_id' => $request->input('item_external_id'),
            'license_type' => $licenseType->value,
            'requires_attribution' => $requiresAttribution,
            'attribution_text' => $attributionText,
            'downloaded_at' => now(),
        ]);

        app(\App\Services\Export\ProjectPublishAutoSyncService::class)->sync($project);

        return response()->json($asset->load('stockLicense'), 201);
    }

    private function resolveStockLicense(Project $project, ?int $id): ?ProjectStockLicense
    {
        if ($id) {
            $license = $project->stockLicenses()->find($id);

            return $license ?: null;
        }

        return $project->defaultStockLicense();
    }

    private function licenseTypeForProvider(string $provider): LicenseType
    {
        return match ($provider) {
            'envato' => LicenseType::Envato,
            'storyblocks' => LicenseType::Storyblocks,
            'artgrid' => LicenseType::Artgrid,
            default => LicenseType::CustomLicensed,
        };
    }

    public function serve(Project $project, Asset $asset): BinaryFileResponse
    {
        abort_unless($asset->project_id === $project->id, 404);
        abort_unless(file_exists($asset->file_path), 404);

        return response()->file($asset->file_path);
    }

    public function serveFile(Project $project, string $type, string $filename): BinaryFileResponse
    {
        $allowed = ['audio', 'exports', 'thumbs', 'slides', 'assets', 'designs'];
        abort_unless(in_array($type, $allowed, true), 404);

        $path = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.$type.DIRECTORY_SEPARATOR.$filename;
        abort_unless(file_exists($path), 404);

        return response()->file($path, [
            'Content-Type' => match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                'json' => 'application/json',
                'mp4' => 'video/mp4',
                'srt' => 'text/plain',
                'zip' => 'application/zip',
                default => null,
            },
        ]);
    }
}
