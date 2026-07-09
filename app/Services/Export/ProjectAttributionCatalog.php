<?php

namespace App\Services\Export;

use App\Models\Asset;
use App\Models\Project;
use App\Services\MediaLibrary\MediaAttribution;

class ProjectAttributionCatalog
{
    public function __construct(private AssetAttributionRepairService $repair) {}

    /**
     * Materiais de biblioteca usados no projeto (slides + trilha).
     *
     * @return list<array{type: string, source: string, license: string, credit_line: string, original_url: ?string, used_in: list<string>}>
     */
    public function collect(Project $project): array
    {
        $this->repair->repairProject($project);
        $project->load(['slides', 'assets.stockLicense', 'audioTracks']);

        $usage = $this->buildUsageMap($project);
        $usedPaths = array_keys($usage);
        $assetsByPath = $this->indexAssetsByPath($project);

        $items = [];

        foreach ($usedPaths as $usedPath) {
            $asset = $assetsByPath[$usedPath] ?? $this->findAssetForPath($project, $usedPath);
            if (! $asset || $this->isLocalOnly($asset)) {
                continue;
            }

            $creditLine = $this->creditLine($asset);
            if (! $creditLine) {
                continue;
            }

            $key = $asset->file_hash ?: (string) $asset->id;
            $items[$key] = [
                'type' => $asset->type,
                'source' => $asset->source,
                'license' => (string) $asset->license_type,
                'credit_line' => $creditLine,
                'original_url' => $asset->original_url,
                'used_in' => $usage[$usedPath] ?? [],
            ];
        }

        return array_values($items);
    }

    /**
     * @return list<string>
     */
    public function creditLines(Project $project): array
    {
        return collect($this->collect($project))
            ->pluck('credit_line')
            ->unique()
            ->values()
            ->all();
    }

    public function creditsBlock(Project $project, string $separator = "\n"): string
    {
        $lines = $this->creditLines($project);

        if (empty($lines)) {
            return '';
        }

        return "CRÉDITOS E LICENÇAS\n".implode($separator, $lines);
    }

    /**
     * @return array<string, list<string>>
     */
    private function buildUsageMap(Project $project): array
    {
        $map = [];

        foreach ($project->slides as $index => $slide) {
            $label = 'Slide '.($index + 1).($slide->title ? ' ('.$slide->title.')' : '');

            foreach (['image_path', 'video_path'] as $field) {
                $path = $slide->{$field};
                if ($path) {
                    $key = $this->normalizePath($path);
                    $map[$key] ??= [];
                    $map[$key][] = $label;
                }
            }
        }

        foreach ($project->audioTracks as $track) {
            if ($track->file_path) {
                $key = $this->normalizePath($track->file_path);
                $map[$key] ??= [];
                $map[$key][] = $track->type === 'music' ? 'Trilha sonora' : 'Áudio';
            }
        }

        foreach ($map as $path => $labels) {
            $map[$path] = array_values(array_unique($labels));
        }

        return $map;
    }

    /**
     * @return array<string, Asset>
     */
    private function indexAssetsByPath(Project $project): array
    {
        $index = [];

        foreach ($project->assets as $asset) {
            if (! $asset->file_path) {
                continue;
            }
            $index[$this->normalizePath($asset->file_path)] = $asset;
        }

        return $index;
    }

    private function findAssetForPath(Project $project, string $usedPath): ?Asset
    {
        $baseUsed = strtolower(basename($usedPath));

        foreach ($project->assets as $asset) {
            if (! $asset->file_path) {
                continue;
            }
            $baseAsset = strtolower(basename(str_replace('\\', '/', $asset->file_path)));
            if ($baseUsed === $baseAsset) {
                return $asset;
            }
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $resolved = @realpath($path);

        return strtolower($resolved ?: $path);
    }

    private function isLocalOnly(Asset $asset): bool
    {
        if ($asset->stock_license_id || $asset->stockLicense) {
            return false;
        }

        if ($asset->attribution_text) {
            return false;
        }

        if (in_array($asset->license_type, [
            \App\Enums\LicenseType::UserPurchased->value,
            \App\Enums\LicenseType::Envato->value,
            \App\Enums\LicenseType::Storyblocks->value,
            \App\Enums\LicenseType::Artgrid->value,
            \App\Enums\LicenseType::CustomLicensed->value,
        ], true)) {
            return false;
        }

        return in_array($asset->source, ['local', 'unknown', '', null], true);
    }

    private function creditLine(Asset $asset): ?string
    {
        if ($asset->stockLicense) {
            return MediaAttribution::forPaidSubscription($asset->stockLicense, $asset)['attribution_text'];
        }

        if ($asset->attribution_text) {
            return trim($asset->attribution_text);
        }

        if ($asset->license_type === \App\Enums\LicenseType::UserPurchased->value) {
            return MediaAttribution::forUserPurchased(
                $asset->item_title,
                $asset->source !== 'local' ? $asset->source : 'Biblioteca licenciada',
                $asset->original_url
            )['attribution_text'];
        }

        return null;
    }
}
