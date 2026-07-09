<?php

namespace App\Services\MediaLibrary;

use App\Enums\LicenseType;
use App\Support\ExternalHttp;

/**
 * Openverse (Creative Commons) — busca gratuita sem API key.
 */
class OpenverseService
{
    public function searchImages(string $query, int $page = 1, int $perPage = 15): array
    {
        $response = ExternalHttp::client(25)->get('https://api.openverse.org/v1/images/', [
            'q' => $query,
            'page' => $page,
            'page_size' => $perPage,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Openverse indisponível (HTTP '.$response->status().').');
        }

        return collect($response->json('results', []))->map(function (array $item) {
            $creator = $item['creator'] ?? 'Desconhecido';
            $license = strtoupper($item['license'] ?? 'CC0');
            $attribution = MediaAttribution::forOpenverseImage($item);

            return [
                'id' => $item['id'] ?? md5($item['url'] ?? uniqid()),
                'source' => 'openverse',
                'type' => 'image',
                'title' => $item['title'] ?? null,
                'preview_url' => $item['thumbnail'] ?? $item['url'],
                'download_url' => $item['url'],
                'author' => $creator,
                'original_url' => $item['foreign_landing_url'] ?? $item['detail_url'] ?? null,
                'license_type' => $license === 'PDM' ? LicenseType::Cc0->value : $license,
                'requires_attribution' => $attribution['requires_attribution'],
                'attribution_text' => $attribution['attribution_text'],
            ];
        })->filter(fn ($item) => ! empty($item['download_url']))->values()->all();
    }
}
