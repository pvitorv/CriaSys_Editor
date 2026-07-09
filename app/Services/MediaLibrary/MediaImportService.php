<?php

namespace App\Services\MediaLibrary;

use App\Enums\LicenseType;
use App\Models\Asset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use App\Support\ExternalHttp;
use Illuminate\Support\Facades\File;

class MediaImportService
{
    public function __construct(
        private ProjectStorageService $storage,
        private UnsplashService $unsplash,
    ) {}

    private function createAsset(Project $project, array $item, string $type, string $path, string $hash, array $extra = []): Asset
    {
        $attribution = MediaAttribution::fromSearchItem($item);
        $source = $item['source'] ?? null;

        if (! $source || $source === 'unknown') {
            $source = ! empty($attribution['attribution_text']) ? 'library' : 'local';
        }

        if ($source === 'library' && ! empty($item['download_url'])) {
            $source = $this->inferSourceFromUrl((string) $item['download_url']) ?? 'mixkit';
        }

        return Asset::create(array_merge([
            'project_id' => $project->id,
            'type' => $type,
            'file_path' => $path,
            'file_hash' => $hash,
            'source' => $source,
            'license_type' => $item['license_type'] ?? LicenseType::Local->value,
            'requires_attribution' => $attribution['requires_attribution'],
            'attribution_text' => $attribution['attribution_text'],
            'original_url' => $item['original_url'] ?? null,
            'downloaded_at' => now(),
        ], $extra));
    }

    private function inferSourceFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        return match (true) {
            str_contains($host, 'pexels') => 'pexels',
            str_contains($host, 'pixabay') => 'pixabay',
            str_contains($host, 'unsplash') => 'unsplash',
            str_contains($host, 'mixkit') => 'mixkit',
            str_contains($host, 'openverse') => 'openverse',
            default => null,
        };
    }

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

        $contents = ExternalHttp::client(60)->get($url)->body();
        $hash = hash('sha256', $contents);
        $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
        $filename = ($item['source'] ?? 'media').'_'.($item['id'] ?? uniqid()).'_'.substr($hash, 0, 8).'.'.$ext;
        $path = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$filename;

        File::put($path, $contents);

        return $this->createAsset($project, $item, 'image', $path, $hash, [
            'metadata' => [
                'external_id' => $item['id'] ?? null,
                'import_source' => $item['source'] ?? null,
                'author' => $item['author'] ?? $item['photographer'] ?? null,
                'title' => $item['title'] ?? null,
            ],
        ]);
    }

    public function importAudio(Project $project, array $item): Asset
    {
        $this->storage->ensureStructure($project);
        $url = $item['download_url'] ?? null;

        if (! $url) {
            throw new \RuntimeException('URL de áudio indisponível.');
        }

        $contents = ExternalHttp::client(120)->get($url)->body();
        $hash = hash('sha256', $contents);
        $filename = ($item['source'] ?? 'audio').'_'.($item['id'] ?? uniqid()).'_'.substr($hash, 0, 8).'.mp3';
        $path = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$filename;

        File::put($path, $contents);

        return $this->createAsset($project, $item, 'audio', $path, $hash, [
            'metadata' => [
                'title' => $item['title'] ?? null,
                'import_source' => $item['source'] ?? null,
                'author' => $item['author'] ?? null,
            ],
        ]);
    }

    public function importVideo(Project $project, array $item): Asset
    {
        $this->storage->ensureStructure($project);
        $url = $item['download_url'] ?? null;

        if (! $url) {
            throw new \RuntimeException('URL de vídeo indisponível.');
        }

        $contents = ExternalHttp::client(180)->get($url)->body();
        $hash = hash('sha256', $contents);
        $filename = ($item['source'] ?? 'video').'_'.($item['id'] ?? uniqid()).'_'.substr($hash, 0, 8).'.mp4';
        $path = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$filename;

        File::put($path, $contents);

        return $this->createAsset($project, $item, 'video', $path, $hash, [
            'metadata' => [
                'external_id' => $item['id'] ?? null,
                'duration_seconds' => $item['duration_seconds'] ?? null,
                'import_source' => $item['source'] ?? null,
                'author' => $item['author'] ?? null,
                'title' => $item['title'] ?? null,
            ],
        ]);
    }
}
