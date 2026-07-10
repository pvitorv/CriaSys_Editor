<?php

namespace App\Services\MediaLibrary;

use App\Enums\LicenseType;
use App\Support\ExternalHttp;

class FreesoundService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function searchMusic(string $query, int $page = 1): array
    {
        return $this->search($query, $page, 'music');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchSfx(string $query, int $page = 1): array
    {
        return $this->search($query, $page, 'sfx');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function search(string $query, int $page, string $mode): array
    {
        $apiKey = config('criasys.media.freesound_api_key');
        if (! $apiKey) {
            throw new \RuntimeException('FREESOUND_API_KEY não configurada no .env');
        }

        $filter = $mode === 'sfx'
            ? 'duration:[0 TO 15]'
            : 'duration:[15 TO 600]';

        $response = ExternalHttp::client(25)->get('https://freesound.org/apiv2/search/text/', [
            'query' => $query,
            'filter' => $filter,
            'fields' => 'id,name,username,duration,previews,license,url,tags',
            'page' => $page,
            'page_size' => 15,
            'token' => $apiKey,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Freesound API erro: '.$response->status());
        }

        return collect($response->json('results', []))->map(function (array $hit) use ($mode) {
            $previews = $hit['previews'] ?? [];
            $previewUrl = $previews['preview-hq-mp3'] ?? $previews['preview-lq-mp3'] ?? null;
            $downloadUrl = $previewUrl;
            $type = $mode === 'sfx' ? 'sfx' : 'audio';
            $attribution = MediaAttribution::forFreesound($hit, $type);

            return [
                'id' => $hit['id'],
                'source' => 'freesound',
                'type' => $type,
                'subtype' => $mode === 'sfx' ? 'sfx' : 'music',
                'title' => $hit['name'] ?? 'Freesound',
                'preview_url' => $previewUrl,
                'download_url' => $downloadUrl,
                'duration_seconds' => isset($hit['duration']) ? round((float) $hit['duration'], 1) : null,
                'author' => $hit['username'] ?? 'Freesound',
                'original_url' => $hit['url'] ?? ('https://freesound.org/people/'.($hit['username'] ?? 'unknown').'/sounds/'.$hit['id'].'/'),
                'license_type' => LicenseType::Freesound->value,
                'requires_attribution' => $attribution['requires_attribution'],
                'attribution_text' => $attribution['attribution_text'],
                'freesound_id' => $hit['id'],
            ];
        })->filter(fn (array $item) => ! empty($item['download_url']))->values()->all();
    }
}
