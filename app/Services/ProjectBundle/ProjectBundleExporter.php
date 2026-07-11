<?php

namespace App\Services\ProjectBundle;

use App\Models\Project;
use App\Services\Export\PlatformPostDescriptionService;
use App\Services\Export\ProjectAttributionCatalog;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ProjectBundleExporter
{
    public const MANIFEST_VERSION = 1;

    public function __construct(private ProjectStorageService $storage) {}

    /**
     * @return array{path: string, url: string, filename: string}
     */
    public function export(Project $project): array
    {
        $project->load(['slides', 'assets', 'audioTracks', 'soundEffects', 'narrations']);

        $timestamp = now()->format('Ymd_His');
        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $project->name) ?: 'projeto';
        $bundleName = "bundle_{$safeName}_{$timestamp}";
        $exportDir = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'exports';
        File::ensureDirectoryExists($exportDir);

        $basePath = $exportDir.DIRECTORY_SEPARATOR.$bundleName;
        File::ensureDirectoryExists($basePath);
        File::ensureDirectoryExists($basePath.DIRECTORY_SEPARATOR.'database');
        File::ensureDirectoryExists($basePath.DIRECTORY_SEPARATOR.'publish');

        $mediaDirs = ['slides', 'audio', 'assets', 'thumbs', 'designs'];
        $projectPath = $this->storage->projectPath($project);

        foreach ($mediaDirs as $dir) {
            $source = $projectPath.DIRECTORY_SEPARATOR.$dir;
            if (! File::isDirectory($source)) {
                continue;
            }
            File::copyDirectory($source, $basePath.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.$dir);
        }

        $this->copyPublishFiles($projectPath.DIRECTORY_SEPARATOR.'exports', $basePath.DIRECTORY_SEPARATOR.'publish');

        $exportData = $this->buildExportData($project);
        File::put(
            $basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'project_export.json',
            json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $manifest = [
            'manifest_version' => self::MANIFEST_VERSION,
            'criasys_editor' => config('app.name', 'CriaSys_Editor'),
            'exported_at' => now()->toIso8601String(),
            'project' => [
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'settings' => $project->settings ?? [],
            ],
            'media_dirs' => $mediaDirs,
        ];

        File::put(
            $basePath.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        File::put(
            $basePath.DIRECTORY_SEPARATOR.'LEIA-ME.txt',
            $this->buildReadme($project->name)
        );

        $zipPath = $exportDir.DIRECTORY_SEPARATOR."{$bundleName}.zip";
        $this->createZip($basePath, $zipPath);
        File::deleteDirectory($basePath);

        $settings = $project->settings ?? [];
        $settings['bundle_exported_at'] = now()->toIso8601String();
        $project->update(['settings' => $settings]);

        return [
            'path' => $zipPath,
            'filename' => basename($zipPath),
            'url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'exports',
                'filename' => basename($zipPath),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function buildExportData(Project $project): array
    {
        return [
            'project' => [
                'name' => $project->name,
                'description' => $project->description,
                'settings' => $project->settings ?? [],
            ],
            'slides' => $project->slides->map(fn ($s) => $this->relativePaths($s->only([
                'order', 'title', 'subtitle', 'body_text', 'image_path', 'video_path',
                'text_style', 'duration_seconds', 'duration_mode', 'video_duration_seconds',
                'transition_type', 'narration_text',
            ])))->values()->all(),
            'assets' => $project->assets->map(fn ($a) => array_merge(
                ['_export_id' => $a->id],
                $this->relativePaths($a->only([
                    'type', 'file_path', 'file_hash', 'source', 'item_title', 'item_external_id',
                    'license_type', 'requires_attribution', 'attribution_text', 'original_url', 'metadata',
                ]))
            ))->values()->all(),
            'audio_tracks' => $project->audioTracks->map(fn ($t) => $this->relativePaths($t->only([
                'type', 'track_slot', 'asset_id', 'file_path', 'volume', 'start_at', 'trim_in', 'trim_out',
                'source_duration', 'ducking_enabled', 'loop_enabled', 'clips',
            ])))->values()->all(),
            'sound_effects' => $project->soundEffects->map(fn ($fx) => $this->relativePaths($fx->only([
                'label', 'asset_id', 'file_path', 'start_at', 'trim_in', 'trim_out', 'source_duration',
                'clip_duration', 'volume',
            ])))->values()->all(),
            'narration' => optional($project->latestNarration(), fn ($n) => $this->relativePaths($n->only([
                'full_script', 'audio_path', 'engine', 'voice', 'duration_seconds',
                'trim_in', 'trim_out', 'segments', 'status',
            ]))),
            'credits_block' => app(ProjectAttributionCatalog::class)->creditsBlock($project),
            'platform_descriptions' => app(PlatformPostDescriptionService::class)->generateAll($project),
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function relativePaths(array $row): array
    {
        foreach (['file_path', 'image_path', 'video_path', 'audio_path'] as $key) {
            if (! empty($row[$key]) && is_string($row[$key])) {
                $row[$key] = $this->pathToRelative($row[$key]);
            }
        }

        if (! empty($row['clips']) && is_array($row['clips'])) {
            foreach ($row['clips'] as $i => $clip) {
                if (! empty($clip['file_path'])) {
                    $row['clips'][$i]['file_path'] = $this->pathToRelative($clip['file_path']);
                }
            }
        }

        return $row;
    }

    private function pathToRelative(string $absolute): string
    {
        $normalized = str_replace('\\', '/', $absolute);
        foreach (['assets/', 'slides/', 'audio/', 'thumbs/', 'designs/'] as $prefix) {
            $pos = stripos($normalized, $prefix);
            if ($pos !== false) {
                return substr($normalized, $pos);
            }
        }

        return 'assets/'.basename($absolute);
    }

    private function copyPublishFiles(string $exportsPath, string $destPublish): void
    {
        if (! File::isDirectory($exportsPath)) {
            return;
        }

        foreach (File::files($exportsPath) as $file) {
            $name = $file->getFilename();
            if (str_starts_with($name, 'descricao_') || $name === 'creditos_materiais.txt') {
                File::copy($file->getPathname(), $destPublish.DIRECTORY_SEPARATOR.$name);
            }
        }
    }

    private function createZip(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o arquivo ZIP do bundle.');
        }

        foreach (File::allFiles($sourceDir) as $file) {
            $relative = str_replace($sourceDir.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $relative));
        }

        $zip->close();
    }

    private function buildReadme(string $projectName): string
    {
        return implode("\n", [
            'CriaSys Editor — Project Bundle',
            '================================',
            '',
            "Projeto: {$projectName}",
            'Este arquivo ZIP contém slides, áudio, assets, descrições e metadados.',
            '',
            'Para importar: Dashboard → Importar projeto → selecione este ZIP.',
            'Modo desktop: projetos ilimitados.',
            'Modo online: exporte e exclua o projeto atual antes de importar outro.',
            '',
        ]);
    }
}
