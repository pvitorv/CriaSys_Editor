<?php

namespace App\Services\MediaLibrary;

use App\Enums\LicenseType;
use Illuminate\Support\Facades\Http;

/**
 * Openverse (Creative Commons) — busca gratuita sem API key.
 * @see https://api.openverse.org/v1/
 */
class OpenverseService
{
    public function searchImages(string $query, int $page = 1, int $perPage = 15): array
    {
        $response = Http::timeout(20)
            ->withHeaders(['User-Agent' => 'CriaSys-Editor/1.0'])
            ->get('https://api.openverse.org/v1/images/', [
                'q' => $query,
                'page' => $page,
                'page_size' => $perPage,
                'license' => 'cc0,pdm',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Não foi possível buscar imagens gratuitas agora.');
        }

        return collect($response->json('results', []))->map(function (array $item) {
            $creator = $item['creator'] ?? 'Desconhecido';
            $license = strtoupper($item['license'] ?? 'CC0');
            $requiresAttribution = ! in_array($license, ['CC0', 'PDM'], true);

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
                'requires_attribution' => $requiresAttribution,
                'attribution_text' => $requiresAttribution
                    ? "Imagem por {$creator} — licença {$license} (Openverse)"
                    : null,
            ];
        })->filter(fn ($item) => ! empty($item['download_url']))->values()->all();
    }
}
