<?php

namespace App\Services\Export;

use App\Models\Asset;
use App\Models\Project;

class ProjectAttributionCatalog
{
    /**
     * @return list<array{type: string, source: string, license: string, credit_line: string, original_url: ?string, used_in: list<string>}>
     */
    public function collect(Project $project): array
    {
        $project->load(['slides', 'assets', 'audioTracks']);

        $usage = $this->buildUsageMap($project);
        $items = [];

        foreach ($project->assets as $asset) {
            if ($this->isLocalOnly($asset)) {
                continue;
            }

            $key = $asset->file_hash ?: (string) $asset->id;
            $creditLine = $this->creditLine($asset);

            if (! $creditLine) {
                continue;
            }

            $items[$key] = [
                'type' => $asset->type,
                'source' => $asset->source,
                'license' => (string) $asset->license_type,
                'credit_line' => $creditLine,
                'original_url' => $asset->original_url,
                'used_in' => $usage[$asset->file_path] ?? [],
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
            return 'Nenhum material de terceiros registrado neste projeto (apenas conteúdo próprio/upload local).';
        }

        return collect($lines)
            ->map(fn (string $line) => '• '.$line)
            ->implode($separator);
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
                    $map[$path] ??= [];
                    $map[$path][] = $label;
                }
            }
        }

        foreach ($project->audioTracks as $track) {
            if ($track->file_path) {
                $map[$track->file_path] ??= [];
                $map[$track->file_path][] = $track->type === 'music' ? 'Trilha sonora' : 'Áudio';
            }
        }

        foreach ($map as $path => $labels) {
            $map[$path] = array_values(array_unique($labels));
        }

        return $map;
    }

    private function isLocalOnly(Asset $asset): bool
    {
        return in_array($asset->source, ['local', '', null], true);
    }

    private function creditLine(Asset $asset): ?string
    {
        if ($asset->attribution_text) {
            $line = $asset->attribution_text;
        } else {
            $line = match ($asset->source) {
                'pexels' => 'Mídia via Pexels (pexels.com)',
                'pixabay' => 'Mídia via Pixabay (pixabay.com)',
                'unsplash' => 'Mídia via Unsplash (unsplash.com)',
                'openverse' => 'Mídia via Openverse (Creative Commons)',
                'mixkit' => 'Mídia via Mixkit (mixkit.co)',
                default => $asset->source !== 'local' && $asset->source
                    ? 'Mídia via '.$asset->source
                    : null,
            };
        }

        if (! $line) {
            return null;
        }

        if ($asset->original_url) {
            $line .= ' — '.$asset->original_url;
        }

        if ($asset->license_type && ! in_array($asset->license_type, ['local', 'Mixkit License'], true)) {
            $line .= ' ['.$asset->license_type.']';
        }

        return $line;
    }
}
