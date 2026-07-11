<?php

namespace App\Services\MediaLibrary;

use App\Enums\LicenseType;
use App\Support\ExternalHttp;

class MixkitSfxService
{
    public function __construct(private MediaSearchQueryTranslator $queryTranslator) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function searchSfx(string $query, int $page = 1): array
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

            if (count($results) >= 24) {
                break;
            }
        }

        foreach (config('criasys.mixkit_sfx_catalog', []) as $item) {
            if ($this->matchesQuery($item, $query)) {
                $key = 'mixkit-'.($item['id'] ?? '');
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $results[] = $this->normalizeCatalogItem($item);
                }
            }
        }

        return collect($results)->take(24)->values()->all();
    }

    /**
     * @return list<string>
     */
    private function fetchPages(string $term, int $page): array
    {
        $encoded = rawurlencode($term);
        $urls = [
            "https://mixkit.co/free-sound-effects/search/?search={$encoded}",
            "https://mixkit.co/free-sound-effects/{$encoded}/",
            "https://mixkit.co/free-sound-effects/impact/",
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
        foreach ($urls[1] as $previewUrl) {
            if (! str_contains($previewUrl, '/sfx/')) {
                continue;
            }

            $id = $this->extractId($previewUrl);
            if (! $id) {
                continue;
            }

            $pageUrl = "https://mixkit.co/free-sound-effects/item/{$id}/";
            $title = 'Mixkit SFX #'.$id;
            $attribution = MediaAttribution::forMixkitSfx($title, $pageUrl);

            $items[] = [
                'id' => $id,
                'source' => 'mixkit',
                'type' => 'sfx',
                'subtype' => 'sfx',
                'title' => $title,
                'preview_url' => $previewUrl,
                'download_url' => $this->resolveDownloadUrl($previewUrl, $id),
                'author' => 'Mixkit',
                'original_url' => $pageUrl,
                'license_type' => LicenseType::Mixkit->value,
                'requires_attribution' => $attribution['requires_attribution'],
                'attribution_text' => $attribution['attribution_text'],
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function matchesQuery(array $item, string $query): bool
    {
        $terms = $this->queryTranslator->termsFor($query);
        if ($terms === []) {
            return true;
        }

        $haystack = mb_strtolower(($item['title'] ?? '').' '.($item['tags'] ?? ''));

        foreach ($terms as $term) {
            if ($term !== '' && str_contains($haystack, mb_strtolower($term))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeCatalogItem(array $item): array
    {
        $title = $item['title'] ?? 'Mixkit SFX';
        $url = $item['original_url'] ?? 'https://mixkit.co/free-sound-effects/';
        $attribution = MediaAttribution::forMixkitSfx($title, $url);

        return array_merge($item, [
            'source' => 'mixkit',
            'type' => 'sfx',
            'subtype' => 'sfx',
            'license_type' => LicenseType::Mixkit->value,
            'requires_attribution' => $attribution['requires_attribution'],
            'attribution_text' => $attribution['attribution_text'],
        ]);
    }

    private function extractId(string $url): ?string
    {
        if (preg_match('#/sfx/(\d+)/#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private function resolveDownloadUrl(string $previewUrl, string $id): string
    {
        if (preg_match('#/sfx/(\d+)/#', $previewUrl, $m)) {
            $numericId = $m[1];

            return "https://assets.mixkit.co/active_storage/sfx/{$numericId}/{$numericId}-preview.mp3";
        }

        if (str_contains($previewUrl, '-preview')) {
            return $previewUrl;
        }

        return $previewUrl;
    }
}
