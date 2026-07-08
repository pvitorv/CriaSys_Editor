<?php

namespace App\Services\MediaLibrary;

use App\Enums\LicenseType;
use Illuminate\Support\Facades\Http;

class PixabayService
{
    public function searchImages(string $query, int $page = 1, int $perPage = 15): array
    {
        $apiKey = config('criasys.media.pixabay_api_key');
        if (! $apiKey) {
            throw new \RuntimeException('PIXABAY_API_KEY não configurada no .env');
        }

        $response = Http::get('https://pixabay.com/api/', [
            'key' => $apiKey,
            'q' => $query,
            'image_type' => 'photo',
            'page' => $page,
            'per_page' => $perPage,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Pixabay API erro: '.$response->body());
        }

        return collect($response->json('hits', []))->map(function (array $hit) {
            $user = $hit['user'] ?? 'Desconhecido';

            return [
                'id' => $hit['id'],
                'source' => 'pixabay',
                'type' => 'image',
                'preview_url' => $hit['previewURL'] ?? $hit['webformatURL'],
                'download_url' => $hit['largeImageURL'] ?? $hit['webformatURL'],
                'author' => $user,
                'original_url' => $hit['pageURL'] ?? null,
                'license_type' => LicenseType::Pixabay->value,
                'requires_attribution' => false,
                'attribution_text' => "Imagem por {$user} no Pixabay",
            ];
        })->all();
    }

    public function searchAudio(string $query, int $page = 1): array
    {
        $apiKey = config('criasys.media.pixabay_api_key');
        if (! $apiKey) {
            throw new \RuntimeException('PIXABAY_API_KEY não configurada no .env');
        }

        $response = Http::get('https://pixabay.com/api/videos/', [
            'key' => $apiKey,
            'q' => $query,
            'page' => $page,
            'per_page' => 15,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Pixabay vídeos/áudio erro: '.$response->body());
        }

        return collect($response->json('hits', []))->map(function (array $hit) {
            $videos = $hit['videos'] ?? [];
            $medium = $videos['medium'] ?? $videos['small'] ?? [];
            $user = $hit['user'] ?? 'Desconhecido';

            return [
                'id' => $hit['id'],
                'source' => 'pixabay',
                'type' => 'audio',
                'preview_url' => $hit['picture_id'] ? "https://i.vimeocdn.com/video/{$hit['picture_id']}_640x360.jpg" : null,
                'download_url' => $medium['url'] ?? null,
                'author' => $user,
                'original_url' => $hit['pageURL'] ?? null,
                'license_type' => LicenseType::Pixabay->value,
                'requires_attribution' => false,
                'attribution_text' => "Áudio/vídeo por {$user} no Pixabay",
                'note' => 'Extraia áudio do vídeo ou use como B-roll',
            ];
        })->filter(fn ($item) => $item['download_url'])->values()->all();
    }
}
