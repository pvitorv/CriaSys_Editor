<?php

namespace App\Services\Export;

use App\Models\Project;
use App\Services\Creator\CreatorProfileService;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\File;

class PlatformPostDescriptionService
{
    public function __construct(
        private ProjectAttributionCatalog $attributions,
        private CreatorProfileService $creatorProfiles,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function generateAll(Project $project): array
    {
        $project->load('slides');
        $credits = $this->attributions->collect($project);
        $creditsBlock = $this->attributions->creditsBlock($project);

        $platforms = collect(config('publish_platforms', []))->map(fn ($meta, $key) => [
            'key' => $key,
            'name' => $meta['name'],
            'slug' => $key,
            'max' => $meta['max_chars'] ?? 2200,
        ])->values()->all();

        $out = [];
        foreach ($platforms as $meta) {
            $out[$meta['key']] = $this->buildForPlatform(
                $project,
                $meta['name'],
                $meta['slug'],
                $creditsBlock,
                $credits,
                $meta['max'],
                $this->customDescription($project, $meta['key']),
            );
        }

        return $out;
    }

    /**
     * @return array<string, string> slug => absolute path
     */
    public function saveToProject(Project $project): array
    {
        $descriptions = $this->generateAll($project);
        $exportDir = app(ProjectStorageService::class)
            ->projectPath($project).DIRECTORY_SEPARATOR.'exports';

        File::ensureDirectoryExists($exportDir);

        $paths = [];
        foreach ($descriptions as $key => $data) {
            $filename = 'descricao_'.$key.'.txt';
            $path = $exportDir.DIRECTORY_SEPARATOR.$filename;
            File::put($path, $data['description']);
            $paths[$key] = $path;
        }

        $creditsPath = $exportDir.DIRECTORY_SEPARATOR.'creditos_materiais.txt';
        File::put($creditsPath, $this->buildCreditsFile($project));
        $paths['creditos'] = $creditsPath;

        return $paths;
    }

    /**
     * @param  list<array<string, mixed>>  $credits
     * @return array<string, mixed>
     */
    private function buildForPlatform(
        Project $project,
        string $platformName,
        string $slug,
        string $creditsBlock,
        array $credits,
        int $maxChars,
        ?string $customDescription = null,
    ): array {
        if ($customDescription !== null && trim($customDescription) !== '') {
            $description = trim($customDescription);
            if ($creditsBlock !== '' && ! str_contains($description, 'CRÉDITOS')) {
                $description .= "\n\n".$creditsBlock;
            }

            return [
                'platform' => $platformName,
                'slug' => $slug,
                'title' => $project->name,
                'description' => $description,
                'hashtags' => $this->hashtagsFor($slug, $project),
                'credits_block' => $creditsBlock,
                'char_count' => mb_strlen($description),
                'materials_count' => count($credits),
                'is_custom' => true,
            ];
        }

        $title = $project->name;
        $summary = $this->projectSummary($project);
        $hashtags = $this->hashtagsFor($slug, $project);
        $creatorProfile = $this->creatorProfiles->forProject($project);
        $ctaBlock = $this->creatorProfiles->ctaBlockForPlatform($creatorProfile, $slug);

        $intro = match ($slug) {
            'youtube', 'youtube_shorts' => "{$title}\n\n{$summary}\n\n{$hashtags}",
            'tiktok', 'instagram_reels' => "{$title} — {$this->shortHook($project)}\n\n{$hashtags}",
            'instagram_feed' => "{$title}\n\n{$summary}\n\n{$hashtags}",
            default => "{$title}\n\n{$summary}",
        };

        $creditsSection = $creditsBlock !== '' ? "\n\n".$creditsBlock : '';

        $description = $intro.$ctaBlock.$creditsSection;

        if (mb_strlen($description) > $maxChars) {
            $fixedLen = mb_strlen($ctaBlock.$creditsSection) + 20;
            $budget = $maxChars - $fixedLen;
            $intro = mb_substr($intro, 0, max(200, $budget)).'…';
            $description = $intro.$ctaBlock.$creditsSection;
        }

        return [
            'platform' => $platformName,
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'hashtags' => $hashtags,
            'credits_block' => $creditsBlock,
            'char_count' => mb_strlen($description),
            'materials_count' => count($credits),
            'is_custom' => false,
        ];
    }

    private function customDescription(Project $project, string $platformKey): ?string
    {
        $settings = $project->settings ?? [];
        $custom = $settings['platform_descriptions'][$platformKey] ?? null;

        return is_string($custom) ? $custom : null;
    }

    private function projectSummary(Project $project): string
    {
        if ($project->description) {
            return trim($project->description);
        }

        $titles = $project->slides->pluck('title')->filter()->take(5)->implode(' · ');

        return $titles
            ? "Slideshow narrado: {$titles}."
            : 'Vídeo slideshow criado com CriaSys Editor.';
    }

    private function shortHook(Project $project): string
    {
        return $project->slides->first()?->title ?: 'Confira este vídeo!';
    }

    private function hashtagsFor(string $slug, Project $project): string
    {
        $base = ['#slideshow', '#criasys', '#conteudo'];

        $extra = match (true) {
            str_contains($slug, 'youtube') => ['#youtube', '#video'],
            str_contains($slug, 'tiktok') => ['#tiktok', '#fyp', '#viral'],
            str_contains($slug, 'instagram') => ['#reels', '#instagram', '#explore'],
            default => [],
        };

        return implode(' ', array_unique(array_merge($base, $extra)));
    }

    private function buildCreditsFile(Project $project): string
    {
        $items = $this->attributions->collect($project);
        $lines = [
            'CRÉDITOS — '.$project->name,
            str_repeat('=', 50),
            '',
            'Texto oficial das plataformas (incluído automaticamente nas descrições):',
            '',
        ];

        if (empty($items)) {
            $lines[] = '(Conteúdo próprio ou upload local — sem créditos de biblioteca.)';

            return implode("\n", $lines);
        }

        foreach ($items as $i => $item) {
            $lines[] = ($i + 1).'. '.$item['credit_line'];
            if (! empty($item['used_in'])) {
                $lines[] = '   Usado em: '.implode(', ', $item['used_in']);
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
