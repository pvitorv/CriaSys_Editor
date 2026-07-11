<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Slide;
use App\Models\SoundEffect;
use App\Services\Export\ProjectPublishAutoSyncService;
use App\Services\MediaLibrary\FreesoundService;
use App\Services\MediaLibrary\MediaImportService;
use App\Services\MediaLibrary\MediaSearchQueryTranslator;
use App\Services\MediaLibrary\MediaUrlResolverService;
use App\Services\MediaLibrary\MixkitMusicService;
use App\Services\MediaLibrary\MixkitSfxService;
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
        private MixkitMusicService $mixkitMusic,
        private MixkitSfxService $mixkitSfx,
        private MixkitVideoService $mixkitVideos,
        private FreesoundService $freesound,
        private MediaImportService $importer,
        private ProjectPublishAutoSyncService $publishSync,
        private MediaSearchQueryTranslator $queryTranslator,
        private MediaUrlResolverService $urlResolver,
    ) {}

    public function providers(): JsonResponse
    {
        return response()->json([
            'visual' => [
                ['id' => 'openverse', 'label' => 'Openverse', 'free' => true, 'configured' => true],
                ['id' => 'pexels', 'label' => 'Pexels', 'free' => true, 'configured' => (bool) config('criasys.media.pexels_api_key')],
                ['id' => 'pixabay', 'label' => 'Pixabay', 'free' => true, 'configured' => (bool) config('criasys.media.pixabay_api_key')],
                ['id' => 'unsplash', 'label' => 'Unsplash', 'free' => true, 'configured' => (bool) config('criasys.media.unsplash_access_key')],
            ],
            'music' => [
                ['id' => 'mixkit', 'label' => 'Mixkit Music', 'free' => true, 'configured' => true, 'attribution' => false],
                ['id' => 'freesound', 'label' => 'Freesound', 'free' => true, 'configured' => (bool) config('criasys.media.freesound_api_key'), 'attribution' => true],
                ['id' => 'pixabay', 'label' => 'Pixabay', 'free' => true, 'configured' => (bool) config('criasys.media.pixabay_api_key'), 'attribution' => false],
            ],
            'sfx' => [
                ['id' => 'mixkit', 'label' => 'Mixkit SFX', 'free' => true, 'configured' => true, 'attribution' => false],
                ['id' => 'freesound', 'label' => 'Freesound', 'free' => true, 'configured' => (bool) config('criasys.media.freesound_api_key'), 'attribution' => true],
            ],
        ]);
    }

    public function suggestQuery(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2'],
        ]);

        return response()->json($this->queryTranslator->meta($data['query']));
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2'],
            'source' => ['nullable', 'in:openverse,pexels,pixabay,unsplash,mixkit,freesound,all'],
            'type' => ['nullable', 'in:image,audio,video,sfx'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $source = $data['source'] ?? 'all';
        $type = $data['type'] ?? 'image';
        $query = $data['query'];
        $page = $data['page'] ?? 1;
        $searchMeta = $this->queryTranslator->meta($query);
        $terms = $searchMeta['terms'];

        if ($type === 'audio') {
            return response()->json($this->withPaginationMeta(
                $this->searchAudio($query, $terms, $source, $page),
                $page,
                $searchMeta,
            ));
        }

        if ($type === 'sfx') {
            return response()->json($this->withPaginationMeta(
                $this->searchSfx($query, $terms, $source, $page),
                $page,
                $searchMeta,
            ));
        }

        if ($type === 'video') {
            $videoTerms = $this->queryTranslator->termsForVideo($query);
            $searchMeta['terms'] = $videoTerms;
            $searchMeta['primary'] = $videoTerms[0] ?? $searchMeta['primary'];
            if ($videoTerms !== [] && ($searchMeta['hint'] ?? null)) {
                $searchMeta['hint'] = 'Objeto: '.$videoTerms[0];
            } elseif ($videoTerms !== [] && $this->queryTranslator->wasTranslated($query)) {
                $searchMeta['hint'] = 'Buscando como: '.$videoTerms[0];
            }

            return response()->json($this->withPaginationMeta(
                $this->searchVideo($query, $videoTerms, $source, $page),
                $page,
                $searchMeta,
            ));
        }

        return response()->json($this->withPaginationMeta(
            $this->searchImages($query, $terms, $source, $page),
            $page,
            $searchMeta,
        ));
    }

    /**
     * @param  array{results: list<array<string, mixed>>, errors: list<string>}  $payload
     * @return array<string, mixed>
     */
    private function withPaginationMeta(array $payload, int $page, array $searchMeta): array
    {
        $count = count($payload['results'] ?? []);
        $minPageSize = 8;

        return array_merge($payload, [
            'search' => $searchMeta,
            'page' => $page,
            'has_more' => $count >= $minPageSize,
        ]);
    }

    /**
     * @param  list<string>  $terms
     * @return array{results: list<array<string, mixed>>, errors: list<string>}
     */
    private function searchImages(string $query, array $terms, string $source, int $page): array
    {
        $imageSources = $this->orderImageSources($this->resolveImageSources($source));
        $results = [];
        $lastError = null;
        $terms = array_slice($terms, 0, 2);

        foreach ($terms as $termIndex => $term) {
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

            $results = $this->uniqueResults($results);

            if (count($results) >= 20) {
                break;
            }

            if ($termIndex === 0 && count($results) >= 8) {
                break;
            }
        }

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
        $term = $terms[0] ?? $this->queryTranslator->primaryTerm($query);
        $results = [];
        $lastError = null;

        if ($term === '') {
            return [
                'results' => [],
                'errors' => ['Digite um objeto ou cena para buscar (ex.: gato, bola, praia).'],
            ];
        }

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

        $results = $this->uniqueResults($results);

        $errors = $this->buildSearchErrors($results, $query, $lastError, 'vídeo', 'vídeos');
        if (empty($results) && ! config('criasys.media.pexels_api_key') && ! config('criasys.media.pixabay_api_key')) {
            $errors[] = 'Mixkit (grátis) não retornou resultados para "'.$term.'" — tente um objeto concreto (ex.: cat, beach, football).';
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
        $lastError = null;
        $terms = array_slice($terms, 0, 2);
        $sources = $this->resolveMusicSources($source);

        foreach ($terms as $term) {
            foreach ($sources as $src) {
                try {
                    $chunk = match ($src) {
                        'mixkit' => $this->mixkitMusic->searchMusic($term, $page),
                        'freesound' => $this->freesound->searchMusic($term, $page),
                        'pixabay' => $this->pixabay->searchAudio($term, $page),
                        default => [],
                    };
                    $results = array_merge($results, $chunk);
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                }
            }

            $results = $this->uniqueResults($results);

            if (count($results) >= 20) {
                break;
            }
        }

        $errors = $this->buildSearchErrors($results, $query, $lastError, 'música', 'músicas');
        if (empty($results)) {
            $errors[] = 'Mixkit é gratuito sem chave. Para mais resultados, configure FREESOUND_API_KEY ou PIXABAY_API_KEY no .env.';
        }

        return ['results' => $results, 'errors' => $errors];
    }

    /**
     * @param  list<string>  $terms
     * @return array{results: list<array<string, mixed>>, errors: list<string>}
     */
    private function searchSfx(string $query, array $terms, string $source, int $page): array
    {
        $results = [];
        $lastError = null;
        $terms = array_slice($terms, 0, 2);
        $sources = $this->resolveSfxSources($source);

        foreach ($terms as $term) {
            foreach ($sources as $src) {
                try {
                    $chunk = match ($src) {
                        'mixkit' => $this->mixkitSfx->searchSfx($term, $page),
                        'freesound' => $this->freesound->searchSfx($term, $page),
                        default => [],
                    };
                    $results = array_merge($results, $chunk);
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                }
            }

            $results = $this->uniqueResults($results);

            if (count($results) >= 20) {
                break;
            }
        }

        $errors = $this->buildSearchErrors($results, $query, $lastError, 'efeito sonoro', 'efeitos');
        if (empty($results)) {
            $errors[] = 'Mixkit SFX funciona sem chave. Configure FREESOUND_API_KEY para milhares de efeitos extras (com crédito ao autor).';
        }

        return ['results' => $results, 'errors' => $errors];
    }

    /**
     * @return list<string>
     */
    private function resolveMusicSources(string $source): array
    {
        if ($source === 'mixkit') {
            return ['mixkit'];
        }
        if ($source === 'freesound') {
            return config('criasys.media.freesound_api_key') ? ['freesound'] : ['mixkit'];
        }
        if ($source === 'pixabay') {
            return array_filter([
                'mixkit',
                config('criasys.media.pixabay_api_key') ? 'pixabay' : null,
            ]);
        }

        $sources = ['mixkit'];
        if (config('criasys.media.freesound_api_key')) {
            $sources[] = 'freesound';
        }
        if (config('criasys.media.pixabay_api_key')) {
            $sources[] = 'pixabay';
        }

        return $sources;
    }

    /**
     * @return list<string>
     */
    private function resolveSfxSources(string $source): array
    {
        if ($source === 'mixkit') {
            return ['mixkit'];
        }
        if ($source === 'freesound') {
            return array_filter([
                'mixkit',
                config('criasys.media.freesound_api_key') ? 'freesound' : null,
            ]);
        }

        $sources = ['mixkit'];
        if (config('criasys.media.freesound_api_key')) {
            $sources[] = 'freesound';
        }

        return $sources;
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return list<array<string, mixed>>
     */
    private function uniqueResults(array $results): array
    {
        return collect($results)
            ->unique(fn ($item) => $this->resultKey($item))
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $item */
    private function resultKey(array $item): string
    {
        $source = (string) ($item['source'] ?? '');
        $id = (string) ($item['id'] ?? '');
        if ($id !== '') {
            return $source.'-'.$id;
        }

        $url = (string) ($item['download_url'] ?? $item['preview_url'] ?? '');
        if ($url !== '' && preg_match('#/videos/(\d+)/#', $url, $match)) {
            return $source.'-'.$match[1];
        }

        return $source.'-'.md5($url);
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
    private function orderImageSources(array $sources): array
    {
        $priority = ['pexels', 'unsplash', 'pixabay', 'openverse'];

        return collect($priority)
            ->filter(fn (string $src) => in_array($src, $sources, true))
            ->values()
            ->all();
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

        $sources = [];

        if (config('criasys.media.pexels_api_key')) {
            $sources[] = 'pexels';
        }
        if (config('criasys.media.unsplash_access_key')) {
            $sources[] = 'unsplash';
        }
        if (config('criasys.media.pixabay_api_key')) {
            $sources[] = 'pixabay';
        }

        $sources[] = 'openverse';

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

    public function resolveUrl(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
            'type' => ['nullable', 'in:image,audio,video,sfx'],
        ]);

        try {
            $item = $this->urlResolver->resolve($data['url'], $data['type'] ?? null);
            if (empty($item['download_url'])) {
                return response()->json(['message' => 'Não foi possível obter o arquivo para download.'], 422);
            }

            return response()->json(['item' => $item]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function importUrl(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
            'type' => ['nullable', 'in:image,audio,video,sfx'],
            'target' => ['nullable', 'in:slide,audio_track,sound_effect'],
            'slide_id' => ['nullable', 'integer'],
            'track_slot' => ['nullable', 'integer', 'min:0', 'max:2'],
            'start_at' => ['nullable', 'numeric', 'min:0'],
            'place_at' => ['nullable', 'boolean'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $item = $this->urlResolver->resolve($data['url'], $data['type'] ?? null);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (empty($item['download_url'])) {
            return response()->json(['message' => 'Não foi possível obter o arquivo para download.'], 422);
        }

        $request->merge(['item' => $item]);

        return $this->import($request, $project);
    }

    public function import(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'item' => ['required', 'array'],
            'item.download_url' => ['required', 'url'],
            'item.type' => ['nullable', 'in:image,audio,video,sfx'],
            'target' => ['nullable', 'in:slide,audio_track,sound_effect'],
            'slide_id' => ['nullable', 'integer'],
            'track_slot' => ['nullable', 'integer', 'min:0', 'max:2'],
            'start_at' => ['nullable', 'numeric', 'min:0'],
            'place_at' => ['nullable', 'boolean'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        $item = $data['item'];
        $type = $item['type'] ?? 'image';
        $target = $data['target'] ?? null;

        if ($type === 'sfx') {
            $target = 'sound_effect';
        } elseif ($type === 'audio' && ! $target) {
            $target = 'audio_track';
        }

        try {
            $asset = match ($type) {
                'audio' => $this->importer->importAudio($project, $item),
                'sfx' => $this->importer->importSfx($project, $item),
                'video' => $this->importer->importVideo($project, $item),
                default => $this->importer->importImage($project, $item),
            };
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $slidePayload = null;
        if ($target !== 'audio_track' && $target !== 'sound_effect' && ! in_array($type, ['audio', 'sfx'], true) && ! empty($data['slide_id'])) {
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

        if ($target === 'sound_effect') {
            $duration = ! empty($item['duration_seconds']) ? (float) $item['duration_seconds'] : null;
            $effect = $project->soundEffects()->create([
                'label' => $data['label'] ?? $item['title'] ?? 'Efeito',
                'asset_id' => $asset->id,
                'file_path' => $asset->file_path,
                'start_at' => (float) ($data['start_at'] ?? 0),
                'volume' => 0.85,
                'source_duration' => $duration,
                'clip_duration' => $duration,
            ]);

            return response()->json(array_merge($payload, ['sound_effect' => $effect->fresh(['asset'])]), 201);
        }

        if ($target === 'audio_track') {
            $slot = (int) ($data['track_slot'] ?? 0);
            $duration = ! empty($item['duration_seconds']) ? (float) $item['duration_seconds'] : null;
            $label = $item['title'] ?? 'Trilha';
            $placeAt = (bool) ($data['place_at'] ?? false);
            $startAt = isset($data['start_at']) ? (float) $data['start_at'] : null;

            $existing = $project->audioTracks()
                ->where('type', 'music')
                ->where('track_slot', $slot)
                ->first();

            if ($existing?->file_path && ! $placeAt) {
                $clips = $existing->appendClip([
                    'asset_id' => $asset->id,
                    'file_path' => $asset->file_path,
                    'source_duration' => $duration,
                    'label' => $label,
                ]);
                $existing->update(['clips' => $clips]);
                $track = $existing->fresh();
            } elseif ($existing?->file_path && $placeAt) {
                $clips = $existing->clips ?? [];
                $clips[] = [
                    'asset_id' => $asset->id,
                    'file_path' => $asset->file_path,
                    'source_duration' => $duration,
                    'start_at' => $startAt ?? $existing->coverageEndSec(),
                    'label' => $label,
                ];
                $existing->update(['clips' => $clips]);
                $track = $existing->fresh();
            } else {
                $track = $project->audioTracks()->updateOrCreate(
                    ['type' => 'music', 'track_slot' => $slot],
                    [
                        'asset_id' => $asset->id,
                        'file_path' => $asset->file_path,
                        'source_duration' => $duration,
                        'volume' => 0.35,
                        'start_at' => $startAt ?? 0,
                        'ducking_enabled' => $slot === 0,
                        'loop_enabled' => true,
                    ]
                );
            }

            return response()->json(array_merge($payload, ['audio_track' => $track->fresh()]), 201);
        }

        return response()->json($payload, 201);
    }
}
