<?php

namespace App\Services\ProjectBundle;

use App\Models\Asset;
use App\Models\AudioTrack;
use App\Models\Narration;
use App\Models\Project;
use App\Models\Slide;
use App\Models\SoundEffect;
use App\Models\User;
use App\Services\ProjectStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ProjectBundleImporter
{
    public function __construct(private ProjectStorageService $storage) {}

    public function import(User $user, UploadedFile $zipFile): Project
    {
        $tempRoot = storage_path('framework/bundle-import/'.uniqid('bundle_', true));
        File::ensureDirectoryExists($tempRoot);

        try {
            $extractPath = $tempRoot.DIRECTORY_SEPARATOR.'extracted';
            File::ensureDirectoryExists($extractPath);

            if (! $this->extractZip($zipFile, $extractPath)) {
                throw ValidationException::withMessages([
                    'bundle' => 'Não foi possível abrir o arquivo ZIP.',
                ]);
            }

            $bundleRoot = $this->resolveBundleRoot($extractPath);
            $manifestPath = $bundleRoot.DIRECTORY_SEPARATOR.'manifest.json';
            $exportPath = $bundleRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'project_export.json';

            if (! File::exists($manifestPath) || ! File::exists($exportPath)) {
                throw ValidationException::withMessages([
                    'bundle' => 'ZIP inválido: manifest.json ou database/project_export.json ausente.',
                ]);
            }

            $manifest = json_decode(File::get($manifestPath), true);
            $exportData = json_decode(File::get($exportPath), true);

            if (! is_array($manifest) || ! is_array($exportData)) {
                throw ValidationException::withMessages([
                    'bundle' => 'Conteúdo do bundle corrompido ou ilegível.',
                ]);
            }

            $version = (int) ($manifest['manifest_version'] ?? 0);
            if ($version !== ProjectBundleExporter::MANIFEST_VERSION) {
                throw ValidationException::withMessages([
                    'bundle' => 'Versão do bundle não suportada (v'.$version.'). Atualize o CriaSys Editor.',
                ]);
            }

            $projectData = $exportData['project'] ?? $manifest['project'] ?? [];
            $name = trim((string) ($projectData['name'] ?? 'Projeto importado'));
            if ($name === '') {
                $name = 'Projeto importado';
            }

            $project = $user->projects()->create([
                'name' => $name,
                'description' => $projectData['description'] ?? null,
                'status' => 'active',
                'settings' => $projectData['settings'] ?? [],
            ]);

            $this->storage->ensureStructure($project);
            $projectPath = $this->storage->projectPath($project);

            $mediaSource = $bundleRoot.DIRECTORY_SEPARATOR.'media';
            if (File::isDirectory($mediaSource)) {
                foreach (File::directories($mediaSource) as $dir) {
                    $subdir = basename($dir);
                    File::copyDirectory($dir, $projectPath.DIRECTORY_SEPARATOR.$subdir);
                }
            }

            $publishSource = $bundleRoot.DIRECTORY_SEPARATOR.'publish';
            if (File::isDirectory($publishSource)) {
                File::ensureDirectoryExists($projectPath.DIRECTORY_SEPARATOR.'exports');
                foreach (File::files($publishSource) as $file) {
                    File::copy($file->getPathname(), $projectPath.DIRECTORY_SEPARATOR.'exports'.DIRECTORY_SEPARATOR.$file->getFilename());
                }
            }

            $assetMap = $this->importAssets($project, $exportData['assets'] ?? []);
            $this->importSlides($project, $exportData['slides'] ?? []);
            $this->importAudioTracks($project, $exportData['audio_tracks'] ?? [], $assetMap);
            $this->importSoundEffects($project, $exportData['sound_effects'] ?? [], $assetMap);
            $this->importNarration($project, $exportData['narration'] ?? null);

            $settings = $project->settings ?? [];
            $settings['imported_from_bundle_at'] = now()->toIso8601String();
            $settings['bundle_source_exported_at'] = $manifest['exported_at'] ?? null;
            $project->update(['settings' => $settings]);

            return $project->fresh()->load(['slides', 'assets', 'audioTracks', 'soundEffects', 'narrations']);
        } finally {
            File::deleteDirectory($tempRoot);
        }
    }

    private function extractZip(UploadedFile $zipFile, string $dest): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($zipFile->getRealPath()) !== true) {
            return false;
        }

        $zip->extractTo($dest);
        $zip->close();

        return true;
    }

    private function resolveBundleRoot(string $extractPath): string
    {
        if (File::exists($extractPath.DIRECTORY_SEPARATOR.'manifest.json')) {
            return $extractPath;
        }

        $dirs = File::directories($extractPath);
        if (count($dirs) === 1 && File::exists($dirs[0].DIRECTORY_SEPARATOR.'manifest.json')) {
            return $dirs[0];
        }

        return $extractPath;
    }

    /** @param  list<array<string, mixed>>  $rows  @return array<int, int> */
    private function importAssets(Project $project, array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $exportId = isset($row['_export_id']) ? (int) $row['_export_id'] : null;
            unset($row['_export_id']);

            $row = $this->resolveAbsolutePaths($project, $row);
            $asset = Asset::create(array_merge($row, ['project_id' => $project->id]));

            if ($exportId !== null) {
                $map[$exportId] = $asset->id;
            }
        }

        return $map;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importSlides(Project $project, array $rows): void
    {
        foreach ($rows as $row) {
            $row = $this->resolveAbsolutePaths($project, $row);
            Slide::create(array_merge($row, ['project_id' => $project->id]));
        }
    }

    /** @param  list<array<string, mixed>>  $rows  @param  array<int, int>  $assetMap */
    private function importAudioTracks(Project $project, array $rows, array $assetMap): void
    {
        foreach ($rows as $row) {
            $row = $this->resolveAbsolutePaths($project, $row);
            if (isset($row['asset_id']) && is_numeric($row['asset_id'])) {
                $oldId = (int) $row['asset_id'];
                $row['asset_id'] = $assetMap[$oldId] ?? null;
            } else {
                $row['asset_id'] = null;
            }

            if (! empty($row['clips']) && is_array($row['clips'])) {
                foreach ($row['clips'] as $j => $clip) {
                    if (isset($clip['asset_id']) && is_numeric($clip['asset_id'])) {
                        $oldId = (int) $clip['asset_id'];
                        $row['clips'][$j]['asset_id'] = $assetMap[$oldId] ?? null;
                    }
                    if (! empty($clip['file_path'])) {
                        $row['clips'][$j]['file_path'] = $this->resolvePath($project, (string) $clip['file_path']);
                    }
                }
            }

            AudioTrack::create(array_merge($row, ['project_id' => $project->id]));
        }
    }

    /** @param  list<array<string, mixed>>  $rows  @param  array<int, int>  $assetMap */
    private function importSoundEffects(Project $project, array $rows, array $assetMap): void
    {
        foreach ($rows as $row) {
            $row = $this->resolveAbsolutePaths($project, $row);
            if (isset($row['asset_id']) && is_numeric($row['asset_id'])) {
                $oldId = (int) $row['asset_id'];
                $row['asset_id'] = $assetMap[$oldId] ?? null;
            } else {
                $row['asset_id'] = null;
            }

            SoundEffect::create(array_merge($row, ['project_id' => $project->id]));
        }
    }

    /** @param  array<string, mixed>|null  $row */
    private function importNarration(Project $project, ?array $row): void
    {
        if (! $row) {
            return;
        }

        $row = $this->resolveAbsolutePaths($project, $row);
        Narration::create(array_merge($row, ['project_id' => $project->id]));
    }

    /** @param  array<string, mixed>  $row */
    private function resolveAbsolutePaths(Project $project, array $row): array
    {
        foreach (['file_path', 'image_path', 'video_path', 'audio_path'] as $key) {
            if (! empty($row[$key]) && is_string($row[$key])) {
                $row[$key] = $this->resolvePath($project, $row[$key]);
            }
        }

        return $row;
    }

    private function resolvePath(Project $project, string $relative): string
    {
        $normalized = str_replace('\\', '/', ltrim($relative, '/'));
        $parts = explode('/', $normalized, 2);
        $subdir = $parts[0] ?? 'assets';
        $filename = $parts[1] ?? basename($normalized);

        if (! in_array($subdir, ['slides', 'audio', 'assets', 'thumbs', 'designs'], true)) {
            $subdir = 'assets';
            $filename = basename($normalized);
        }

        return $this->storage->projectPath($project).DIRECTORY_SEPARATOR.$subdir.DIRECTORY_SEPARATOR.basename($filename);
    }
}
