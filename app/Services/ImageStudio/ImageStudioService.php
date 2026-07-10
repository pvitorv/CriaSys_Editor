<?php

namespace App\Services\ImageStudio;

use App\Enums\LicenseType;
use App\Models\Asset;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectStorageService;
use App\Services\Render\ThumbnailFrameDrawer;
use App\Services\Render\ThumbnailRenderer;
use App\Services\ThumbnailFrameLibraryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImageStudioService
{
    public function __construct(
        private ProjectStorageService $storage,
        private ThumbnailRenderer $thumbnailRenderer,
    ) {}

    public function catalog(?User $user = null): array
    {
        $groups = config('image_studio.groups', []);
        $presets = collect(config('image_studio.presets', []))
            ->map(fn (array $meta, string $slug) => array_merge($meta, [
                'slug' => $slug,
                'group_label' => $groups[$meta['group'] ?? 'web'] ?? $meta['group'],
            ]))
            ->values();

        $frameCatalog = app(ThumbnailFrameLibraryService::class)->catalogForUser($user);

        return [
            'presets' => $presets,
            'groups' => $groups,
            'export_formats' => config('image_studio.export_formats', []),
            'fonts' => $this->fontCatalog(),
            'font_groups' => config('image_studio_fonts.groups', []),
            'icon_fonts' => config('image_studio_fonts.icon_fonts', []),
            'icon_glyphs' => config('image_studio_fonts.icon_glyphs', []),
            'defaults' => config('image_studio.defaults', []),
            'templates' => collect(config('image_studio.templates', []))
                ->map(fn (array $meta, string $slug) => array_merge($meta, ['slug' => $slug]))
                ->values(),
            'frames' => $frameCatalog['frames'],
            'frame_categories' => $frameCatalog['categories'],
            'elements' => collect(config('image_studio_stickers.elements', []))
                ->merge(config('image_studio_shapes.elements', []))
                ->merge(config('image_studio.elements', []))
                ->merge(config('image_studio_icons.elements', []))
                ->values(),
            'element_groups' => array_merge(
                config('image_studio_stickers.groups', []),
                config('image_studio_shapes.groups', []),
                config('image_studio.element_groups', []),
                config('image_studio_icons.groups', [])
            ),
            'background_removal_available' => true,
            'background_removal_client' => true,
            'preset_platform_map' => config('image_studio.preset_platform_map', []),
            'primary_formats' => config('image_studio.primary_formats', []),
            'group_order' => config('image_studio.group_order', []),
        ];
    }

    public function resolvePlatformForPreset(?string $presetSlug, ?string $fallbackPlatform = null): string
    {
        if ($presetSlug) {
            $mapped = config('image_studio.preset_platform_map.'.$presetSlug);
            if ($mapped) {
                return $mapped;
            }
        }

        return $fallbackPlatform ?? config('thumbnail_templates.default_platform', 'youtube_landscape');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fontCatalog(): array
    {
        $groups = config('image_studio_fonts.groups', []);
        $fonts = [];

        foreach (config('thumbnail_templates.fonts', []) as $meta) {
            $slug = $meta['slug'] ?? null;
            if (! $slug) {
                continue;
            }
            $groupKey = match ($meta['group'] ?? '') {
                'Destaque' => 'system',
                'Sans-serif', 'Serif', 'Pop & criativo', 'Tech & código' => 'system',
                default => 'system',
            };
            $fonts[] = array_merge($meta, [
                'source' => 'system',
                'family' => $meta['label'] ?? $slug,
                'group_key' => $groupKey,
                'group_label' => $meta['group'] ?? ($groups[$groupKey] ?? 'Sistema'),
                'weights' => [400, 700],
                'italic' => str_contains(strtolower($meta['slug'] ?? ''), 'italic') || str_contains(strtolower($meta['label'] ?? ''), 'Italic'),
            ]);
        }

        foreach (config('image_studio_fonts.google_fonts', []) as $meta) {
            $groupKey = $meta['group'] ?? 'google_sans';
            $fonts[] = array_merge($meta, [
                'source' => 'google',
                'group_key' => $groupKey,
                'group_label' => $groups[$groupKey] ?? $groupKey,
            ]);
        }

        foreach (config('image_studio_fonts.icon_fonts', []) as $meta) {
            $groupKey = $meta['group'] ?? 'icons_fa';
            $fonts[] = array_merge($meta, [
                'source' => 'icon',
                'group_key' => $groupKey,
                'group_label' => $groups[$groupKey] ?? 'Ícones',
            ]);
        }

        return $fonts;
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
        $preset = $presetSlug ?? ($settings['active_preset'] ?? config('image_studio.defaults.preset', 'yt_thumb'));
        $path = $this->designPath($project, $preset);

        $canvas = null;
        if (File::exists($path)) {
            $canvas = json_decode(File::get($path), true);
        }

        $meta = config('image_studio.presets.'.$preset, config('image_studio.presets.yt_thumb'));

        return [
            'preset' => $preset,
            'width' => (int) ($canvas['width'] ?? $meta['width'] ?? 1920),
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

    public function pushToThumbnail(Project $project, string $absolutePath, ?string $platform = null, ?string $studioPreset = null): array
    {
        if (! file_exists($absolutePath)) {
            throw new \InvalidArgumentException('Arquivo exportado não encontrado.');
        }

        $this->storage->ensureStructure($project);
        $platform = $this->resolvePlatformForPreset($studioPreset, $platform);
        $exportPreset = $this->thumbnailRenderer->presetForPlatform($platform);
        $meta = config('thumbnail_templates.platforms.'.$platform);
        $filename = $meta['filename'] ?? 'thumbnail_'.$platform.'.jpg';
        $dest = $this->storage->thumbPath($project, $filename);

        $this->composeStudioExportToThumbnail(
            $absolutePath,
            $dest,
            (int) $exportPreset->width,
            (int) $exportPreset->height
        );

        $settings = $project->settings ?? [];
        $thumbnails = $settings['thumbnails'] ?? [];
        $platformSettings = array_merge(
            $this->thumbnailRenderer->resolveSettings($project, $platform),
            [
                'image_source' => 'image_studio',
                'template' => 'image_studio_final',
                'custom_image_path' => $dest,
                'frame_slug' => 'none',
                'overlay_opacity' => 0,
                'accent_opacity' => 0,
                'background_opacity' => 0,
                'image_studio_preset' => $studioPreset,
                'image_studio_export' => basename($absolutePath),
                'image_studio_pushed_at' => now()->toIso8601String(),
            ]
        );
        $thumbnails[$platform] = $platformSettings;
        $settings['thumbnails'] = $thumbnails;
        $project->update(['settings' => $settings]);

        $freshSettings = $this->thumbnailRenderer->resolveSettings($project->fresh(), $platform);

        return [
            'platform' => $platform,
            'filename' => $filename,
            'width' => (int) $exportPreset->width,
            'height' => (int) $exportPreset->height,
            'settings' => $freshSettings,
            'url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'thumbs',
                'filename' => $filename,
            ]).'?t='.time(),
        ];
    }

    private function composeStudioExportToThumbnail(string $sourcePath, string $destPath, int $width, int $height): void
    {
        $source = $this->loadStudioImage($sourcePath);
        if (! $source) {
            throw new \InvalidArgumentException('Não foi possível ler a imagem exportada do Image Studio.');
        }

        File::ensureDirectoryExists(dirname($destPath));

        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 0, 0, 0));
        imagealphablending($canvas, true);

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $scale = max($width / max(1, $srcW), $height / max(1, $srcH));
        $newW = (int) ($srcW * $scale);
        $newH = (int) ($srcH * $scale);
        $dx = (int) (($width - $newW) / 2);
        $dy = (int) (($height - $newH) / 2);

        imagecopyresampled($canvas, $source, $dx, $dy, 0, 0, $newW, $newH, $srcW, $srcH);
        imagejpeg($canvas, $destPath, 93);

        imagedestroy($source);
        imagedestroy($canvas);
    }

    private function loadStudioImage(string $path): ?\GdImage
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path) ?: null,
            'png' => @imagecreatefrompng($path) ?: null,
            'webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => @imagecreatefrompng($path) ?: @imagecreatefromjpeg($path) ?: null,
        };
    }

    public function pushToAssetLibrary(
        Project $project,
        string $absolutePath,
        string $originalName = 'design.png',
        ?string $studioPreset = null
    ): Asset {
        if (! file_exists($absolutePath)) {
            throw new \InvalidArgumentException('Arquivo não encontrado.');
        }

        $this->storage->ensureStructure($project);
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'png');
        $filename = 'studio_'.Str::random(10).'.'.$ext;
        $dest = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$filename;
        File::copy($absolutePath, $dest);

        $presetMeta = $studioPreset ? config('image_studio.presets.'.$studioPreset) : null;
        $title = $presetMeta
            ? 'Image Studio — '.($presetMeta['name'] ?? $studioPreset)
            : pathinfo($originalName, PATHINFO_FILENAME);

        return Asset::create([
            'project_id' => $project->id,
            'type' => 'image',
            'source' => 'image_studio',
            'file_path' => $dest,
            'file_hash' => hash_file('sha256', $dest),
            'item_title' => $title,
            'license_type' => LicenseType::Local->value,
            'requires_attribution' => false,
            'metadata' => [
                'from' => 'image_studio',
                'studio_preset' => $studioPreset,
                'original_export' => basename($absolutePath),
            ],
            'downloaded_at' => now(),
        ]);
    }

    public function renderFramePreview(Project $project, int $width, int $height, string $slug, array $options = []): array
    {
        if ($slug === '' || $slug === 'none') {
            throw new \InvalidArgumentException('Moldura inválida.');
        }

        $this->storage->ensureStructure($project);
        $img = imagecreatetruecolor($width, $height);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);

        $settings = array_merge(config('thumbnail_frames.defaults', []), [
            'frame_slug' => $slug,
            'frame_color' => $options['color'] ?? '#ffffff',
            'frame_secondary_color' => $options['secondary_color'] ?? '#ef4444',
            'frame_width' => (int) ($options['frame_width'] ?? 48),
            'frame_opacity' => (int) ($options['opacity'] ?? 100),
            'frame_inset' => (int) ($options['inset'] ?? 12),
        ]);

        $drawer = app(ThumbnailFrameDrawer::class);
        $meta = app(ThumbnailFrameLibraryService::class)->resolveFrameMeta($project->user, $slug)
            ?? config('thumbnail_frames.frames.'.$slug);

        if (! $meta) {
            throw new \InvalidArgumentException("Moldura \"{$slug}\" não existe no catálogo.");
        }

        $drawer->apply($img, $settings, $width, $height, $project);

        $filename = 'frame_'.$slug.'_'.$width.'x'.$height.'_'.Str::random(4).'.png';
        $path = $this->designsDir($project).DIRECTORY_SEPARATOR.$filename;
        imagepng($img, $path);
        imagedestroy($img);

        $binary = file_get_contents($path) ?: '';

        return [
            'filename' => $filename,
            'url' => '/api/projects/'.$project->id.'/files/designs/'.$filename.'?t='.time(),
            'data_url' => 'data:image/png;base64,'.base64_encode($binary),
            'width' => $width,
            'height' => $height,
        ];
    }
}
