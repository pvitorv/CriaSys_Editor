<?php

namespace App\Services\MediaLibrary;

use App\Enums\LicenseType;
use App\Models\Asset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use App\Support\ExternalHttp;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class PexelsService
{
    public function __construct(private ProjectStorageService $storage) {}

    public function searchPhotos(string $query, int $page = 1, int $perPage = 15): array
    {
        $apiKey = config('criasys.media.pexels_api_key');
        if (! $apiKey) {
            throw new \RuntimeException('PEXELS_API_KEY não configurada no .env');
        }

        $response = ExternalHttp::client()->withHeaders(['Authorization' => $apiKey])
            ->get('https://api.pexels.com/v1/search', [
                'query' => $query,
                'page' => $page,
                'per_page' => $perPage,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Pexels API erro: '.$response->body());
        }

        return collect($response->json('photos', []))->map(function (array $photo) {
            $attribution = MediaAttribution::forPexelsPhoto($photo);

            return [
                'id' => $photo['id'],
                'source' => 'pexels',
                'type' => 'image',
                'preview_url' => $photo['src']['medium'] ?? $photo['src']['small'],
                'download_url' => $photo['src']['large2x'] ?? $photo['src']['large'],
                'photographer' => $photo['photographer'] ?? 'Desconhecido',
                'original_url' => $photo['url'] ?? null,
                'license_type' => LicenseType::Pexels->value,
                'requires_attribution' => $attribution['requires_attribution'],
                'attribution_text' => $attribution['attribution_text'],
            ];
        })->all();
    }

    public function searchVideos(string $query, int $page = 1, int $perPage = 15): array
    {
        $apiKey = config('criasys.media.pexels_api_key');
        if (! $apiKey) {
            throw new \RuntimeException('PEXELS_API_KEY não configurada no .env');
        }

        $response = ExternalHttp::client()->withHeaders(['Authorization' => $apiKey])
            ->get('https://api.pexels.com/videos/search', [
                'query' => $query,
                'page' => $page,
                'per_page' => $perPage,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Pexels vídeos API erro: '.$response->body());
        }

        return collect($response->json('videos', []))->map(function (array $video) {
            $files = collect($video['video_files'] ?? [])->sortByDesc('width');
            $file = $files->first(fn (array $f) => ($f['width'] ?? 0) <= 1920) ?? $files->first();
            $attribution = MediaAttribution::forPexelsVideo($video);

            return [
                'id' => $video['id'],
                'source' => 'pexels',
                'type' => 'video',
                'preview_url' => $video['image'] ?? null,
                'download_url' => $file['link'] ?? null,
                'duration_seconds' => $video['duration'] ?? null,
                'author' => $video['user']['name'] ?? 'Pexels',
                'original_url' => $video['url'] ?? null,
                'license_type' => LicenseType::Pexels->value,
                'requires_attribution' => $attribution['requires_attribution'],
                'attribution_text' => $attribution['attribution_text'],
            ];
        })->filter(fn (array $item) => ! empty($item['download_url']))->values()->all();
    }

    public function downloadToProject(Project $project, array $photoData): Asset
    {
        $this->storage->ensureStructure($project);
        $url = $photoData['download_url'];
        $contents = ExternalHttp::client(60)->get($url)->body();
        $hash = hash('sha256', $contents);
        $filename = 'pexels_'.$photoData['id'].'_'.substr($hash, 0, 8).'.jpg';
        $path = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$filename;

        File::put($path, $contents);

        return Asset::create([
            'project_id' => $project->id,
            'type' => 'image',
            'file_path' => $path,
            'file_hash' => $hash,
            'source' => 'pexels',
            'license_type' => LicenseType::Pexels->value,
            'requires_attribution' => $photoData['requires_attribution'] ?? false,
            'attribution_text' => $photoData['attribution_text'] ?? null,
            'original_url' => $photoData['original_url'] ?? null,
            'metadata' => ['pexels_id' => $photoData['id']],
            'downloaded_at' => now(),
        ]);
    }
}
