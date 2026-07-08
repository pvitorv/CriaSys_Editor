<?php

namespace App\Services\MediaLibrary;

use App\Enums\LicenseType;
use App\Models\Asset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class MediaImportService
{
    public function __construct(
        private ProjectStorageService $storage,
        private UnsplashService $unsplash,
    ) {}

    public function importImage(Project $project, array $item): Asset
    {
        $this->storage->ensureStructure($project);

        if (($item['source'] ?? '') === 'unsplash' && ! empty($item['download_location'])) {
            $this->unsplash->triggerDownload($item['download_location']);
        }

        $url = $item['download_url'] ?? null;
        if (! $url) {
            throw new \RuntimeException('URL de download indisponível.');
        }

        $contents = Http::timeout(60)->get($url)->body();
        $hash = hash('sha256', $contents);
        $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
        $filename = ($item['source'] ?? 'media').'_'.($item['id'] ?? uniqid()).'_'.substr($hash, 0, 8).'.'.$ext;
        $path = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$filename;

        File::put($path, $contents);

        return Asset::create([
            'project_id' => $project->id,
            'type' => 'image',
            'file_path' => $path,
            'file_hash' => $hash,
            'source' => $item['source'] ?? 'unknown',
            'license_type' => $item['license_type'] ?? LicenseType::Local->value,
            'requires_attribution' => (bool) ($item['requires_attribution'] ?? false),
            'attribution_text' => $item['attribution_text'] ?? null,
            'original_url' => $item['original_url'] ?? null,
            'metadata' => ['external_id' => $item['id'] ?? null],
            'downloaded_at' => now(),
        ]);
    }

    public function importAudio(Project $project, array $item): Asset
    {
        $this->storage->ensureStructure($project);
        $url = $item['download_url'] ?? null;

        if (! $url) {
            throw new \RuntimeException('URL de áudio indisponível.');
        }

        $contents = Http::timeout(120)->get($url)->body();
        $hash = hash('sha256', $contents);
        $filename = ($item['source'] ?? 'audio').'_'.($item['id'] ?? uniqid()).'_'.substr($hash, 0, 8).'.mp3';
        $path = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$filename;

        File::put($path, $contents);

        return Asset::create([
            'project_id' => $project->id,
            'type' => 'audio',
            'file_path' => $path,
            'file_hash' => $hash,
            'source' => $item['source'] ?? 'unknown',
            'license_type' => $item['license_type'] ?? LicenseType::Local->value,
            'requires_attribution' => (bool) ($item['requires_attribution'] ?? false),
            'attribution_text' => $item['attribution_text'] ?? null,
            'original_url' => $item['original_url'] ?? null,
            'metadata' => ['title' => $item['title'] ?? null],
            'downloaded_at' => now(),
        ]);
    }
}
