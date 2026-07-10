<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ThumbnailFrameLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ThumbnailFrameController extends Controller
{
    public function library(Request $request, ThumbnailFrameLibraryService $library): JsonResponse
    {
        $user = $request->user();
        $data = $library->load($user);
        $catalog = $library->catalogForUser($user);
        $systemCategories = config('thumbnail_frames.categories', []);

        $hiddenFrameDetails = collect($data['hidden_frames'] ?? [])
            ->map(function ($slug) use ($systemCategories) {
                $meta = config('thumbnail_frames.frames.'.$slug);

                return $meta ? [
                    'slug' => $slug,
                    'name' => $meta['name'],
                    'category' => $meta['category'] ?? 'basico',
                    'category_label' => $systemCategories[$meta['category'] ?? 'basico'] ?? $meta['category'],
                    'is_custom' => false,
                ] : null;
            })
            ->filter()
            ->values();

        $hiddenCategoryDetails = collect($data['hidden_categories'] ?? [])
            ->map(fn ($slug) => [
                'slug' => $slug,
                'label' => $systemCategories[$slug] ?? ($data['custom_categories'][$slug]['label'] ?? $slug),
                'is_custom' => isset($data['custom_categories'][$slug]),
            ])
            ->values();

        return response()->json([
            'catalog' => $catalog,
            'hidden_frames' => $hiddenFrameDetails,
            'hidden_categories' => $hiddenCategoryDetails,
            'custom_categories' => $data['custom_categories'] ?? [],
        ]);
    }

    public function storeCategory(Request $request, ThumbnailFrameLibraryService $library): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
        ]);

        $slug = $library->createCategory($request->user(), $data['label']);

        return response()->json([
            'slug' => $slug,
            'catalog' => $library->catalogForUser($request->user()),
        ], 201);
    }

    public function store(Request $request, ThumbnailFrameLibraryService $library): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:20480'],
            'style' => ['nullable', 'string', 'max:60'],
            'default_color' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();

        if ($request->hasFile('image')) {
            $result = $library->createOverlayFrame(
                $user,
                $request->file('image'),
                $data['name'],
                $data['category'] ?? null,
                $data['description'] ?? null
            );
        } else {
            $result = $library->createProceduralFrame($user, $data);
        }

        return response()->json([
            'slug' => $result['slug'],
            'frame' => $result['meta'],
            'catalog' => $library->catalogForUser($user),
        ], 201);
    }

    public function destroy(Request $request, string $slug, ThumbnailFrameLibraryService $library): JsonResponse
    {
        if ($slug === 'none') {
            return response()->json(['message' => 'Não é possível remover esta moldura.'], 422);
        }

        $library->hideFrame($request->user(), $slug);

        return response()->json([
            'message' => 'Moldura removida.',
            'catalog' => $library->catalogForUser($request->user()),
        ]);
    }

    public function restore(Request $request, string $slug, ThumbnailFrameLibraryService $library): JsonResponse
    {
        $library->restoreFrame($request->user(), $slug);

        return response()->json([
            'message' => 'Moldura restaurada.',
            'catalog' => $library->catalogForUser($request->user()),
        ]);
    }

    public function destroyCategory(Request $request, string $slug, ThumbnailFrameLibraryService $library): JsonResponse
    {
        try {
            $library->hideCategory($request->user(), $slug);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Conjunto removido.',
            'catalog' => $library->catalogForUser($request->user()),
        ]);
    }

    public function restoreCategory(Request $request, string $slug, ThumbnailFrameLibraryService $library): JsonResponse
    {
        $library->restoreCategory($request->user(), $slug);

        return response()->json([
            'message' => 'Conjunto restaurado.',
            'catalog' => $library->catalogForUser($request->user()),
        ]);
    }

    public function serveFile(Request $request, string $filename, ThumbnailFrameLibraryService $library): BinaryFileResponse|JsonResponse
    {
        $path = $library->frameImagePath($request->user(), $filename);
        if (! $path) {
            return response()->json(['message' => 'Arquivo não encontrado.'], 404);
        }

        return response()->file($path, [
            'Content-Type' => File::mimeType($path) ?: 'image/png',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
