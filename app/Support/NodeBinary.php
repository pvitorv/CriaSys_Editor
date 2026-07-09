<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class NodeBinary
{
    private static ?string $resolved = null;

    public static function path(): string
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $configured = config('criasys.node_path');
        if (is_string($configured) && $configured !== '' && self::isExecutable($configured)) {
            return self::$resolved = $configured;
        }

        foreach (self::candidatePaths() as $candidate) {
            if (self::isExecutable($candidate)) {
                return self::$resolved = $candidate;
            }
        }

        return self::$resolved = 'node';
    }

    /**
     * @return list<string>
     */
    private static function candidatePaths(): array
    {
        $paths = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $programFiles = getenv('ProgramFiles') ?: 'C:\\Program Files';
            $programFilesX86 = getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)';

            $paths[] = $programFiles.'\\nodejs\\node.exe';
            $paths[] = $programFilesX86.'\\nodejs\\node.exe';

            $laragonRoot = getenv('LARAGON_ROOT') ?: 'C:\\laragon';
            if (File::isDirectory($laragonRoot.'\\bin\\nodejs')) {
                foreach (File::directories($laragonRoot.'\\bin\\nodejs') as $dir) {
                    $paths[] = $dir.'\\node.exe';
                }
            }

            $where = trim(Process::run(['where.exe', 'node'])->output());
            if ($where !== '') {
                foreach (preg_split('/\R/', $where) as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $paths[] = $line;
                    }
                }
            }
        } else {
            $which = trim(Process::run(['which', 'node'])->output());
            if ($which !== '') {
                $paths[] = $which;
            }
        }

        return array_values(array_unique($paths));
    }

    private static function isExecutable(string $path): bool
    {
        if ($path === 'node') {
            return Process::run([$path, '--version'])->successful();
        }

        if (! File::exists($path)) {
            return false;
        }

        return Process::run([$path, '--version'])->successful();
    }
}
