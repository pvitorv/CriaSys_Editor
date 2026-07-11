<?php

namespace App\Support;

class DeploymentMode
{
    public const DESKTOP = 'desktop';

    public const ONLINE = 'online';

    public static function current(): string
    {
        $mode = strtolower((string) config('criasys.deployment', self::DESKTOP));

        return in_array($mode, [self::DESKTOP, self::ONLINE], true)
            ? $mode
            : self::DESKTOP;
    }

    public static function isDesktop(): bool
    {
        return self::current() === self::DESKTOP;
    }

    public static function isOnline(): bool
    {
        return self::current() === self::ONLINE;
    }

    public static function maxActiveProjects(): ?int
    {
        if (self::isDesktop()) {
            return null;
        }

        return max(1, (int) config('criasys.online_max_active_projects', 1));
    }

    /** @return array<string, mixed> */
    public static function meta(): array
    {
        return [
            'deployment' => self::current(),
            'is_desktop' => self::isDesktop(),
            'is_online' => self::isOnline(),
            'max_active_projects' => self::maxActiveProjects(),
            'allows_project_import' => true,
            'allows_unlimited_projects' => self::isDesktop(),
            'requires_export_before_next' => self::isOnline(),
        ];
    }
}
