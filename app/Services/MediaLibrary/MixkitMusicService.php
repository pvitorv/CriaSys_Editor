<?php

namespace App\Services\MediaLibrary;

use App\Enums\LicenseType;
use App\Support\ExternalHttp;

class MixkitMusicService
{
    public function __construct(
        private MediaSearchQueryTranslator $queryTranslator,
        private MixkitService $catalog,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function searchMusic(string $query, int $page = 1): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $results = [];
        $seen = [];

        foreach ($this->queryTranslator->termsFor($query) as $term) {
            foreach ($this->fetchPages($term, $page) as $html) {
                foreach ($this->parseHtml($html) as $item) {
                    $key = ($item['source'] ?? '').'-'.($item['id'] ?? '');
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $results[] = $item;
                }
            }

            $results = array_merge($results, $this->catalog->searchMusic($term));

            if (count($results) >= 24) {
                break;
            }
        }

        return collect($results)
            ->unique(fn ($item) => ($item['source'] ?? '').'-'.($item['id'] ?? ''))
            ->values()
            ->take(24)
            ->all();
    }

    /**
     * @return list<string>
     */
    private function fetchPages(string $term, int $page): array
    {
        $encoded = rawurlencode($term);
        $urls = [
            "https://mixkit.co/free-stock-music/search/?search={$encoded}",
            "https://mixkit.co/free-stock-music/{$encoded}/",
            "https://mixkit.co/free-stock-music/ambient/",
        ];

        if ($page > 1) {
            $urls = array_map(fn (string $url) => $url.(str_contains($url, '?') ? '&' : '?').'page='.$page, $urls);
        }

        $pages = [];
        foreach ($urls as $url) {
            try {
                $response = ExternalHttp::client(20)
                    ->withHeaders(['User-Agent' => 'CriaSysEditor/1.0'])
                    ->get($url);

                if ($response->successful() && str_contains($response->body(), 'data-audio-player-preview-url-value')) {
                    $pages[] = $response->body();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $pages;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseHtml(string $html): array
    {
        if (! preg_match_all('/data-audio-player-preview-url-value="([^"]+)"/', $html, $urls)) {
            return [];
        }

        preg_match_all('/data-audio-player-item-id-value="(\d+)"/', $html, $ids);

        $items = [];
        foreach ($urls[1] as $index => $previewUrl) {
            if (! str_contains($previewUrl, '/music/')) {
                continue;
            }

            $id = $ids[1][$index] ?? $this->extractId($previewUrl);
            if (! $id) {
                continue;
            }

            $downloadUrl = preg_replace('#/preview/#', '/', $previewUrl) ?? $previewUrl;
            if (! str_contains($downloadUrl, '.mp3')) {
                $downloadUrl = "https://assets.mixkit.co/music/{$id}/{$id}.mp3";
            }

            $pageUrl = "https://mixkit.co/free-stock-music/item/{$id}/";
            $title = 'Mixkit Music #'.$id;
            $attribution = MediaAttribution::forMixkitMusic($title, $pageUrl);

            $items[] = [
                'id' => $id,
                'source' => 'mixkit',
                'type' => 'audio',
                'subtype' => 'music',
                'title' => $title,
                'preview_url' => $previewUrl,
                'download_url' => $downloadUrl,
                'author' => 'Mixkit',
                'original_url' => $pageUrl,
                'license_type' => LicenseType::Mixkit->value,
                'requires_attribution' => $attribution['requires_attribution'],
                'attribution_text' => $attribution['attribution_text'],
            ];
        }

        return $items;
    }

    private function extractId(string $url): ?string
    {
        if (preg_match('#/music/(\d+)/#', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
