<?php

namespace App\Services\Export;

use App\Models\Asset;
use App\Models\Project;
use App\Services\MediaLibrary\MediaAttribution;

/**
 * Repara assets importados da biblioteca que ficaram sem source/crédito (imports antigos ou bug).
 */
class AssetAttributionRepairService
{
    private const LIBRARY_SOURCES = ['openverse', 'pexels', 'pixabay', 'unsplash', 'mixkit', 'library'];

    public function repairProject(Project $project): int
    {
        $project->load('assets');
        $fixed = 0;

        foreach ($project->assets as $asset) {
            if ($this->repairAsset($asset)) {
                $fixed++;
            }
        }

        return $fixed;
    }

    public function repairAsset(Asset $asset): bool
    {
        if ($this->hasValidCredit($asset)) {
            return false;
        }

        $item = $this->reconstructItem($asset);
        if ($item === null) {
            return false;
        }

        $attribution = MediaAttribution::fromSearchItem($item);
        if (empty($attribution['attribution_text'])) {
            return false;
        }

        $asset->update([
            'source' => $item['source'],
            'attribution_text' => $attribution['attribution_text'],
            'requires_attribution' => $attribution['requires_attribution'],
            'original_url' => $item['original_url'] ?? $asset->original_url,
            'metadata' => array_merge($asset->metadata ?? [], [
                'repaired_at' => now()->toIso8601String(),
            ]),
        ]);

        return true;
    }

    private function hasValidCredit(Asset $asset): bool
    {
        if (! $asset->attribution_text) {
            return false;
        }

        return in_array($asset->source, self::LIBRARY_SOURCES, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function reconstructItem(Asset $asset): ?array
    {
        $meta = $asset->metadata ?? [];
        $source = $this->resolveSource($asset);
        if ($source === null) {
            return null;
        }

        $externalId = $meta['external_id'] ?? null;
        $originalUrl = $asset->original_url;

        if (! $originalUrl) {
            $originalUrl = match ($source) {
                'mixkit' => $externalId
                    ? "https://mixkit.co/free-stock-video/{$externalId}/"
                    : 'https://mixkit.co/free-stock-video/',
                'pexels' => $externalId
                    ? ($asset->type === 'video'
                        ? "https://www.pexels.com/video/{$externalId}/"
                        : "https://www.pexels.com/photo/{$externalId}/")
                    : 'https://www.pexels.com',
                'pixabay' => 'https://pixabay.com',
                'unsplash' => 'https://unsplash.com',
                'openverse' => 'https://openverse.org',
                default => null,
            };
        }

        return [
            'source' => $source,
            'type' => $asset->type,
            'id' => $externalId,
            'author' => $meta['author'] ?? $meta['photographer'] ?? null,
            'title' => $meta['title'] ?? null,
            'original_url' => $originalUrl,
            'license_type' => $asset->license_type,
        ];
    }

    private function resolveSource(Asset $asset): ?string
    {
        if (in_array($asset->source, self::LIBRARY_SOURCES, true)) {
            return $asset->source === 'library' ? 'mixkit' : $asset->source;
        }

        $meta = $asset->metadata ?? [];
        if (! empty($meta['import_source']) && in_array($meta['import_source'], self::LIBRARY_SOURCES, true)) {
            return $meta['import_source'];
        }

        $basename = strtolower(basename($asset->file_path));
        if (preg_match('/^(mixkit|openverse|pexels|pixabay|unsplash)_/', $basename, $m)) {
            return $m[1];
        }

        // imports antigos: video_* ou media_* de biblioteca externa (não upload local_*)
        if (preg_match('/^(video|media)_/', $basename) && ! str_starts_with($basename, 'local_')) {
            return 'mixkit';
        }

        return null;
    }
}
