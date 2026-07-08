<?php

namespace App\Services\MediaLibrary;

use App\Enums\LicenseType;
use App\Models\Asset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class PexelsService
{
    public function __construct(private ProjectStorageService $storage) {}

    public function searchPhotos(string $query, int $page = 1, int $perPage = 15): array
    {
        $apiKey = config('criasys.media.pexels_api_key');
        if (! $apiKey) {
            throw new \RuntimeException('PEXELS_API_KEY não configurada no .env');
        }

        $response = Http::withHeaders(['Authorization' => $apiKey])
            ->get('https://api.pexels.com/v1/search', [
                'query' => $query,
                'page' => $page,
                'per_page' => $perPage,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Pexels API erro: '.$response->body());
        }

        return collect($response->json('photos', []))->map(function (array $photo) {
            return [
                'id' => $photo['id'],
                'source' => 'pexels',
                'type' => 'image',
                'preview_url' => $photo['src']['medium'] ?? $photo['src']['small'],
                'download_url' => $photo['src']['large2x'] ?? $photo['src']['large'],
                'photographer' => $photo['photographer'] ?? 'Desconhecido',
                'original_url' => $photo['url'] ?? null,
                'license_type' => LicenseType::Pexels->value,
                'requires_attribution' => false,
                'attribution_text' => isset($photo['photographer'])
                    ? "Foto por {$photo['photographer']} no Pexels"
                    : null,
            ];
        })->all();
    }

    public function downloadToProject(Project $project, array $photoData): Asset
    {
        $this->storage->ensureStructure($project);
        $url = $photoData['download_url'];
        $contents = Http::timeout(60)->get($url)->body();
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
