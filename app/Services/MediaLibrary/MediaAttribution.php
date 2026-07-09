<?php

namespace App\Services\MediaLibrary;

use App\Models\Asset;
use App\Models\ProjectStockLicense;

/**
 * Textos de crédito no formato sugerido por cada plataforma (copiar/colar).
 */
class MediaAttribution
{
    /**
     * @param  array<string, mixed>  $data  Dados brutos da API ou item normalizado parcial
     * @return array{attribution_text: ?string, requires_attribution: bool}
     */
    public static function forOpenverseImage(array $data): array
    {
        $text = trim((string) ($data['attribution'] ?? ''));

        if ($text === '') {
            $creator = $data['creator'] ?? 'Desconhecido';
            $license = strtoupper($data['license'] ?? 'CC');
            $text = "{$creator} / {$license} (Openverse)";
            if (! empty($data['foreign_landing_url'])) {
                $text .= ' — '.$data['foreign_landing_url'];
            }
        }

        $license = strtoupper($data['license'] ?? '');
        $required = ! in_array($license, ['CC0', 'PDM', ''], true);

        return [
            'attribution_text' => $text,
            'requires_attribution' => $required,
        ];
    }

    /**
     * @param  array<string, mixed>  $photo
     */
    public static function forPexelsPhoto(array $photo): array
    {
        $name = $photo['photographer'] ?? 'Unknown';
        $url = $photo['url'] ?? ('https://www.pexels.com/photo/'.($photo['id'] ?? '').'/');

        return [
            'attribution_text' => "Photo by {$name} on Pexels — {$url}",
            'requires_attribution' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $video
     */
    public static function forPexelsVideo(array $video): array
    {
        $name = $video['user']['name'] ?? 'Unknown';
        $url = $video['url'] ?? ('https://www.pexels.com/video/'.($video['id'] ?? '').'/');

        return [
            'attribution_text' => "Video by {$name} on Pexels — {$url}",
            'requires_attribution' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $photo
     */
    public static function forUnsplashPhoto(array $photo): array
    {
        $name = $photo['user']['name'] ?? 'Unknown';
        $link = ($photo['links']['html'] ?? 'https://unsplash.com')
            .'?utm_source=criasys_editor&utm_medium=referral';

        return [
            'attribution_text' => "Photo by {$name} on Unsplash — {$link}",
            'requires_attribution' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    public static function forPixabay(array $hit, string $type = 'image'): array
    {
        $user = $hit['user'] ?? 'Unknown';
        $page = $hit['pageURL'] ?? 'https://pixabay.com';
        $label = match ($type) {
            'video' => 'Video',
            'audio' => 'Media',
            default => 'Image',
        };

        return [
            'attribution_text' => "{$label} by {$user} on Pixabay — {$page}",
            'requires_attribution' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $item  item de busca (title, original_url, @id opcional)
     */
    public static function forMixkitVideo(array $item): array
    {
        $title = $item['title'] ?? $item['name'] ?? 'Stock Video';
        $url = $item['original_url'] ?? $item['page_url'] ?? 'https://mixkit.co/free-stock-video/';

        if (str_contains($url, '#video')) {
            $url = explode('#', $url)[0];
        }

        return [
            'attribution_text' => "Free Stock Video \"{$title}\" — Mixkit — {$url}",
            'requires_attribution' => false,
        ];
    }

    public static function forMixkitMusic(string $title, string $url = 'https://mixkit.co/free-stock-music/'): array
    {
        return [
            'attribution_text' => "Music: {$title} — Mixkit — {$url}",
            'requires_attribution' => false,
        ];
    }

    /**
     * Monta crédito a partir do item normalizado da busca/importação.
     *
     * @param  array<string, mixed>  $item
     * @return array{attribution_text: ?string, requires_attribution: bool}
     */
    public static function fromSearchItem(array $item): array
    {
        if (! empty($item['attribution_text'])) {
            return [
                'attribution_text' => (string) $item['attribution_text'],
                'requires_attribution' => (bool) ($item['requires_attribution'] ?? false),
            ];
        }

        $source = $item['source'] ?? '';
        $type = $item['type'] ?? 'image';

        return match ($source) {
            'openverse' => self::forOpenverseImage([
                'attribution' => $item['attribution'] ?? null,
                'creator' => $item['author'] ?? null,
                'license' => $item['license_type'] ?? null,
                'foreign_landing_url' => $item['original_url'] ?? null,
            ]),
            'pexels' => $type === 'video'
                ? self::forPexelsVideo([
                    'user' => ['name' => $item['author'] ?? 'Unknown'],
                    'url' => $item['original_url'] ?? null,
                    'id' => $item['id'] ?? null,
                ])
                : self::forPexelsPhoto([
                    'photographer' => $item['author'] ?? $item['photographer'] ?? 'Unknown',
                    'url' => $item['original_url'] ?? null,
                    'id' => $item['id'] ?? null,
                ]),
            'unsplash' => self::forUnsplashPhoto([
                'user' => ['name' => $item['author'] ?? 'Unknown'],
                'links' => ['html' => $item['original_url'] ?? 'https://unsplash.com'],
            ]),
            'pixabay' => self::forPixabay([
                'user' => $item['author'] ?? 'Unknown',
                'pageURL' => $item['original_url'] ?? null,
            ], $type),
            'mixkit' => $type === 'audio'
                ? self::forMixkitMusic($item['title'] ?? 'Mixkit Music', $item['original_url'] ?? null)
                : self::forMixkitVideo($item),
            default => ['attribution_text' => null, 'requires_attribution' => false],
        };
    }

    /**
     * Licença de assinatura paga (Envato, Storyblocks…) vinculada ao projeto CriaSys.
     *
     * @return array{attribution_text: string, requires_attribution: bool}
     */
    public static function forPaidSubscription(ProjectStockLicense $registration, ?Asset $asset = null): array
    {
        $provider = config("criasys.stock_providers.{$registration->provider}", []);
        $name = $provider['name'] ?? ucfirst($registration->provider);
        $url = $registration->license_url ?: ($provider['license_url'] ?? null);

        $parts = ["Licensed via {$name}. Project: «{$registration->project_title}»"];

        if ($asset?->item_title) {
            $parts[] = 'Item: '.$asset->item_title;
        } elseif ($asset?->item_external_id) {
            $parts[] = 'Item ID: '.$asset->item_external_id;
        }

        if ($registration->license_note) {
            $parts[] = $registration->license_note;
        }

        if ($url) {
            $parts[] = $url;
        }

        return [
            'attribution_text' => implode(' — ', $parts),
            'requires_attribution' => true,
        ];
    }

    /** Compra avulsa / biblioteca licenciada local. */
    public static function forUserPurchased(?string $itemTitle, ?string $sourceLabel, ?string $licenseUrl = null): array
    {
        $label = $sourceLabel ?: 'Licensed library';
        $text = $itemTitle ? "{$label} — {$itemTitle}" : $label;
        if ($licenseUrl) {
            $text .= ' — '.$licenseUrl;
        }

        return [
            'attribution_text' => $text,
            'requires_attribution' => true,
        ];
    }
}
