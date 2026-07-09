<?php

namespace App\Services\MediaLibrary;

class MixkitService
{
    public function __construct(private MediaSearchQueryTranslator $queryTranslator) {}

    /**
     * Mixkit não possui API pública — catálogo curado para busca local.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchMusic(string $query): array
    {
        $catalog = config('criasys.mixkit_catalog', []);
        $terms = $this->queryTranslator->termsFor($query);

        return collect($catalog)->filter(function (array $track) use ($terms) {
            if ($terms === []) {
                return true;
            }

            $haystack = mb_strtolower($track['title'].' '.($track['tags'] ?? ''));

            foreach ($terms as $term) {
                if ($term !== '' && str_contains($haystack, mb_strtolower($term))) {
                    return true;
                }
            }

            return false;
        })->map(function (array $track) {
            $attribution = MediaAttribution::forMixkitMusic(
                $track['title'] ?? 'Mixkit Music',
                $track['original_url'] ?? $track['page_url'] ?? 'https://mixkit.co/free-stock-music/'
            );

            return array_merge($track, [
                'source' => 'mixkit',
                'type' => 'audio',
                'license_type' => 'Mixkit License',
                'requires_attribution' => $attribution['requires_attribution'],
                'attribution_text' => $attribution['attribution_text'],
            ]);
        })->values()->all();
    }
}
