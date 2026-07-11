<?php

namespace App\Http\Controllers\Api;

use App\Enums\LicenseType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AudioTrack;
use App\Models\Project;
use App\Models\ProjectStockLicense;
use App\Models\SoundEffect;
use App\Services\MediaLibrary\MediaAttribution;
use App\Services\ProjectStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController extends Controller
{
    public function __construct(private ProjectStorageService $storage) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $type = $request->query('type');

        $query = $project->assets()->orderByDesc('id');
        if ($type) {
            $query->where('type', $type);
        } else {
            $query->whereIn('type', ['image', 'video']);
        }

        $assets = $this->dedupeAssets($query->get())
            ->map(fn (Asset $asset) => $this->formatAsset($asset, $project))
            ->values();

        return response()->json(['assets' => $assets]);
    }

    public function destroy(Project $project, Asset $asset): JsonResponse
    {
        abort_unless($asset->project_id === $project->id, 404);

        $duplicates = $this->duplicateAssets($project, $asset);
        $paths = $duplicates->pluck('file_path')->filter()->unique()->values();
        $ids = $duplicates->pluck('id');

        Asset::whereIn('id', $ids)->delete();

        $filesDeleted = 0;
        foreach ($paths as $path) {
            if ($this->filePathInUse($project, $path)) {
                continue;
            }
            if ($path && file_exists($path)) {
                File::delete($path);
                $filesDeleted++;
            }
        }

        app(\App\Services\Export\ProjectPublishAutoSyncService::class)->sync($project);

        $stillInUse = $paths->contains(fn (string $path) => $this->filePathInUse($project, $path));

        return response()->json([
            'deleted' => true,
            'removed_records' => $ids->count(),
            'files_deleted' => $filesDeleted,
            'message' => $stillInUse
                ? 'Removido da biblioteca. O arquivo continua no disco porque ainda está em uso no projeto.'
                : 'Removido da biblioteca do projeto.',
        ]);
    }

    public function upload(Request $request, Project $project): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'type' => ['nullable', 'in:image,audio,video'],
            'stock_license_id' => ['nullable', 'integer', 'exists:project_stock_licenses,id'],
            'item_title' => ['nullable', 'string', 'max:255'],
            'item_external_id' => ['nullable', 'string', 'max:128'],
            'author' => ['nullable', 'string', 'max:255'],
            'attribution_text' => ['nullable', 'string', 'max:2000'],
            'requires_attribution' => ['nullable', 'boolean'],
            'original_url' => ['nullable', 'url', 'max:2000'],
            'license_type' => ['nullable', 'string', 'max:64'],
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
        $attributionText = $request->input('attribution_text');
        $requiresAttribution = $request->boolean('requires_attribution');
        $originalUrl = $request->input('original_url');

        if ($stockLicense) {
            $licenseType = $this->licenseTypeForProvider($stockLicense->provider);
            $source = $stockLicense->provider;
            $attribution = MediaAttribution::forPaidSubscription($stockLicense, new Asset([
                'item_title' => $itemTitle,
                'item_external_id' => $request->input('item_external_id'),
            ]));
            $attributionText = $attribution['attribution_text'];
            $requiresAttribution = true;
        } elseif ($attributionText) {
            $licenseType = LicenseType::tryFrom((string) $request->input('license_type')) ?? LicenseType::CustomLicensed;
            $source = 'external';
            $requiresAttribution = true;
        } elseif ($request->filled('author')) {
            $author = trim((string) $request->input('author'));
            $attributionText = $originalUrl
                ? "Por {$author} — {$originalUrl}"
                : "Por {$author}";
            $licenseType = LicenseType::tryFrom((string) $request->input('license_type')) ?? LicenseType::CustomLicensed;
            $source = 'external';
            $requiresAttribution = true;
        }

        $metadata = [];
        if ($request->filled('author')) {
            $metadata['author'] = trim((string) $request->input('author'));
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
            'original_url' => $originalUrl,
            'metadata' => $metadata ?: null,
            'downloaded_at' => now(),
        ]);

        app(\App\Services\Export\ProjectPublishAutoSyncService::class)->sync($project);

        return response()->json($this->formatAsset($asset->load('stockLicense'), $project), 201);
    }

    /** @return array<string, mixed> */
    public function formatAsset(Asset $asset, Project $project): array
    {
        $url = route('api.projects.assets', ['project' => $project->id, 'asset' => $asset->id]);

        return [
            'id' => $asset->id,
            'type' => $asset->type,
            'source' => $asset->source,
            'item_title' => $asset->item_title,
            'file_path' => $asset->file_path,
            'file_hash' => $asset->file_hash,
            'url' => $url,
            'preview_url' => in_array($asset->type, ['image', 'video'], true) ? $url : null,
            'metadata' => $asset->metadata ?? [],
            'license_type' => $asset->license_type,
            'requires_attribution' => (bool) $asset->requires_attribution,
            'attribution_text' => $asset->attribution_text,
            'original_url' => $asset->original_url,
            'created_at' => $asset->created_at?->toIso8601String(),
        ];
    }

    /** @param \Illuminate\Support\Collection<int, Asset> $assets */
    private function dedupeAssets($assets)
    {
        $seen = [];

        return $assets->filter(function (Asset $asset) use (&$seen) {
            $key = $asset->file_hash ?: $asset->file_path ?: (string) $asset->id;
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        })->values();
    }

    /** @return \Illuminate\Support\Collection<int, Asset> */
    private function duplicateAssets(Project $project, Asset $asset)
    {
        $query = $project->assets()->where('type', $asset->type);

        if ($asset->file_hash) {
            $query->where('file_hash', $asset->file_hash);
        } elseif ($asset->file_path) {
            $query->where('file_path', $asset->file_path);
        } else {
            $query->whereKey($asset->id);
        }

        return $query->orderByDesc('id')->get();
    }

    private function filePathInUse(Project $project, string $path): bool
    {
        if ($project->slides()->where(function ($query) use ($path) {
            $query->where('image_path', $path)->orWhere('video_path', $path);
        })->exists()) {
            return true;
        }

        if (Asset::where('project_id', $project->id)->where('file_path', $path)->exists()) {
            return true;
        }

        if (AudioTrack::where('project_id', $project->id)->where('file_path', $path)->exists()) {
            return true;
        }

        if (SoundEffect::where('project_id', $project->id)->where('file_path', $path)->exists()) {
            return true;
        }

        return false;
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

        $mime = match (strtolower(pathinfo($asset->file_path, PATHINFO_EXTENSION))) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'm4a', 'aac' => 'audio/mp4',
            'webm' => 'audio/webm',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            default => null,
        };

        return response()->file($asset->file_path, array_filter([
            'Content-Type' => $mime,
        ]));
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
                'ogg' => 'audio/ogg',
                'm4a', 'aac' => 'audio/mp4',
                'webm' => 'audio/webm',
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
