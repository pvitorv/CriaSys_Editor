<?php

namespace App\Services\MediaLibrary;

class MixkitService
{
    /**
     * Mixkit não possui API pública — catálogo curado para busca local.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchMusic(string $query): array
    {
        $catalog = config('criasys.mixkit_catalog', []);
        $query = mb_strtolower(trim($query));

        return collect($catalog)->filter(function (array $track) use ($query) {
            if ($query === '') {
                return true;
            }

            $haystack = mb_strtolower($track['title'].' '.($track['tags'] ?? ''));

            return str_contains($haystack, $query);
        })->map(fn (array $track) => array_merge($track, [
            'source' => 'mixkit',
            'type' => 'audio',
            'license_type' => 'Mixkit License',
            'requires_attribution' => false,
            'attribution_text' => 'Música gratuita do Mixkit.co',
        ]))->values()->all();
    }
}
