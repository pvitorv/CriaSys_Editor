<?php

namespace App\Services\Export;

use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\File;

class PlatformPostDescriptionService
{
    public function __construct(private ProjectAttributionCatalog $attributions) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function generateAll(Project $project): array
    {
        $project->load('slides');
        $credits = $this->attributions->collect($project);
        $creditsBlock = $this->attributions->creditsBlock($project);

        $platforms = [
            'youtube' => ['name' => 'YouTube', 'slug' => 'youtube', 'max' => 5000],
            'youtube_shorts' => ['name' => 'YouTube Shorts', 'slug' => 'youtube_shorts', 'max' => 2200],
            'tiktok' => ['name' => 'TikTok', 'slug' => 'tiktok', 'max' => 2200],
            'instagram_reels' => ['name' => 'Instagram Reels', 'slug' => 'instagram_reels', 'max' => 2200],
            'instagram_feed' => ['name' => 'Instagram Feed', 'slug' => 'instagram_feed', 'max' => 2200],
        ];

        $out = [];
        foreach ($platforms as $key => $meta) {
            $out[$key] = $this->buildForPlatform(
                $project,
                $meta['name'],
                $meta['slug'],
                $creditsBlock,
                $credits,
                $meta['max']
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
    ): array {
        $title = $project->name;
        $summary = $this->projectSummary($project);
        $hashtags = $this->hashtagsFor($slug, $project);

        $intro = match ($slug) {
            'youtube', 'youtube_shorts' => "{$title}\n\n{$summary}\n\n{$hashtags}",
            'tiktok', 'instagram_reels' => "{$title} — {$this->shortHook($project)}\n\n{$hashtags}",
            'instagram_feed' => "{$title}\n\n{$summary}\n\n{$hashtags}",
            default => "{$title}\n\n{$summary}",
        };

        $creditsSection = $creditsBlock !== '' ? "\n\n".$creditsBlock : '';

        $description = $intro.$creditsSection;

        if (mb_strlen($description) > $maxChars) {
            $budget = $maxChars - mb_strlen($creditsSection) - 20;
            $intro = mb_substr($intro, 0, max(200, $budget)).'…';
            $description = $intro.$creditsSection;
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
        ];
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
