<?php

namespace App\Services\MediaLibrary;

use App\Enums\LicenseType;
use App\Support\ExternalHttp;
use Illuminate\Support\Str;

class MediaUrlResolverService
{
    /**
     * Resolve URL de página ou arquivo direto em item normalizado para importação.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $url, ?string $typeHint = null): array
    {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Informe uma URL válida.');
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if ($this->isDirectMediaUrl($url)) {
            return $this->resolveDirectFile($url, $typeHint);
        }

        if (str_contains($host, 'pixabay.com')) {
            return $this->resolvePixabayPage($url, $typeHint);
        }

        if (str_contains($host, 'pexels.com')) {
            return $this->resolvePexelsPage($url, $typeHint);
        }

        if (str_contains($host, 'unsplash.com')) {
            return $this->resolveUnsplashPage($url, $typeHint);
        }

        if (str_contains($host, 'mixkit.co')) {
            return $this->resolveMixkitPage($url, $typeHint);
        }

        throw new \RuntimeException(
            'Não reconhecemos esta URL. Use Pixabay, Pexels, Unsplash, Mixkit ou um link direto para o arquivo (.jpg, .mp4, .mp3…).'
        );
    }

    private function isDirectMediaUrl(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return (bool) preg_match('/\.(jpe?g|png|webp|gif|svg|mp4|webm|mov|mp3|wav|ogg|m4a|aac)(\?|$)/i', $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDirectFile(string $url, ?string $typeHint): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $type = $typeHint ?: match (true) {
            in_array($ext, ['mp4', 'webm', 'mov'], true) => 'video',
            in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'aac'], true) => 'audio',
            default => 'image',
        };

        if ($typeHint === 'sfx') {
            $type = 'sfx';
        }

        $title = urldecode(basename($path)) ?: 'Arquivo externo';

        return [
            'id' => 'direct_'.substr(hash('sha256', $url), 0, 12),
            'source' => 'external',
            'type' => $type,
            'title' => $title,
            'preview_url' => in_array($type, ['image', 'video'], true) ? $url : null,
            'download_url' => $url,
            'original_url' => $url,
            'license_type' => LicenseType::CustomLicensed->value,
            'requires_attribution' => true,
            'attribution_text' => "Fonte externa — {$url}",
            'author' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePixabayPage(string $url, ?string $typeHint): array
    {
        if (! preg_match('/-(\d+)\/?(?:\?|$)/', $url, $m)) {
            throw new \RuntimeException('Não foi possível extrair o ID do Pixabay nesta URL.');
        }

        $id = (int) $m[1];
        $apiKey = config('criasys.media.pixabay_api_key');
        if (! $apiKey) {
            throw new \RuntimeException('PIXABAY_API_KEY não configurada — cadastre no .env para importar por link.');
        }

        $isVideo = str_contains($url, '/videos/') || $typeHint === 'video';
        $endpoint = $isVideo ? 'https://pixabay.com/api/videos/' : 'https://pixabay.com/api/';

        $response = ExternalHttp::client()->get($endpoint, [
            'key' => $apiKey,
            'id' => $id,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Pixabay não retornou dados para este link.');
        }

        $hit = collect($response->json('hits', []))->first();
        if (! $hit) {
            throw new \RuntimeException('Item não encontrado no Pixabay — verifique se o link está correto.');
        }

        if ($isVideo) {
            $videos = $hit['videos'] ?? [];
            $medium = $videos['medium'] ?? $videos['small'] ?? $videos['large'] ?? [];
            $attribution = MediaAttribution::forPixabay($hit, 'video');

            return [
                'id' => $hit['id'],
                'source' => 'pixabay',
                'type' => 'video',
                'title' => $hit['tags'] ?? 'Vídeo Pixabay',
                'preview_url' => ! empty($hit['picture_id'])
                    ? "https://i.vimeocdn.com/video/{$hit['picture_id']}_640x360.jpg"
                    : null,
                'download_url' => $medium['url'] ?? null,
                'duration_seconds' => $hit['duration'] ?? null,
                'author' => $hit['user'] ?? 'Desconhecido',
                'original_url' => $hit['pageURL'] ?? $url,
                'license_type' => LicenseType::Pixabay->value,
                'requires_attribution' => $attribution['requires_attribution'],
                'attribution_text' => $attribution['attribution_text'],
            ];
        }

        $attribution = MediaAttribution::forPixabay($hit, 'image');

        return [
            'id' => $hit['id'],
            'source' => 'pixabay',
            'type' => 'image',
            'title' => $hit['tags'] ?? 'Imagem Pixabay',
            'preview_url' => $hit['previewURL'] ?? $hit['webformatURL'],
            'download_url' => $hit['largeImageURL'] ?? $hit['webformatURL'],
            'author' => $hit['user'] ?? 'Desconhecido',
            'original_url' => $hit['pageURL'] ?? $url,
            'license_type' => LicenseType::Pixabay->value,
            'requires_attribution' => $attribution['requires_attribution'],
            'attribution_text' => $attribution['attribution_text'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePexelsPage(string $url, ?string $typeHint): array
    {
        if (! preg_match('#pexels\.com/(photo|video)/(?:[^/]+-)?(\d+)#i', $url, $m)) {
            throw new \RuntimeException('Não foi possível extrair o ID do Pexels nesta URL.');
        }

        $kind = strtolower($m[1]);
        $id = (int) $m[2];
        $apiKey = config('criasys.media.pexels_api_key');
        if (! $apiKey) {
            throw new \RuntimeException('PEXELS_API_KEY não configurada — cadastre no .env para importar por link.');
        }

        if ($kind === 'video' || $typeHint === 'video') {
            $response = ExternalHttp::client()->withHeaders(['Authorization' => $apiKey])
                ->get("https://api.pexels.com/videos/videos/{$id}");

            if (! $response->successful()) {
                throw new \RuntimeException('Vídeo não encontrado no Pexels.');
            }

            $video = $response->json();
            $files = collect($video['video_files'] ?? [])->sortByDesc('width')->first();
            $attribution = MediaAttribution::forPexelsVideo($video);

            return [
                'id' => $video['id'],
                'source' => 'pexels',
                'type' => 'video',
                'title' => $video['url'] ?? 'Vídeo Pexels',
                'preview_url' => $video['image'] ?? null,
                'download_url' => $files['link'] ?? null,
                'duration_seconds' => $video['duration'] ?? null,
                'author' => $video['user']['name'] ?? 'Desconhecido',
                'original_url' => $video['url'] ?? $url,
                'license_type' => LicenseType::Pexels->value,
                'requires_attribution' => $attribution['requires_attribution'],
                'attribution_text' => $attribution['attribution_text'],
            ];
        }

        $response = ExternalHttp::client()->withHeaders(['Authorization' => $apiKey])
            ->get("https://api.pexels.com/v1/photos/{$id}");

        if (! $response->successful()) {
            throw new \RuntimeException('Foto não encontrada no Pexels.');
        }

        $photo = $response->json();
        $attribution = MediaAttribution::forPexelsPhoto($photo);

        return [
            'id' => $photo['id'],
            'source' => 'pexels',
            'type' => 'image',
            'title' => $photo['alt'] ?? 'Foto Pexels',
            'preview_url' => $photo['src']['medium'] ?? $photo['src']['small'] ?? null,
            'download_url' => $photo['src']['large2x'] ?? $photo['src']['large'] ?? null,
            'author' => $photo['photographer'] ?? 'Desconhecido',
            'original_url' => $photo['url'] ?? $url,
            'license_type' => LicenseType::Pexels->value,
            'requires_attribution' => $attribution['requires_attribution'],
            'attribution_text' => $attribution['attribution_text'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveUnsplashPage(string $url, ?string $typeHint): array
    {
        if (! preg_match('#unsplash\.com/photos/([a-zA-Z0-9_-]+)#i', $url, $m)) {
            throw new \RuntimeException('URL do Unsplash inválida.');
        }

        $slug = $m[1];
        $photoId = Str::afterLast($slug, '-');
        if ($photoId === '' || strlen($photoId) < 6) {
            $photoId = $slug;
        }

        $accessKey = config('criasys.media.unsplash_access_key');
        if (! $accessKey) {
            throw new \RuntimeException('UNSPLASH_ACCESS_KEY não configurada — cadastre no .env para importar por link.');
        }

        $response = ExternalHttp::client()
            ->withHeaders(['Authorization' => "Client-ID {$accessKey}"])
            ->get("https://api.unsplash.com/photos/{$photoId}");

        if (! $response->successful()) {
            throw new \RuntimeException('Foto não encontrada no Unsplash — verifique o link.');
        }

        $photo = $response->json();
        $attribution = MediaAttribution::forUnsplashPhoto($photo);

        return [
            'id' => $photo['id'],
            'source' => 'unsplash',
            'type' => 'image',
            'title' => $photo['description'] ?? $photo['alt_description'] ?? 'Foto Unsplash',
            'preview_url' => $photo['urls']['small'] ?? $photo['urls']['thumb'] ?? null,
            'download_url' => $photo['urls']['regular'] ?? $photo['urls']['full'] ?? null,
            'author' => $photo['user']['name'] ?? 'Desconhecido',
            'original_url' => ($photo['links']['html'] ?? $url).'?utm_source=criasys_editor&utm_medium=referral',
            'license_type' => LicenseType::Cc0->value,
            'requires_attribution' => $attribution['requires_attribution'],
            'attribution_text' => $attribution['attribution_text'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMixkitPage(string $url, ?string $typeHint): array
    {
        if (preg_match('#mixkit\.co/free-sound-effects/([^/]+)#i', $url, $m)) {
            $slug = $m[1];
            $downloadUrl = "https://assets.mixkit.co/active_storage/sfx/{$slug}/{$slug}.wav";

            return [
                'id' => 'mixkit_sfx_'.$slug,
                'source' => 'mixkit',
                'type' => 'sfx',
                'title' => str_replace('-', ' ', $slug),
                'preview_url' => null,
                'download_url' => $downloadUrl,
                'original_url' => $url,
                'license_type' => LicenseType::Mixkit->value,
                'requires_attribution' => false,
                'attribution_text' => MediaAttribution::forMixkitSfx(str_replace('-', ' ', $slug), $url)['attribution_text'],
            ];
        }

        if (preg_match('#mixkit\.co/free-stock-music/([^/]+)#i', $url, $m)) {
            $slug = $m[1];

            return [
                'id' => 'mixkit_music_'.$slug,
                'source' => 'mixkit',
                'type' => 'audio',
                'title' => str_replace('-', ' ', $slug),
                'preview_url' => null,
                'download_url' => "https://assets.mixkit.co/music/preview/mixkit-{$slug}.mp3",
                'original_url' => $url,
                'license_type' => LicenseType::Mixkit->value,
                'requires_attribution' => false,
                'attribution_text' => MediaAttribution::forMixkitMusic(str_replace('-', ' ', $slug), $url)['attribution_text'],
            ];
        }

        if (preg_match('#mixkit\.co/free-stock-video/([^/]+)#i', $url, $m)) {
            $slug = $m[1];

            return [
                'id' => 'mixkit_video_'.$slug,
                'source' => 'mixkit',
                'type' => 'video',
                'title' => str_replace('-', ' ', $slug),
                'preview_url' => null,
                'download_url' => "https://assets.mixkit.co/videos/preview/mixkit-{$slug}.mp4",
                'original_url' => $url,
                'license_type' => LicenseType::Mixkit->value,
                'requires_attribution' => false,
                'attribution_text' => null,
            ];
        }

        throw new \RuntimeException('Link Mixkit não reconhecido — use a página do som, trilha ou vídeo.');
    }
}
