<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\MediaLibrary\MediaImportService;
use App\Services\MediaLibrary\MixkitService;
use App\Services\MediaLibrary\OpenverseService;
use App\Services\MediaLibrary\PexelsService;
use App\Services\MediaLibrary\PixabayService;
use App\Services\MediaLibrary\UnsplashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaLibraryController extends Controller
{
    public function __construct(
        private OpenverseService $openverse,
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
            'source' => ['nullable', 'in:openverse,pexels,pixabay,unsplash,mixkit,all'],
            'type' => ['nullable', 'in:image,audio'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $source = $data['source'] ?? 'all';
        $type = $data['type'] ?? 'image';
        $query = $data['query'];
        $page = $data['page'] ?? 1;
        $results = [];

        if ($type === 'audio') {
            try {
                $results = array_merge($results, $this->mixkit->searchMusic($query));
            } catch (\Throwable) {
                // Mixkit catálogo local — ignora falhas
            }

            if (in_array($source, ['pixabay', 'all'], true) && config('criasys.media.pixabay_api_key')) {
                try {
                    $results = array_merge($results, $this->pixabay->searchAudio($query, $page));
                } catch (\Throwable) {
                    // opcional
                }
            }

            return response()->json([
                'results' => $results,
                'errors' => empty($results) ? ['Nenhum áudio encontrado para esta busca.'] : [],
            ]);
        }

        $imageSources = $this->resolveImageSources($source);
        $lastError = null;

        foreach ($imageSources as $src) {
            try {
                $chunk = match ($src) {
                    'openverse' => $this->openverse->searchImages($query, $page),
                    'pexels' => $this->pexels->searchPhotos($query, $page),
                    'pixabay' => $this->pixabay->searchImages($query, $page),
                    'unsplash' => $this->unsplash->searchPhotos($query, $page),
                    default => [],
                };
                $results = array_merge($results, $chunk);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        $results = collect($results)->unique(fn ($item) => ($item['source'] ?? '').'-'.($item['id'] ?? ''))->values()->all();

        $errors = [];
        if (empty($results)) {
            $errors[] = 'Nenhuma imagem encontrada para "'.$query.'". Tente outro termo.';
            if ($lastError && config('app.debug')) {
                $errors[] = $lastError;
            }
            if (config('app.env') !== 'production' && str_contains((string) $lastError, 'SSL')) {
                $errors[] = 'Adicione HTTP_VERIFY_SSL=false no .env e rode php artisan config:clear.';
            }
        }

        return response()->json([
            'results' => $results,
            'errors' => $errors,
        ]);
    }

    /**
     * @return list<string>
     */
    private function resolveImageSources(string $source): array
    {
        if ($source === 'openverse') {
            return ['openverse'];
        }

        if ($source === 'pexels') {
            return config('criasys.media.pexels_api_key') ? ['pexels'] : ['openverse'];
        }

        if ($source === 'pixabay') {
            return config('criasys.media.pixabay_api_key') ? ['pixabay'] : ['openverse'];
        }

        if ($source === 'unsplash') {
            return config('criasys.media.unsplash_access_key') ? ['unsplash'] : ['openverse'];
        }

        // all: Openverse sempre (gratuito) + APIs configuradas
        $sources = ['openverse'];

        if (config('criasys.media.pexels_api_key')) {
            $sources[] = 'pexels';
        }
        if (config('criasys.media.pixabay_api_key')) {
            $sources[] = 'pixabay';
        }
        if (config('criasys.media.unsplash_access_key')) {
            $sources[] = 'unsplash';
        }

        return $sources;
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
