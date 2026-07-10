<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExportPreset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use App\Services\Render\ThumbnailRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThumbnailController extends Controller
{
    public function templates(): JsonResponse
    {
        $templates = collect(config('thumbnail_templates.templates', []))
            ->map(fn (array $meta, string $slug) => [
                'slug' => $slug,
                'name' => $meta['name'],
                'description' => $meta['description'],
            ])
            ->values();

        return response()->json([
            'templates' => $templates,
            'fonts' => config('thumbnail_templates.fonts', []),
            'defaults' => config('thumbnail_templates.defaults', []),
        ]);
    }

    public function show(Project $project, ThumbnailRenderer $renderer): JsonResponse
    {
        return response()->json($renderer->resolveSettings($project));
    }

    public function update(Request $request, Project $project, ThumbnailRenderer $renderer): JsonResponse
    {
        $data = $request->validate([
            'template' => ['nullable', 'string'],
            'slide_index' => ['nullable', 'integer', 'min:0'],
            'title_text' => ['nullable', 'string', 'max:500'],
            'subtitle_text' => ['nullable', 'string', 'max:500'],
            'title_color' => ['nullable', 'string', 'max:20'],
            'subtitle_color' => ['nullable', 'string', 'max:20'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'background_color' => ['nullable', 'string', 'max:20'],
            'font_family' => ['nullable', 'string', 'max:40'],
            'title_size' => ['nullable', 'integer', 'min:18', 'max:120'],
            'subtitle_size' => ['nullable', 'integer', 'min:14', 'max:72'],
            'brightness' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'contrast' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'overlay_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'text_align' => ['nullable', 'in:left,center,right'],
            'vertical_align' => ['nullable', 'in:top,center,bottom'],
        ]);

        $settings = $project->settings ?? [];
        $settings['thumbnail'] = array_merge(
            $renderer->resolveSettings($project),
            array_filter($data, fn ($v) => $v !== null)
        );

        $project->update(['settings' => $settings]);

        return response()->json($renderer->resolveSettings($project->fresh()));
    }

    public function generate(Request $request, Project $project, ThumbnailRenderer $renderer, ProjectStorageService $storage): JsonResponse
    {
        $data = $request->validate([
            'slide_index' => ['nullable', 'integer', 'min:0'],
            'preview' => ['nullable', 'boolean'],
        ]);

        $project->load('slides');
        $slideIndex = $data['slide_index'] ?? $renderer->resolveSettings($project)['slide_index'] ?? 0;
        $slide = $project->slides[$slideIndex] ?? $project->slides->first();

        if (! $slide) {
            return response()->json(['message' => 'Projeto sem slides para gerar thumbnail.'], 422);
        }

        $preset = ExportPreset::where('slug', 'thumbnail')->first()
            ?? ExportPreset::where('slug', 'youtube_landscape')->firstOrFail();

        $storage->ensureStructure($project);
        $filename = ($data['preview'] ?? false) ? 'thumbnail_preview.jpg' : 'thumbnail.jpg';
        $outputPath = $storage->thumbPath($project, $filename);

        try {
            $renderer->render($project, $slide, $preset, $outputPath);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'path' => $outputPath,
            'url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'thumbs',
                'filename' => basename($outputPath),
            ]).'?t='.time(),
            'settings' => $renderer->resolveSettings($project),
        ]);
    }
}
