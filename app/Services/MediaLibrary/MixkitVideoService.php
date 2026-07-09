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
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $terms = $this->searchTerms($query);
        $results = [];
        $seen = [];

        foreach ($terms as $term) {
            $html = $this->fetchPage($term, $page);
            if (! $html) {
                continue;
            }

            foreach ($this->parseVideoObjects($html) as $video) {
                $key = ($video['source'] ?? '').'-'.($video['id'] ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $results[] = $video;
            }

            if (count($results) >= 20) {
                break;
            }
        }

        return array_slice($results, 0, 24);
    }

    /**
     * @return list<string>
     */
    private function searchTerms(string $query): array
    {
        $lower = mb_strtolower(trim($query));

        $aliases = [
            'jogo' => 'gaming',
            'jogos' => 'gaming',
            'videogame' => 'gaming',
            'video game' => 'gaming',
            'futebol' => 'football',
            'natureza' => 'nature',
            'cidade' => 'city',
            'mar' => 'ocean',
            'floresta' => 'forest',
        ];

        if (isset($aliases[$lower])) {
            return [$aliases[$lower], $lower];
        }

        return [$lower];
    }

    private function fetchPage(string $term, int $page): ?string
    {
        $encoded = rawurlencode($term);
        $urls = [
            "https://mixkit.co/free-stock-video/discover/{$encoded}/",
            "https://mixkit.co/free-stock-video/search/?search={$encoded}",
            "https://mixkit.co/free-stock-video/{$encoded}/",
        ];

        if ($page > 1) {
            $urls = array_map(fn (string $url) => $url.(str_contains($url, '?') ? '&' : '?').'page='.$page, $urls);
        }

        foreach ($urls as $url) {
            try {
                $response = ExternalHttp::client(20)
                    ->withHeaders(['User-Agent' => 'CriaSysEditor/1.0'])
                    ->get($url);

                if ($response->successful() && str_contains($response->body(), 'VideoObject')) {
                    return $response->body();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
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
                'requires_attribution' => false,
                'attribution_text' => 'Vídeo gratuito do Mixkit.co',
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
            $items[] = [
                'id' => $id,
                'source' => 'mixkit',
                'type' => 'video',
                'title' => 'Vídeo Mixkit #'.$id,
                'preview_url' => "https://assets.mixkit.co/videos/{$id}/{$id}-thumb-360-0.jpg",
                'download_url' => "https://assets.mixkit.co/videos/{$id}/{$id}-720.mp4",
                'duration_seconds' => null,
                'author' => 'Mixkit',
                'original_url' => 'https://mixkit.co/free-stock-video/',
                'license_type' => 'Mixkit License',
                'requires_attribution' => false,
                'attribution_text' => 'Vídeo gratuito do Mixkit.co',
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
