<?php

namespace App\Services\ImageStudio;

use App\Models\Asset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImageStudioService
{
    public function __construct(
        private ProjectStorageService $storage,
    ) {}

    public function catalog(): array
    {
        $groups = config('image_studio.groups', []);
        $presets = collect(config('image_studio.presets', []))
            ->map(fn (array $meta, string $slug) => array_merge($meta, [
                'slug' => $slug,
                'group_label' => $groups[$meta['group'] ?? 'web'] ?? $meta['group'],
            ]))
            ->values();

        return [
            'presets' => $presets,
            'groups' => $groups,
            'export_formats' => config('image_studio.export_formats', []),
            'fonts' => config('thumbnail_templates.fonts', []),
            'defaults' => config('image_studio.defaults', []),
            'background_removal_available' => app(BackgroundRemovalService::class)->isAvailable(),
        ];
    }

    public function designsDir(Project $project): string
    {
        $dir = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'designs';
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    public function designPath(Project $project, string $presetSlug): string
    {
        return $this->designsDir($project).DIRECTORY_SEPARATOR.$presetSlug.'.json';
    }

    public function loadDesign(Project $project, ?string $presetSlug = null): array
    {
        $settings = $project->settings['image_studio'] ?? [];
        $preset = $presetSlug ?? ($settings['active_preset'] ?? config('image_studio.defaults.preset', 'ig_feed_square'));
        $path = $this->designPath($project, $preset);

        $canvas = null;
        if (File::exists($path)) {
            $canvas = json_decode(File::get($path), true);
        }

        $meta = config('image_studio.presets.'.$preset, config('image_studio.presets.ig_feed_square'));

        return [
            'preset' => $preset,
            'width' => (int) ($canvas['width'] ?? $meta['width'] ?? 1080),
            'height' => (int) ($canvas['height'] ?? $meta['height'] ?? 1080),
            'canvas' => is_array($canvas) ? $canvas : null,
            'updated_at' => $settings['designs'][$preset]['updated_at'] ?? null,
        ];
    }

    public function saveDesign(Project $project, string $presetSlug, array $canvasJson): array
    {
        $this->storage->ensureStructure($project);
        $meta = config('image_studio.presets.'.$presetSlug);
        if (! $meta) {
            throw new \InvalidArgumentException('Preset inválido.');
        }

        $canvasJson['version'] = config('image_studio.version', 1);
        $canvasJson['preset'] = $presetSlug;
        $canvasJson['width'] = (int) ($canvasJson['width'] ?? $meta['width']);
        $canvasJson['height'] = (int) ($canvasJson['height'] ?? $meta['height']);
        $canvasJson['saved_at'] = now()->toIso8601String();

        File::put(
            $this->designPath($project, $presetSlug),
            json_encode($canvasJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $settings = $project->settings ?? [];
        $studio = $settings['image_studio'] ?? [];
        $studio['active_preset'] = $presetSlug;
        $studio['designs'][$presetSlug] = [
            'updated_at' => now()->toIso8601String(),
            'width' => $canvasJson['width'],
            'height' => $canvasJson['height'],
        ];
        $settings['image_studio'] = $studio;
        $project->update(['settings' => $settings]);

        return $this->loadDesign($project->fresh(), $presetSlug);
    }

    public function storeExport(Project $project, UploadedFile $file, string $format, ?string $presetSlug = null): array
    {
        $this->storage->ensureStructure($project);
        $ext = strtolower($file->getClientOriginalExtension() ?: $format);
        $slug = $presetSlug ?: ($project->settings['image_studio']['active_preset'] ?? 'design');
        $filename = 'studio_'.$slug.'_'.Str::random(6).'.'.$ext;
        $path = $this->designsDir($project).DIRECTORY_SEPARATOR.$filename;
        $file->move(dirname($path), basename($path));

        return [
            'filename' => $filename,
            'path' => $path,
            'format' => $format,
            'url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'designs',
                'filename' => $filename,
            ]).'?t='.time(),
        ];
    }

    public function pushToThumbnail(Project $project, string $absolutePath, ?string $platform = null): array
    {
        if (! file_exists($absolutePath)) {
            throw new \InvalidArgumentException('Arquivo exportado não encontrado.');
        }

        $this->storage->ensureStructure($project);
        $platform = $platform ?? 'youtube_landscape';
        $meta = config('thumbnail_templates.platforms.'.$platform);
        $filename = $meta['filename'] ?? 'thumbnail_'.$platform.'.jpg';
        $dest = $this->storage->thumbPath($project, $filename);

        File::copy($absolutePath, $dest);

        $settings = $project->settings ?? [];
        $thumbnails = $settings['thumbnails'] ?? [];
        $thumbnails[$platform] = array_merge(
            $thumbnails[$platform] ?? [],
            [
                'image_source' => 'upload',
                'custom_image_path' => $dest,
            ]
        );
        $settings['thumbnails'] = $thumbnails;
        $project->update(['settings' => $settings]);

        return [
            'platform' => $platform,
            'filename' => $filename,
            'url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'thumbs',
                'filename' => $filename,
            ]).'?t='.time(),
        ];
    }

    public function pushToAssetLibrary(Project $project, string $absolutePath, string $originalName = 'design.png'): Asset
    {
        if (! file_exists($absolutePath)) {
            throw new \InvalidArgumentException('Arquivo não encontrado.');
        }

        $this->storage->ensureStructure($project);
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'png');
        $filename = 'studio_'.Str::random(10).'.'.$ext;
        $dest = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$filename;
        File::copy($absolutePath, $dest);

        return Asset::create([
            'project_id' => $project->id,
            'type' => 'image',
            'source' => 'image_studio',
            'file_path' => $dest,
            'file_hash' => hash_file('sha256', $dest),
            'item_title' => $originalName,
            'metadata' => ['from' => 'image_studio'],
            'downloaded_at' => now(),
        ]);
    }
}
