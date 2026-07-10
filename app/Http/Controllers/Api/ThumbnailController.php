<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExportPreset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use App\Services\Render\ThumbnailRenderer;
use App\Services\ThumbnailFrameLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ThumbnailController extends Controller
{
    public function templates(Request $request, ThumbnailRenderer $renderer, ThumbnailFrameLibraryService $frameLibrary): JsonResponse
    {
        $templates = collect(config('thumbnail_templates.templates', []))
            ->map(fn (array $meta, string $slug) => [
                'slug' => $slug,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'category' => $meta['category'] ?? 'geral',
            ])
            ->values();

        $platforms = collect($renderer->platformPresets())
            ->map(fn (array $meta, string $slug) => [
                'slug' => $slug,
                'name' => $meta['name'],
                'icon' => $meta['icon'] ?? '',
                'aspect' => $meta['aspect'] ?? '',
                'hint' => $meta['hint'] ?? '',
            ])
            ->values();

        $frameCatalog = $frameLibrary->catalogForUser($request->user());

        return response()->json([
            'templates' => $templates,
            'platforms' => $platforms,
            'frames' => $frameCatalog['frames'],
            'frame_categories' => $frameCatalog['categories'],
            'frame_library' => $frameCatalog['library'],
            'fonts' => config('thumbnail_templates.fonts', []),
            'defaults' => array_merge(
                config('thumbnail_templates.defaults', []),
                config('thumbnail_frames.defaults', [])
            ),
        ]);
    }

    public function show(Request $request, Project $project, ThumbnailRenderer $renderer): JsonResponse
    {
        $platform = $request->query('platform', config('thumbnail_templates.default_platform'));

        return response()->json([
            'platform' => $platform,
            'settings' => $renderer->resolveSettings($project, $platform),
            'all' => $renderer->resolveAllSettings($project),
        ]);
    }

    public function update(Request $request, Project $project, ThumbnailRenderer $renderer): JsonResponse
    {
        $data = $request->validate([
            'platform_preset' => ['required', 'string'],
            'template' => ['nullable', 'string'],
            'image_source' => ['nullable', 'in:slide,upload,solid'],
            'custom_image_path' => ['nullable', 'string'],
            'slide_index' => ['nullable', 'integer', 'min:0'],
            'slide_id' => ['nullable', 'integer', 'min:1'],
            'title_text' => ['nullable', 'string', 'max:500'],
            'subtitle_text' => ['nullable', 'string', 'max:500'],
            'title_color' => ['nullable', 'string', 'max:20'],
            'subtitle_color' => ['nullable', 'string', 'max:20'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'accent_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'background_color' => ['nullable', 'string', 'max:20'],
            'background_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'font_family' => ['nullable', 'string', 'max:40'],
            'title_size' => ['nullable', 'integer', 'min:18', 'max:120'],
            'subtitle_size' => ['nullable', 'integer', 'min:14', 'max:72'],
            'brightness' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'contrast' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'overlay_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'text_align' => ['nullable', 'in:left,center,right'],
            'vertical_align' => ['nullable', 'in:top,center,bottom'],
            'frame_slug' => ['nullable', 'string'],
            'frame_color' => ['nullable', 'string', 'max:20'],
            'frame_secondary_color' => ['nullable', 'string', 'max:20'],
            'frame_width' => ['nullable', 'integer', 'min:4', 'max:100'],
            'frame_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'frame_inset' => ['nullable', 'integer', 'min:0', 'max:80'],
        ]);

        $platform = $data['platform_preset'];
        unset($data['platform_preset']);

        $settings = $project->settings ?? [];
        $thumbnails = $settings['thumbnails'] ?? [];
        $thumbnails[$platform] = array_merge(
            $renderer->resolveSettings($project, $platform),
            array_filter($data, fn ($v) => $v !== null)
        );
        $settings['thumbnails'] = $thumbnails;

        $project->update(['settings' => $settings]);

        return response()->json([
            'platform' => $platform,
            'settings' => $renderer->resolveSettings($project->fresh(), $platform),
        ]);
    }

    public function uploadImage(Request $request, Project $project, ProjectStorageService $storage, ThumbnailRenderer $renderer): JsonResponse
    {
        $data = $request->validate([
            'platform_preset' => ['required', 'string'],
            'image' => ['required', 'image', 'max:20480'],
        ]);

        $platform = $data['platform_preset'];
        $storage->ensureStructure($project);

        $ext = $request->file('image')->getClientOriginalExtension() ?: 'jpg';
        $filename = 'upload_'.$platform.'_'.Str::random(8).'.'.strtolower($ext);
        $path = $storage->thumbPath($project, $filename);
        $request->file('image')->move(dirname($path), basename($path));

        $settings = $project->settings ?? [];
        $thumbnails = $settings['thumbnails'] ?? [];
        $thumbnails[$platform] = array_merge(
            $renderer->resolveSettings($project, $platform),
            [
                'image_source' => 'upload',
                'custom_image_path' => $path,
            ]
        );
        $settings['thumbnails'] = $thumbnails;
        $project->update(['settings' => $settings]);

        return response()->json([
            'platform' => $platform,
            'settings' => $renderer->resolveSettings($project->fresh(), $platform),
            'url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'thumbs',
                'filename' => $filename,
            ]).'?t='.time(),
        ]);
    }

    public function generate(Request $request, Project $project, ThumbnailRenderer $renderer, ProjectStorageService $storage): JsonResponse
    {
        $data = $request->validate([
            'platform_preset' => ['nullable', 'string'],
            'template' => ['nullable', 'string'],
            'image_source' => ['nullable', 'in:slide,upload,solid'],
            'custom_image_path' => ['nullable', 'string'],
            'slide_index' => ['nullable', 'integer', 'min:0'],
            'slide_id' => ['nullable', 'integer', 'min:1'],
            'preview' => ['nullable', 'boolean'],
            'all_platforms' => ['nullable', 'boolean'],
            'title_text' => ['nullable', 'string', 'max:500'],
            'subtitle_text' => ['nullable', 'string', 'max:500'],
            'title_color' => ['nullable', 'string', 'max:20'],
            'subtitle_color' => ['nullable', 'string', 'max:20'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'accent_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'background_color' => ['nullable', 'string', 'max:20'],
            'background_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'font_family' => ['nullable', 'string', 'max:40'],
            'title_size' => ['nullable', 'integer', 'min:18', 'max:120'],
            'subtitle_size' => ['nullable', 'integer', 'min:14', 'max:72'],
            'brightness' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'contrast' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'overlay_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'text_align' => ['nullable', 'in:left,center,right'],
            'vertical_align' => ['nullable', 'in:top,center,bottom'],
            'frame_slug' => ['nullable', 'string'],
            'frame_color' => ['nullable', 'string', 'max:20'],
            'frame_secondary_color' => ['nullable', 'string', 'max:20'],
            'frame_width' => ['nullable', 'integer', 'min:4', 'max:100'],
            'frame_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'frame_inset' => ['nullable', 'integer', 'min:0', 'max:80'],
        ]);

        $project->load('slides', 'user');
        $storage->ensureStructure($project);

        if ($data['all_platforms'] ?? false) {
            return $this->generateAll($project, $renderer, $storage);
        }

        $platform = $data['platform_preset'] ?? config('thumbnail_templates.default_platform');
        $renderOverrides = $this->renderOverridesFromRequest($data);
        $settings = $renderer->resolveSettings($project, $platform, $renderOverrides);
        $slide = $renderer->resolveSlideForSettings($project, $settings);

        if (! $slide && ($settings['image_source'] ?? 'slide') === 'slide') {
            return response()->json(['message' => 'Selecione um slide ou envie uma imagem externa.'], 422);
        }

        $preview = $data['preview'] ?? false;
        $filename = $renderer->outputFilename($platform, $preview);
        $outputPath = $storage->thumbPath($project, $filename);

        try {
            $renderer->renderForPlatform($project, $platform, $slide, $outputPath, $renderOverrides);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'platform' => $platform,
            'path' => $outputPath,
            'url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'thumbs',
                'filename' => $filename,
            ]).'?t='.time(),
            'settings' => $renderer->resolveSettings($project, $platform, $renderOverrides),
            'slide_id' => $slide?->id,
            'slide_index' => max(0, (int) ($settings['slide_index'] ?? 0)),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function renderOverridesFromRequest(array $data): array
    {
        $keys = [
            'template',
            'image_source',
            'custom_image_path',
            'slide_index',
            'slide_id',
            'title_text',
            'subtitle_text',
            'title_color',
            'subtitle_color',
            'accent_color',
            'accent_opacity',
            'background_color',
            'background_opacity',
            'font_family',
            'title_size',
            'subtitle_size',
            'brightness',
            'contrast',
            'overlay_opacity',
            'text_align',
            'vertical_align',
            'frame_slug',
            'frame_color',
            'frame_secondary_color',
            'frame_width',
            'frame_opacity',
            'frame_inset',
        ];

        return array_filter(
            array_intersect_key($data, array_flip($keys)),
            fn ($value) => $value !== null
        );
    }

    private function generateAll(Project $project, ThumbnailRenderer $renderer, ProjectStorageService $storage): JsonResponse
    {
        $project->load('slides', 'user');
        $generated = [];

        foreach (array_keys($renderer->platformPresets()) as $platform) {
            $settings = $renderer->resolveSettings($project, $platform);
            $slide = $renderer->resolveSlideForSettings($project, $settings);

            if (! $slide && ($settings['image_source'] ?? 'slide') === 'slide') {
                continue;
            }

            $filename = $renderer->outputFilename($platform, false);
            $outputPath = $storage->thumbPath($project, $filename);

            try {
                $renderer->renderForPlatform($project, $platform, $slide, $outputPath);
                $generated[] = [
                    'platform' => $platform,
                    'filename' => $filename,
                    'url' => route('api.projects.files', [
                        'project' => $project->id,
                        'type' => 'thumbs',
                        'filename' => $filename,
                    ]).'?t='.time(),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        if ($generated === []) {
            return response()->json(['message' => 'Nenhuma capa gerada — adicione slide ou imagem externa.'], 422);
        }

        return response()->json([
            'generated' => $generated,
            'count' => count($generated),
        ]);
    }
}
