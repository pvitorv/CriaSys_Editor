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
            'item_title' => $item['title'] ?? null,
            'item_external_id' => isset($item['id']) ? (string) $item['id'] : null,
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
            str_contains($host, 'freesound') => 'freesound',
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
        return $this->importAudioFile($project, $item, 'audio');
    }

    public function importSfx(Project $project, array $item): Asset
    {
        return $this->importAudioFile($project, $item, 'audio');
    }

    private function importAudioFile(Project $project, array $item, string $type): Asset
    {
        $this->storage->ensureStructure($project);

        [$contents, $url] = $this->downloadAudioContents($item);
        $hash = hash('sha256', $contents);
        $ext = $this->detectAudioExtension($url, $contents);
        $filename = ($item['source'] ?? 'audio').'_'.($item['id'] ?? uniqid()).'_'.substr($hash, 0, 8).'.'.$ext;
        $path = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$filename;

        File::put($path, $contents);

        return $this->createAsset($project, $item, $type, $path, $hash, [
            'metadata' => [
                'title' => $item['title'] ?? null,
                'import_source' => $item['source'] ?? null,
                'author' => $item['author'] ?? null,
                'subtype' => $item['subtype'] ?? ($item['type'] ?? null),
                'duration_seconds' => $item['duration_seconds'] ?? null,
                'download_url' => $url,
            ],
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function downloadAudioContents(array $item): array
    {
        $candidates = $this->audioDownloadCandidates($item);
        $lastError = null;

        foreach ($candidates as $url) {
            try {
                $response = ExternalHttp::client(120)->get($url);
                if (! $response->successful()) {
                    $lastError = 'HTTP '.$response->status();
                    continue;
                }

                $body = $response->body();
                if ($this->isValidAudioContents($body)) {
                    return [$body, $url];
                }

                $lastError = 'conteúdo inválido';
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new \RuntimeException(
            'Não foi possível baixar o áudio'
            .($lastError ? " ({$lastError})" : '')
            .'. Tente outro efeito ou envie seu arquivo em Meu arquivo.'
        );
    }

    /** @return list<string> */
    private function audioDownloadCandidates(array $item): array
    {
        $candidates = [];

        foreach (['download_url', 'preview_url'] as $key) {
            if (! empty($item[$key])) {
                $candidates[] = (string) $item[$key];
            }
        }

        foreach (array_filter([$item['preview_url'] ?? null, $item['download_url'] ?? null]) as $url) {
            if (! str_contains($url, 'assets.mixkit.co/active_storage/sfx/')) {
                continue;
            }

            if (! preg_match('#/sfx/(\d+)/#', $url, $match)) {
                continue;
            }

            $id = $match[1];
            $base = "https://assets.mixkit.co/active_storage/sfx/{$id}/{$id}";
            $candidates[] = "{$base}-preview.mp3";
            $candidates[] = "{$base}-preview.wav";
            $candidates[] = "{$base}.mp3";
            $candidates[] = "{$base}.wav";
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function isValidAudioContents(string $contents): bool
    {
        if (strlen($contents) < 512) {
            return false;
        }

        $trimmed = ltrim($contents);
        if (str_starts_with($trimmed, '<?xml')
            || str_starts_with($trimmed, '<!DOCTYPE')
            || str_starts_with(strtolower($trimmed), '<html')) {
            return false;
        }

        $head = substr($contents, 0, 32);

        return str_starts_with($head, 'ID3')
            || str_starts_with($head, "\xFF\xFB")
            || str_starts_with($head, "\xFF\xF3")
            || (str_starts_with($head, 'RIFF') && str_contains($head, 'WAVE'))
            || str_starts_with($head, 'OggS')
            || str_starts_with($head, 'fLaC')
            || str_contains(substr($contents, 0, 12), 'ftyp');
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

    private function detectAudioExtension(string $url, string $contents): string
    {
        $head = substr($contents, 0, 16);
        if (str_starts_with($head, 'ID3') || str_starts_with($head, "\xFF\xFB") || str_starts_with($head, "\xFF\xF3")) {
            return 'mp3';
        }
        if (str_starts_with($head, 'RIFF') && str_contains($head, 'WAVE')) {
            return 'wav';
        }
        if (str_starts_with($head, 'OggS')) {
            return 'ogg';
        }
        if (str_contains(substr($contents, 0, 12), 'ftyp')) {
            return 'm4a';
        }

        $pathExt = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (in_array($pathExt, ['mp3', 'wav', 'ogg', 'm4a', 'webm'], true)) {
            return $pathExt;
        }
        if ($pathExt === 'aac') {
            return 'm4a';
        }

        return 'mp3';
    }
}
