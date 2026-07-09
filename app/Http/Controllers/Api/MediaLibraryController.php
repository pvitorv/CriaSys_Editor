<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Slide;
use App\Services\Export\ProjectPublishAutoSyncService;
use App\Services\MediaLibrary\MediaImportService;
use App\Services\MediaLibrary\MediaSearchQueryTranslator;
use App\Services\MediaLibrary\MixkitService;
use App\Services\MediaLibrary\MixkitVideoService;
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
        private MixkitVideoService $mixkitVideos,
        private MediaImportService $importer,
        private ProjectPublishAutoSyncService $publishSync,
        private MediaSearchQueryTranslator $queryTranslator,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2'],
            'source' => ['nullable', 'in:openverse,pexels,pixabay,unsplash,mixkit,all'],
            'type' => ['nullable', 'in:image,audio,video'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $source = $data['source'] ?? 'all';
        $type = $data['type'] ?? 'image';
        $query = $data['query'];
        $page = $data['page'] ?? 1;
        $searchMeta = $this->queryTranslator->meta($query);
        $terms = $searchMeta['terms'];

        if ($type === 'audio') {
            return response()->json(array_merge(
                $this->searchAudio($query, $terms, $source, $page),
                ['search' => $searchMeta]
            ));
        }

        if ($type === 'video') {
            return response()->json(array_merge(
                $this->searchVideo($query, $terms, $source, $page),
                ['search' => $searchMeta]
            ));
        }

        return response()->json(array_merge(
            $this->searchImages($query, $terms, $source, $page),
            ['search' => $searchMeta]
        ));
    }

    /**
     * @param  list<string>  $terms
     * @return array{results: list<array<string, mixed>>, errors: list<string>}
     */
    private function searchImages(string $query, array $terms, string $source, int $page): array
    {
        $imageSources = $this->resolveImageSources($source);
        $results = [];
        $lastError = null;

        foreach ($terms as $term) {
            foreach ($imageSources as $src) {
                try {
                    $chunk = match ($src) {
                        'openverse' => $this->openverse->searchImages($term, $page),
                        'pexels' => $this->pexels->searchPhotos($term, $page),
                        'pixabay' => $this->pixabay->searchImages($term, $page),
                        'unsplash' => $this->unsplash->searchPhotos($term, $page),
                        default => [],
                    };
                    $results = array_merge($results, $chunk);
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                }
            }

            if (count($results) >= 24) {
                break;
            }
        }

        $results = $this->uniqueResults($results);

        return [
            'results' => $results,
            'errors' => $this->buildSearchErrors($results, $query, $lastError, 'imagem', 'imagens'),
        ];
    }

    /**
     * @param  list<string>  $terms
     * @return array{results: list<array<string, mixed>>, errors: list<string>}
     */
    private function searchVideo(string $query, array $terms, string $source, int $page): array
    {
        $videoSources = $this->resolveVideoSources($source);
        $results = [];
        $lastError = null;

        foreach ($terms as $term) {
            if (in_array('mixkit', $videoSources, true)) {
                try {
                    $results = array_merge($results, $this->mixkitVideos->searchVideos($term, $page));
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                }
            }

            foreach ($videoSources as $src) {
                if ($src === 'mixkit') {
                    continue;
                }
                try {
                    $chunk = match ($src) {
                        'pexels' => $this->pexels->searchVideos($term, $page),
                        'pixabay' => $this->pixabay->searchVideos($term, $page),
                        default => [],
                    };
                    $results = array_merge($results, $chunk);
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                }
            }

            if (count($results) >= 24) {
                break;
            }
        }

        $results = $this->uniqueResults($results);

        $errors = $this->buildSearchErrors($results, $query, $lastError, 'vídeo', 'vídeos');
        if (empty($results) && ! config('criasys.media.pexels_api_key') && ! config('criasys.media.pixabay_api_key')) {
            $errors[] = 'Mixkit (grátis) não retornou resultados — tente outra palavra em português (ex.: futebol, praia, cidade).';
        }

        return ['results' => $results, 'errors' => $errors];
    }

    /**
     * @param  list<string>  $terms
     * @return array{results: list<array<string, mixed>>, errors: list<string>}
     */
    private function searchAudio(string $query, array $terms, string $source, int $page): array
    {
        $results = [];

        foreach ($terms as $term) {
            try {
                $results = array_merge($results, $this->mixkit->searchMusic($term));
            } catch (\Throwable) {
                // catálogo local
            }

            if (in_array($source, ['pixabay', 'all'], true) && config('criasys.media.pixabay_api_key')) {
                try {
                    $results = array_merge($results, $this->pixabay->searchAudio($term, $page));
                } catch (\Throwable) {
                    // opcional
                }
            }

            if (count($results) >= 20) {
                break;
            }
        }

        $results = $this->uniqueResults($results);

        return [
            'results' => $results,
            'errors' => empty($results) ? ['Nenhum áudio encontrado para "'.$query.'".'] : [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return list<array<string, mixed>>
     */
    private function uniqueResults(array $results): array
    {
        return collect($results)
            ->unique(fn ($item) => ($item['source'] ?? '').'-'.($item['id'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function buildSearchErrors(array $results, string $query, ?string $lastError, string $label, string $labelPlural): array
    {
        if (! empty($results)) {
            return [];
        }

        $errors = ['Nenhuma '.$label.' encontrada para "'.$query.'". Tente sinônimo ou termo mais genérico.'];
        if ($lastError && config('app.debug')) {
            $errors[] = $lastError;
        }
        if (config('app.env') !== 'production' && str_contains((string) $lastError, 'SSL')) {
            $errors[] = 'Adicione HTTP_VERIFY_SSL=false no .env e rode php artisan config:clear.';
        }

        return $errors;
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

    /**
     * @return list<string>
     */
    private function resolveVideoSources(string $source): array
    {
        if ($source === 'mixkit') {
            return ['mixkit'];
        }

        if ($source === 'pexels') {
            return array_filter([
                'mixkit',
                config('criasys.media.pexels_api_key') ? 'pexels' : null,
            ]);
        }

        if ($source === 'pixabay') {
            return array_filter([
                'mixkit',
                config('criasys.media.pixabay_api_key') ? 'pixabay' : null,
            ]);
        }

        $sources = ['mixkit'];
        if (config('criasys.media.pexels_api_key')) {
            $sources[] = 'pexels';
        }
        if (config('criasys.media.pixabay_api_key')) {
            $sources[] = 'pixabay';
        }

        return $sources;
    }

    public function import(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'item' => ['required', 'array'],
            'item.download_url' => ['required', 'url'],
            'item.type' => ['nullable', 'in:image,audio,video'],
            'target' => ['nullable', 'in:slide,audio_track'],
            'slide_id' => ['nullable', 'integer'],
        ]);

        $item = $data['item'];
        $type = $item['type'] ?? 'image';

        try {
            $asset = match ($type) {
                'audio' => $this->importer->importAudio($project, $item),
                'video' => $this->importer->importVideo($project, $item),
                default => $this->importer->importImage($project, $item),
            };
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $slidePayload = null;
        if (($data['target'] ?? '') !== 'audio_track' && $type !== 'audio' && ! empty($data['slide_id'])) {
            $slide = Slide::where('id', $data['slide_id'])
                ->where('project_id', $project->id)
                ->first();

            if ($slide) {
                $updates = [];
                if ($type === 'video') {
                    $updates['video_path'] = $asset->file_path;
                    if (! empty($item['duration_seconds'])) {
                        $updates['duration_seconds'] = min(max((float) $item['duration_seconds'], 1), 60);
                    }
                } else {
                    $updates['image_path'] = $asset->file_path;
                }
                $slide->update($updates);
                $slidePayload = $slide->fresh();
            }
        }

        $publish = $this->publishSync->sync($project);

        $payload = [
            'asset' => $asset,
            'slide' => $slidePayload,
            'publish' => $publish,
        ];

        if (($data['target'] ?? '') === 'audio_track' || $type === 'audio') {
            $track = $project->audioTracks()->updateOrCreate(
                ['type' => 'music'],
                ['asset_id' => $asset->id, 'file_path' => $asset->file_path, 'volume' => 0.35, 'ducking_enabled' => true]
            );

            return response()->json(array_merge($payload, ['audio_track' => $track]), 201);
        }

        return response()->json($payload, 201);
    }
}
