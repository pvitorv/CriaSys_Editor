<?php

namespace App\Services\MediaLibrary;

use App\Support\ExternalHttp;

class MixkitVideoService
{
    /**
     * Busca vídeos gratuitos no Mixkit (sem API key).
     * A página /search/ do Mixkit costuma devolver clipes genéricos sobre "buscar na internet";
     * categorias (/free-stock-video/{term}/) trazem resultados relevantes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchVideos(string $query, int $page = 1): array
    {
        $term = trim($query);
        if ($term === '') {
            return [];
        }

        $results = [];

        foreach ($this->categoryCandidates($term) as $slug) {
            $html = $this->fetchCategoryPage($slug);
            if (! $html) {
                continue;
            }
            $chunk = $this->parseVideoPage($html);
            $results = $this->uniqueVideos(array_merge($results, $chunk));
            if (count($results) >= 12) {
                break;
            }
        }

        if (count($results) < 8) {
            $searchHtml = $this->fetchSearchPage($term, $page);
            if ($searchHtml) {
                $fromSearch = $this->parseVideoPage($searchHtml, filterSearchUi: true);
                $results = $this->uniqueVideos(array_merge($results, $fromSearch));
            }
        }

        return array_slice($this->uniqueVideos($results), 0, 24);
    }

    /** @return list<string> */
    private function categoryCandidates(string $term): array
    {
        $normalized = strtolower(trim($term));
        $normalized = preg_replace('/[^a-z0-9\s-]+/', '', $normalized) ?? $normalized;
        $candidates = [];

        if ($normalized !== '') {
            $candidates[] = str_replace(' ', '-', $normalized);
        }

        foreach (preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (strlen($word) >= 3) {
                $candidates[] = $word;
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function fetchSearchPage(string $term, int $page): ?string
    {
        $encoded = rawurlencode($term);
        $url = "https://mixkit.co/free-stock-video/search/?search={$encoded}";
        if ($page > 1) {
            $url .= '&page='.$page;
        }

        return $this->fetchHtml($url);
    }

    private function fetchCategoryPage(string $term): ?string
    {
        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($term)) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') {
            return null;
        }

        return $this->fetchHtml("https://mixkit.co/free-stock-video/{$slug}/");
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = ExternalHttp::client(20)
                ->withHeaders(['User-Agent' => 'CriaSysEditor/1.0'])
                ->get($url);

            if ($response->successful() && str_contains($response->body(), 'mixkit.co')) {
                return $response->body();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseVideoPage(string $html, bool $filterSearchUi = false): array
    {
        $fromJson = $this->parseVideoObjects($html, $filterSearchUi);
        $fromSlugs = $this->parseFromSlugLinks($html);

        if ($fromJson === []) {
            return $fromSlugs;
        }

        if ($filterSearchUi && $this->looksLikeSearchUiResults($fromJson)) {
            return $fromSlugs !== [] ? $fromSlugs : [];
        }

        return $this->uniqueVideos(array_merge($fromJson, $fromSlugs));
    }

    /** @param  list<array<string, mixed>>  $videos */
    private function looksLikeSearchUiResults(array $videos): bool
    {
        if ($videos === []) {
            return false;
        }

        $junk = 0;
        foreach ($videos as $video) {
            if ($this->isSearchUiTitle((string) ($video['title'] ?? ''))) {
                $junk++;
            }
        }

        return $junk >= max(1, (int) ceil(count($videos) * 0.6));
    }

    private function isSearchUiTitle(string $title): bool
    {
        $t = strtolower($title);
        foreach ([
            'searching for',
            'search results',
            'surfing the internet',
            'scrolling through',
            'searching for a book',
            'creatives surfing',
        ] as $needle) {
            if (str_contains($t, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $videos
     * @return list<array<string, mixed>>
     */
    private function uniqueVideos(array $videos): array
    {
        $seen = [];
        $unique = [];

        foreach ($videos as $video) {
            $key = $this->videoKey($video);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $video;
        }

        return $unique;
    }

    /** @param  array<string, mixed>  $video */
    private function videoKey(array $video): string
    {
        $id = (string) ($video['id'] ?? '');
        if ($id !== '') {
            return 'mixkit-'.$id;
        }

        $url = (string) ($video['download_url'] ?? $video['preview_url'] ?? '');
        if (preg_match('#/videos/(\d+)/#', $url, $match)) {
            return 'mixkit-'.$match[1];
        }

        return 'mixkit-'.md5($url);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseVideoObjects(string $html, bool $filterSearchUi = false): array
    {
        if (! preg_match_all('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $matches)) {
            return [];
        }

        $items = [];

        foreach ($matches[1] as $rawJson) {
            $json = json_decode(html_entity_decode($rawJson), true);
            if (! is_array($json)) {
                continue;
            }

            $graph = $json['@graph'] ?? [$json];

            foreach ($graph as $node) {
                if (($node['@type'] ?? '') !== 'VideoObject') {
                    continue;
                }

                $title = (string) ($node['name'] ?? 'Vídeo Mixkit');
                if ($filterSearchUi && $this->isSearchUiTitle($title)) {
                    continue;
                }

                $download = $node['contentUrl'] ?? $node['embedUrl'] ?? null;
                if (! $download) {
                    continue;
                }

                $id = $this->extractIdFromUrl($download);
                if (! $id) {
                    continue;
                }

                $items[] = $this->videoItem(
                    $id,
                    $title,
                    $node['thumbnailUrl'] ?? null,
                    $this->preferHdUrl($download),
                    $node['@id'] ?? "https://mixkit.co/free-stock-video/{$id}/",
                );
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseFromSlugLinks(string $html): array
    {
        if (! preg_match_all(
            '#https://mixkit\.co/free-stock-video/([a-z0-9-]+)-(\d+)/#i',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            return $this->parseFromMp4Links($html);
        }

        $items = [];
        $seen = [];

        foreach ($matches as $match) {
            $slug = $match[1];
            $id = $match[2];

            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $title = ucfirst(str_replace('-', ' ', $slug));
            $pageUrl = "https://mixkit.co/free-stock-video/{$slug}-{$id}/";

            $items[] = $this->videoItem(
                $id,
                $title,
                "https://assets.mixkit.co/videos/{$id}/{$id}-thumb-360-0.jpg",
                "https://assets.mixkit.co/videos/{$id}/{$id}-720.mp4",
                $pageUrl,
            );
        }

        return $items ?: $this->parseFromMp4Links($html);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseFromMp4Links(string $html): array
    {
        if (! preg_match_all('#https://assets\.mixkit\.co/videos/(\d+)/\1-720\.mp4#', $html, $matches)) {
            return [];
        }

        $items = [];
        foreach (array_unique($matches[1]) as $id) {
            $items[] = $this->videoItem(
                $id,
                'Vídeo Mixkit #'.$id,
                "https://assets.mixkit.co/videos/{$id}/{$id}-thumb-360-0.jpg",
                "https://assets.mixkit.co/videos/{$id}/{$id}-720.mp4",
                "https://mixkit.co/free-stock-video/{$id}/",
            );
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function videoItem(string $id, string $title, ?string $thumb, string $download, string $pageUrl): array
    {
        $attribution = MediaAttribution::forMixkitVideo([
            'title' => $title,
            'original_url' => $pageUrl,
        ]);

        return [
            'id' => $id,
            'source' => 'mixkit',
            'type' => 'video',
            'title' => $title,
            'preview_url' => $thumb,
            'download_url' => $download,
            'duration_seconds' => null,
            'author' => 'Mixkit',
            'original_url' => $pageUrl,
            'license_type' => 'Mixkit License',
            'requires_attribution' => $attribution['requires_attribution'],
            'attribution_text' => $attribution['attribution_text'],
        ];
    }

    private function extractIdFromUrl(string $url): ?string
    {
        if (preg_match('#/videos/(\d+)/#', $url, $m)) {
            return $m[1];
        }

        if (preg_match('#/free-stock-video/[a-z0-9-]+-(\d+)/#i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private function preferHdUrl(string $url): string
    {
        if (preg_match('#/videos/(\d+)/#', $url, $m)) {
            $id = $m[1];

            return "https://assets.mixkit.co/videos/{$id}/{$id}-720.mp4";
        }

        return $url;
    }
}
