<?php

namespace App\Services\MediaLibrary;

use App\Enums\LicenseType;
use Illuminate\Support\Facades\Http;

class UnsplashService
{
    public function searchPhotos(string $query, int $page = 1, int $perPage = 15): array
    {
        $accessKey = config('criasys.media.unsplash_access_key');
        if (! $accessKey) {
            throw new \RuntimeException('UNSPLASH_ACCESS_KEY não configurada no .env');
        }

        $response = Http::withHeaders(['Authorization' => "Client-ID {$accessKey}"])
            ->get('https://api.unsplash.com/search/photos', [
                'query' => $query,
                'page' => $page,
                'per_page' => $perPage,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Unsplash API erro: '.$response->body());
        }

        return collect($response->json('results', []))->map(function (array $photo) {
            $user = $photo['user']['name'] ?? 'Desconhecido';
            $username = $photo['user']['username'] ?? '';

            return [
                'id' => $photo['id'],
                'source' => 'unsplash',
                'type' => 'image',
                'preview_url' => $photo['urls']['small'] ?? $photo['urls']['thumb'],
                'download_url' => $photo['urls']['regular'] ?? $photo['urls']['full'],
                'download_location' => $photo['links']['download_location'] ?? null,
                'author' => $user,
                'original_url' => $photo['links']['html'] ?? null,
                'license_type' => LicenseType::Cc0->value,
                'requires_attribution' => true,
                'attribution_text' => "Foto por {$user} (@{$username}) no Unsplash",
            ];
        })->all();
    }

    public function triggerDownload(string $downloadLocation): void
    {
        $accessKey = config('criasys.media.unsplash_access_key');
        if ($downloadLocation && $accessKey) {
            Http::withHeaders(['Authorization' => "Client-ID {$accessKey}"])
                ->get($downloadLocation);
        }
    }
}
