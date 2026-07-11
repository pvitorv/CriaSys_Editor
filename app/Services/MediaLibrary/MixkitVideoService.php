<?php

namespace App\Services\MediaLibrary;

use App\Support\ExternalHttp;

class MixkitVideoService
{
    /**
     * Busca vídeos gratuitos no Mixkit (sem API key) via página pública + schema.org.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchVideos(string $query, int $page = 1): array
    {
        $term = trim($query);
        if ($term === '') {
            return [];
        }

        $html = $this->fetchSearchPage($term, $page);
        if (! $html && $page === 1) {
            $html = $this->fetchCategoryPage($term);
        }

        if (! $html) {
            return [];
        }

        $results = $this->parseVideoObjects($html);

        return array_slice($this->uniqueVideos($results), 0, 24);
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
    private function parseVideoObjects(string $html): array
    {
        if (! preg_match('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $match)) {
            return $this->parseFromMp4Links($html);
        }

        $json = json_decode(html_entity_decode($match[1]), true);
        if (! is_array($json)) {
            return $this->parseFromMp4Links($html);
        }

        $graph = $json['@graph'] ?? [$json];
        $items = [];

        foreach ($graph as $node) {
            if (($node['@type'] ?? '') !== 'VideoObject') {
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

            $attribution = MediaAttribution::forMixkitVideo([
                'title' => $node['name'] ?? 'Vídeo Mixkit',
                'original_url' => $node['@id'] ?? 'https://mixkit.co/free-stock-video/',
            ]);

            $items[] = [
                'id' => $id,
                'source' => 'mixkit',
                'type' => 'video',
                'title' => $node['name'] ?? 'Vídeo Mixkit',
                'preview_url' => $node['thumbnailUrl'] ?? null,
                'download_url' => $this->preferHdUrl($download),
                'duration_seconds' => null,
                'author' => 'Mixkit',
                'original_url' => $node['@id'] ?? 'https://mixkit.co/free-stock-video/',
                'license_type' => 'Mixkit License',
                'requires_attribution' => $attribution['requires_attribution'],
                'attribution_text' => $attribution['attribution_text'],
            ];
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
            $pageUrl = "https://mixkit.co/free-stock-video/{$id}/";
            $attribution = MediaAttribution::forMixkitVideo([
                'title' => 'Vídeo Mixkit #'.$id,
                'original_url' => $pageUrl,
            ]);

            $items[] = [
                'id' => $id,
                'source' => 'mixkit',
                'type' => 'video',
                'title' => 'Vídeo Mixkit #'.$id,
                'preview_url' => "https://assets.mixkit.co/videos/{$id}/{$id}-thumb-360-0.jpg",
                'download_url' => "https://assets.mixkit.co/videos/{$id}/{$id}-720.mp4",
                'duration_seconds' => null,
                'author' => 'Mixkit',
                'original_url' => $pageUrl,
                'license_type' => 'Mixkit License',
                'requires_attribution' => $attribution['requires_attribution'],
                'attribution_text' => $attribution['attribution_text'],
            ];
        }

        return $items;
    }

    private function extractIdFromUrl(string $url): ?string
    {
        if (preg_match('#/videos/(\d+)/#', $url, $m)) {
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
