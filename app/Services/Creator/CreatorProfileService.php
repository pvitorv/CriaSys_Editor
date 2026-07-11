<?php

namespace App\Services\Creator;

use App\Models\Project;
use App\Models\User;

class CreatorProfileService
{
    /** @return array<string, string|null> */
    public function defaults(): array
    {
        return [
            'display_name' => null,
            'youtube' => null,
            'instagram' => null,
            'tiktok' => null,
            'website' => null,
            'subscribe_cta' => null,
        ];
    }

    /** @return array<string, string|null> */
    public function forUser(User $user): array
    {
        return array_merge($this->defaults(), $user->creator_profile ?? []);
    }

    /** @return array<string, string|null> */
    public function forProject(Project $project): array
    {
        $project->loadMissing('user');
        $base = $this->forUser($project->user);
        $overrides = ($project->settings ?? [])['creator_profile'] ?? [];

        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (array_key_exists($key, $base)) {
                $base[$key] = trim((string) $value);
            }
        }

        return $base;
    }

    public function hasAnyLink(array $profile): bool
    {
        foreach (['youtube', 'instagram', 'tiktok', 'website'] as $key) {
            if (! empty($profile[$key])) {
                return true;
            }
        }

        return false;
    }

    public function ctaBlockForPlatform(array $profile, string $platformSlug): string
    {
        if (! $this->hasAnyLink($profile) && empty($profile['subscribe_cta'])) {
            return '';
        }

        $lines = [];
        $custom = trim((string) ($profile['subscribe_cta'] ?? ''));
        if ($custom !== '') {
            $lines[] = $custom;
        }

        $linkLines = $this->linkLinesForPlatform($profile, $platformSlug);
        if ($linkLines !== []) {
            if ($lines !== []) {
                $lines[] = '';
            }
            $lines = array_merge($lines, $linkLines);
        }

        if ($lines === []) {
            return '';
        }

        return "\n\n---\n".implode("\n", $lines);
    }

    /** @return list<string> */
    private function linkLinesForPlatform(array $profile, string $platformSlug): array
    {
        $order = match (true) {
            str_contains($platformSlug, 'youtube') => ['youtube', 'instagram', 'tiktok', 'website'],
            str_contains($platformSlug, 'tiktok') => ['tiktok', 'instagram', 'youtube', 'website'],
            str_contains($platformSlug, 'instagram') => ['instagram', 'youtube', 'tiktok', 'website'],
            default => ['website', 'youtube', 'instagram', 'tiktok'],
        };

        $labels = [
            'youtube' => 'YouTube',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'website' => 'Site',
        ];

        $lines = [];
        foreach ($order as $key) {
            $url = trim((string) ($profile[$key] ?? ''));
            if ($url === '') {
                continue;
            }
            $lines[] = ($labels[$key] ?? ucfirst($key)).': '.$url;
        }

        return $lines;
    }
}
