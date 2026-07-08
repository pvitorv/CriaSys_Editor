<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\MediaLibrary\MediaImportService;
use App\Services\MediaLibrary\MixkitService;
use App\Services\MediaLibrary\PexelsService;
use App\Services\MediaLibrary\PixabayService;
use App\Services\MediaLibrary\UnsplashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaLibraryController extends Controller
{
    public function __construct(
        private PexelsService $pexels,
        private PixabayService $pixabay,
        private UnsplashService $unsplash,
        private MixkitService $mixkit,
        private MediaImportService $importer,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2'],
            'source' => ['nullable', 'in:pexels,pixabay,unsplash,mixkit,all'],
            'type' => ['nullable', 'in:image,audio'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $source = $data['source'] ?? 'all';
        $type = $data['type'] ?? 'image';
        $query = $data['query'];
        $page = $data['page'] ?? 1;
        $results = [];
        $errors = [];

        if ($type === 'audio') {
            try {
                $results = array_merge($results, $this->mixkit->searchMusic($query));
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }

            if (in_array($source, ['pixabay', 'all'], true)) {
                try {
                    $results = array_merge($results, $this->pixabay->searchAudio($query, $page));
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }

            return response()->json(['results' => $results, 'errors' => $errors]);
        }

        $sources = $source === 'all' ? ['pexels', 'pixabay', 'unsplash'] : [$source];

        foreach ($sources as $src) {
            try {
                $results = array_merge($results, match ($src) {
                    'pexels' => $this->pexels->searchPhotos($query, $page),
                    'pixabay' => $this->pixabay->searchImages($query, $page),
                    'unsplash' => $this->unsplash->searchPhotos($query, $page),
                    default => [],
                });
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        return response()->json(['results' => $results, 'errors' => $errors]);
    }

    public function import(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'item' => ['required', 'array'],
            'item.download_url' => ['required', 'url'],
            'item.type' => ['nullable', 'in:image,audio'],
            'target' => ['nullable', 'in:slide,audio_track'],
        ]);

        $item = $data['item'];
        $type = $item['type'] ?? 'image';

        try {
            $asset = $type === 'audio'
                ? $this->importer->importAudio($project, $item)
                : $this->importer->importImage($project, $item);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (($data['target'] ?? '') === 'audio_track' || $type === 'audio') {
            $track = $project->audioTracks()->updateOrCreate(
                ['type' => 'music'],
                ['asset_id' => $asset->id, 'file_path' => $asset->file_path, 'volume' => 0.35, 'ducking_enabled' => true]
            );

            return response()->json(['asset' => $asset, 'audio_track' => $track], 201);
        }

        return response()->json($asset, 201);
    }
}
