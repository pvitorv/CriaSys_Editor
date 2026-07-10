<?php

namespace App\Services\Render;

use App\Models\ExportPreset;
use App\Models\Project;
use App\Models\Slide;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ThumbnailRenderer
{
    public function __construct(
        private ProjectStorageService $storage,
    ) {}
    public function platformPresets(): array
    {
        return config('thumbnail_templates.platforms', []);
    }

    public function resolveSettings(Project $project, ?string $platformSlug = null, ?array $overrides = null): array
    {
        $platform = $platformSlug ?? config('thumbnail_templates.default_platform', 'youtube_landscape');
        $all = $project->settings['thumbnails'] ?? [];

        if ($all === [] && is_array($project->settings['thumbnail'] ?? null)) {
            $all['youtube_landscape'] = $project->settings['thumbnail'];
        }

        $stored = is_array($all[$platform] ?? null) ? $all[$platform] : [];

        return array_merge(
            config('thumbnail_templates.defaults', []),
            config('thumbnail_frames.defaults', []),
            ['platform_preset' => $platform],
            $stored,
            $overrides ?? []
        );
    }

    public function resolveAllSettings(Project $project): array
    {
        $result = [];
        foreach (array_keys($this->platformPresets()) as $slug) {
            $result[$slug] = $this->resolveSettings($project, $slug);
        }

        return $result;
    }

    public function presetForPlatform(string $platformSlug): ExportPreset
    {
        $meta = $this->platformPresets()[$platformSlug] ?? null;
        $exportSlug = $meta['export_preset'] ?? $platformSlug;

        return ExportPreset::where('slug', $exportSlug)->first()
            ?? ExportPreset::where('slug', 'youtube_landscape')->firstOrFail();
    }

    public function outputFilename(string $platformSlug, bool $preview = false): string
    {
        $meta = $this->platformPresets()[$platformSlug] ?? [];
        $base = $meta['filename'] ?? 'thumbnail_'.$platformSlug.'.jpg';

        if ($preview) {
            return 'preview_'.preg_replace('/\.jpg$/i', '', $base).'.jpg';
        }

        return $base;
    }

    public function render(Project $project, ?Slide $slide, ExportPreset $preset, string $outputPath, ?array $settings = null): void
    {
        File::ensureDirectoryExists(dirname($outputPath));

        $settings = $settings ?? $this->resolveSettings($project);
        $width = $preset->width;
        $height = $preset->height;

        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 0, 0, 0));

        $layout = config('thumbnail_templates.templates.'.$settings['template'].'.layout', 'overlay_center');
        $layout = $this->resolveEffectiveLayout($layout, $width, $height);

        $this->drawLayoutBackground($image, $project, $slide, $settings, $layout, $width, $height);
        $this->applyImageFilters($image, $settings);
        $this->drawBackgroundTint($image, $settings, $width, $height);
        $this->drawLayoutOverlay($image, $settings, $layout, $width, $height);
        $this->drawAccentPanel($image, $settings, $layout, $width, $height);
        $this->drawTexts($image, $slide, $settings, $layout, $width, $height);

        app(ThumbnailFrameDrawer::class)->apply($image, $settings, $width, $height, $project);

        imagejpeg($image, $outputPath, 93);
        imagedestroy($image);
    }

    public function renderForPlatform(Project $project, string $platformSlug, ?Slide $slide, string $outputPath, ?array $overrides = null): void
    {
        $project->loadMissing(['user', 'slides']);
        $settings = $this->resolveSettings($project, $platformSlug, $overrides);
        $slide = $this->resolveSlideForSettings($project, $settings, $slide);
        $preset = $this->presetForPlatform($platformSlug);
        $this->render($project, $slide, $preset, $outputPath, $settings);
    }

    public function resolveSlideForSettings(Project $project, array $settings, ?Slide $fallback = null): ?Slide
    {
        $slides = $project->slides->values();

        if ($slides->isEmpty()) {
            return $fallback;
        }

        if (! empty($settings['slide_id'])) {
            $byId = $slides->firstWhere('id', (int) $settings['slide_id']);
            if ($byId) {
                return $byId;
            }
        }

        $index = max(0, (int) ($settings['slide_index'] ?? 0));

        return $slides[$index] ?? $slides->first() ?? $fallback;
    }

    private function resolveBackgroundPath(Project $project, ?Slide $slide, array $settings): ?string
    {
        $source = $settings['image_source'] ?? 'slide';

        if ($source === 'upload') {
            $path = $settings['custom_image_path'] ?? null;

            return ($path && file_exists($path)) ? $path : null;
        }

        if ($source === 'solid' || $source === 'none') {
            return null;
        }

        if ($slide?->video_path && file_exists($slide->video_path)) {
            $frame = $this->extractVideoFrame($project, $slide->video_path);
            if ($frame) {
                return $frame;
            }
        }

        if ($slide?->image_path && file_exists($slide->image_path)) {
            return $slide->image_path;
        }

        return null;
    }

    private function extractVideoFrame(Project $project, string $videoPath): ?string
    {
        $cacheDir = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'thumbs'.DIRECTORY_SEPARATOR.'_video_frames';
        File::ensureDirectoryExists($cacheDir);

        $mtime = @filemtime($videoPath) ?: 0;
        $out = $cacheDir.DIRECTORY_SEPARATOR.'frame_'.md5($videoPath.'|'.$mtime).'.jpg';

        if (file_exists($out)) {
            return $out;
        }

        $ffmpeg = config('criasys.ffmpeg_path', 'ffmpeg');
        $result = Process::timeout(30)->run([
            $ffmpeg,
            '-y',
            '-ss', '0.5',
            '-i', $videoPath,
            '-vframes', '1',
            '-q:v', '2',
            $out,
        ]);

        return $result->successful() && file_exists($out) ? $out : null;
    }

    private function drawLayoutBackground(\GdImage $canvas, Project $project, ?Slide $slide, array $settings, string $layout, int $width, int $height): void
    {
        if ($layout === 'solid') {
            return;
        }

        $path = $this->resolveBackgroundPath($project, $slide, $settings);
        if (! $path) {
            return;
        }

        $source = $this->loadImage($path);
        if (! $source) {
            return;
        }

        match ($layout) {
            'split_right', 'podcast_studio' => $this->copyCover($canvas, $source, 0, 0, (int) ($width * 0.55), $height),
            default => $this->copyCover($canvas, $source, 0, 0, $width, $height),
        };

        imagedestroy($source);
    }

    private function resolveEffectiveLayout(string $layout, int $width, int $height): string
    {
        $isVertical = $height > $width;

        if ($isVertical && in_array($layout, ['overlay_center', 'youtube_pro', 'magazine'], true)) {
            return 'vertical_hero';
        }

        if (abs($width - $height) < 50 && $layout === 'overlay_center') {
            return 'square_clean';
        }

        return $layout;
    }

    private function accentStrength(array $settings): int
    {
        return max(0, min(100, (int) ($settings['accent_opacity'] ?? 0)));
    }

    private function drawBackgroundTint(\GdImage $image, array $settings, int $width, int $height): void
    {
        $opacity = max(0, min(100, (int) ($settings['background_opacity'] ?? 0)));
        if ($opacity <= 0) {
            return;
        }

        $rgb = $this->hexToRgbArray($settings['background_color'] ?? '#09090b');
        $alpha = (int) round(127 * (1 - $opacity / 100));
        $this->filledRectAlpha($image, 0, 0, $width, $height, $rgb[0], $rgb[1], $rgb[2], min(127, $alpha));
    }

    private function drawAccentPanel(\GdImage $image, array $settings, string $layout, int $width, int $height): void
    {
        $opacity = $this->accentStrength($settings);
        if ($opacity <= 0) {
            return;
        }

        [$textX, $textY, $maxWidth] = $this->textBox($layout, $settings, $width, $height);
        $accent = $this->hexToRgbArray($settings['accent_color'] ?? '#ef4444');
        $alpha = (int) round(127 * (1 - $opacity / 100));

        $panelHeight = (int) round($height * 0.34);
        $vertical = $settings['vertical_align'] ?? 'bottom';
        $panelTop = match ($vertical) {
            'top' => (int) round($height * 0.06),
            'center' => (int) round($height * 0.33),
            default => min((int) round($height * 0.58), max(0, $textY - (int) round($height * 0.04))),
        };
        $panelBottom = min($height, $panelTop + $panelHeight);

        $padX = (int) round($width * 0.04);
        $x1 = max(0, $textX - $padX);
        $x2 = min($width, $textX + $maxWidth + $padX);

        $this->filledRectAlpha($image, $x1, $panelTop, $x2, $panelBottom, $accent[0], $accent[1], $accent[2], min(127, $alpha));
    }

    private function drawLayoutOverlay(\GdImage $image, array $settings, string $layout, int $width, int $height): void
    {
        $opacity = max(0, min(100, (int) ($settings['overlay_opacity'] ?? 50)));
        $alpha = (int) round(127 * (1 - $opacity / 100));
        $accentStrength = $this->accentStrength($settings);
        $accent = $accentStrength > 0
            ? $this->hexToRgbArray($settings['accent_color'] ?? '#ef4444')
            : [24, 24, 27];

        match ($layout) {
            'youtube_pro' => $this->drawYoutubeProOverlay($image, $width, $height, $alpha, $accentStrength > 0 ? $accent : null),
            'magazine' => $this->drawMagazineOverlay($image, $width, $height, $alpha),
            'news_breaking' => $accentStrength > 0
                ? $this->drawNewsBreakingOverlay($image, $width, $height, $accent, $alpha)
                : $this->filledRectAlpha($image, 0, 0, $width, $height, 0, 0, 0, max(25, $alpha)),
            'podcast_studio' => $this->filledRectAlpha($image, (int) ($width * 0.55), 0, $width, $height, 18, 18, 22, min(127, $alpha + 15)),
            'vertical_hero' => $this->drawVerticalHeroOverlay($image, $width, $height, $alpha),
            'square_clean' => $this->filledRectAlpha($image, 0, 0, $width, $height, 0, 0, 0, max(30, $alpha - 20)),
            'spotlight' => $this->drawSpotlightOverlay($image, $width, $height, $alpha),
            'gradient_mesh' => $accentStrength > 0
                ? $this->drawGradientMeshOverlay($image, $width, $height, $accent, $alpha)
                : $this->filledRectAlpha($image, 0, 0, $width, $height, 0, 0, 0, $alpha),
            'neon_burst' => $this->filledRectAlpha($image, 0, 0, $width, $height, 0, 0, 0, max(40, $alpha)),
            'bar_bottom' => $this->filledRectAlpha($image, 0, (int) ($height * 0.62), $width, $height, 0, 0, 0, $alpha),
            'split_right' => $accentStrength > 0
                ? $this->filledRectAlpha($image, (int) ($width * 0.55), 0, $width, $height, $accent[0], $accent[1], $accent[2], min(127, $alpha + 20))
                : $this->filledRectAlpha($image, (int) ($width * 0.55), 0, $width, $height, 18, 18, 22, min(127, $alpha + 15)),
            'letterbox' => $this->drawLetterbox($image, $width, $height, $alpha),
            'diagonal_accent' => $accentStrength > 0
                ? $this->drawDiagonalAccent($image, $width, $height, $accent, $alpha)
                : $this->filledRectAlpha($image, 0, 0, $width, $height, 0, 0, 0, max(25, $alpha)),
            'border_frame' => $accentStrength > 0
                ? $this->drawBorderFrame($image, $width, $height, $accent)
                : $this->filledRectAlpha($image, 0, 0, $width, $height, 0, 0, 0, max(20, $alpha - 10)),
            'gradient_top' => $this->drawGradientTop($image, $width, $height, $alpha),
            default => $this->filledRectAlpha($image, 0, 0, $width, $height, 0, 0, 0, $alpha),
        };
    }

    private function drawTexts(\GdImage $image, ?Slide $slide, array $settings, string $layout, int $width, int $height): void
    {
        [$title, $subtitle] = $this->resolveTexts($slide, $settings);
        if ($title === '' && $subtitle === '') {
            return;
        }

        $font = $this->fontPath($settings['font_family'] ?? 'impact');
        $titleSize = $this->scaledSize((int) ($settings['title_size'] ?? 72), $width, $height, 18, 120);
        $subtitleSize = $this->scaledSize((int) ($settings['subtitle_size'] ?? 34), $width, $height, 14, 72);

        [$textX, $textY, $maxWidth, $align] = $this->textBox($layout, $settings, $width, $height);

        $y = $textY;
        if ($title !== '') {
            if ($layout === 'badge_title' && $this->accentStrength($settings) > 0) {
                $this->drawBadgeBehindTitle($image, $title, $font, $titleSize, $textX, $textY, $maxWidth, $align, $settings);
            }
            $y = $this->drawWrappedText(
                $image,
                $title,
                $font,
                $titleSize,
                $this->hexToRgb($image, $settings['title_color'] ?? '#ffffff'),
                $textX,
                $y,
                $maxWidth,
                $align,
                $layout === 'neon_burst'
            ) + (int) ($titleSize * 0.3);
        }

        if ($subtitle !== '') {
            $this->drawWrappedText(
                $image,
                $subtitle,
                $font,
                $subtitleSize,
                $this->hexToRgb($image, $settings['subtitle_color'] ?? '#f4f4f5'),
                $textX,
                $y,
                $maxWidth,
                $align,
                false
            );
        }
    }

    private function resolveTexts(?Slide $slide, array $settings): array
    {
        $title = trim((string) ($settings['title_text'] ?? '')) ?: trim((string) ($slide?->title ?? ''));
        $subtitle = trim((string) ($settings['subtitle_text'] ?? '')) ?: trim((string) ($slide?->subtitle ?? ''));

        if ($subtitle === '' && $title === '') {
            $body = trim((string) ($slide?->body_text ?? ''));
            if ($body !== '') {
                $title = mb_strlen($body) > 80 ? mb_substr($body, 0, 77).'…' : $body;
            }
        }

        return [$title, $subtitle];
    }

    private function textBox(string $layout, array $settings, int $width, int $height): array
    {
        $align = $settings['text_align'] ?? 'left';
        $vertical = $settings['vertical_align'] ?? 'bottom';

        $marginX = (int) round($width * 0.08);
        $maxWidth = (int) round($width * 0.84);

        $textX = match ($align) {
            'right' => $width - $marginX - $maxWidth,
            'center' => (int) round(($width - $maxWidth) / 2),
            default => $marginX,
        };

        $textY = match ($vertical) {
            'top' => (int) round($height * 0.10),
            'center' => (int) round($height * 0.36),
            default => (int) round($height * 0.66),
        };

        if (in_array($layout, ['podcast_studio', 'split_right'], true)) {
            return [
                (int) round($width * 0.58),
                $textY,
                (int) round($width * 0.34),
                $align,
            ];
        }

        if ($layout === 'news_breaking') {
            return [
                $textX,
                max((int) round($height * 0.16), $textY),
                (int) round($width * 0.88),
                $align,
            ];
        }

        return [$textX, $textY, $maxWidth, $align];
    }

    private function scaledSize(int $size, int $width, int $height, int $min, int $max): int
    {
        $ref = min($width, $height);
        $scale = $ref / 1080;
        $scaled = (int) round($size * max(0.55, min(1.35, $scale)));

        return max($min, min($max, $scaled));
    }

    private function drawWrappedText(
        \GdImage $image,
        string $text,
        string $font,
        int $size,
        int $color,
        int $x,
        int $startY,
        int $maxWidth,
        string $align,
        bool $glow = false
    ): int {
        $lines = $this->wrapText($text, $font, $size, $maxWidth);
        $lineHeight = (int) ($size * 1.22);
        $y = $startY + $size;

        foreach ($lines as $line) {
            $box = imagettfbbox($size, 0, $font, $line);
            $textWidth = abs($box[2] - $box[0]);
            $drawX = match ($align) {
                'left' => $x,
                'right' => $x + $maxWidth - $textWidth,
                default => $x + (int) (($maxWidth - $textWidth) / 2),
            };

            if ($glow) {
                $glowColor = imagecolorallocatealpha($image, 255, 60, 120, 90);
                foreach ([[-2, 0], [2, 0], [0, -2], [0, 2]] as [$ox, $oy]) {
                    imagettftext($image, $size, 0, $drawX + $ox, $y + $oy, $glowColor, $font, $line);
                }
            }

            $shadow = imagecolorallocatealpha($image, 0, 0, 0, 60);
            imagettftext($image, $size, 0, $drawX + 3, $y + 3, $shadow, $font, $line);
            imagettftext($image, $size, 0, $drawX, $y, $color, $font, $line);
            $y += $lineHeight;
        }

        return $y - $lineHeight;
    }

    private function drawBadgeBehindTitle(\GdImage $image, string $title, string $font, int $size, int $x, int $y, int $maxWidth, string $align, array $settings): void
    {
        $lines = $this->wrapText($title, $font, $size, $maxWidth);
        $line = $lines[0] ?? $title;
        $box = imagettfbbox($size, 0, $font, $line);
        $textWidth = abs($box[2] - $box[0]);
        $drawX = match ($align) {
            'right' => $x + $maxWidth - $textWidth,
            'center' => $x + (int) (($maxWidth - $textWidth) / 2),
            default => $x,
        };
        $accent = $this->hexToRgbArray($settings['accent_color'] ?? '#ef4444');
        $padX = (int) ($size * 0.35);
        $padY = (int) ($size * 0.25);
        $accentAlpha = (int) round(127 * (1 - $this->accentStrength($settings) / 100));
        $this->filledRectAlpha(
            $image,
            max(0, $drawX - $padX),
            $y,
            min(imagesx($image), $drawX + $textWidth + $padX),
            $y + $size + $padY,
            $accent[0],
            $accent[1],
            $accent[2],
            min(127, max(10, $accentAlpha))
        );
    }

    private function drawYoutubeProOverlay(\GdImage $image, int $width, int $height, int $alpha, ?array $accent = null): void
    {
        $start = (int) ($height * 0.45);
        for ($i = 0; $i < 10; $i++) {
            $y1 = $start + (int) (($height - $start) * ($i / 10));
            $y2 = $start + (int) (($height - $start) * (($i + 1) / 10));
            $this->filledRectAlpha($image, 0, $y1, $width, $y2, 0, 0, 0, min(127, $alpha + $i * 4));
        }
        if ($accent) {
            $barH = max(6, (int) ($height * 0.012));
            $accentColor = imagecolorallocate($image, $accent[0], $accent[1], $accent[2]);
            imagefilledrectangle($image, 0, $height - (int) ($height * 0.28), $barH, $height, $accentColor);
        }
    }

    private function drawMagazineOverlay(\GdImage $image, int $width, int $height, int $alpha): void
    {
        for ($i = 0; $i < 14; $i++) {
            $y1 = (int) ($height * 0.35 + ($height * 0.65 * $i / 14));
            $y2 = (int) ($height * 0.35 + ($height * 0.65 * ($i + 1) / 14));
            $this->filledRectAlpha($image, 0, $y1, $width, $y2, 0, 0, 0, min(127, $alpha + 8 + $i * 3));
        }
    }

    private function drawNewsBreakingOverlay(\GdImage $image, int $width, int $height, array $accent, int $alpha): void
    {
        $bannerH = (int) ($height * 0.14);
        imagefilledrectangle($image, 0, 0, $width, $bannerH, imagecolorallocate($image, $accent[0], $accent[1], $accent[2]));
        $this->filledRectAlpha($image, 0, $bannerH, $width, $height, 0, 0, 0, max(25, $alpha - 10));
    }

    private function drawVerticalHeroOverlay(\GdImage $image, int $width, int $height, int $alpha): void
    {
        $start = (int) ($height * 0.42);
        for ($i = 0; $i < 12; $i++) {
            $y1 = $start + (int) (($height - $start) * $i / 12);
            $y2 = $start + (int) (($height - $start) * ($i + 1) / 12);
            $this->filledRectAlpha($image, 0, $y1, $width, $y2, 0, 0, 0, min(127, $alpha + $i * 5));
        }
    }

    private function drawSpotlightOverlay(\GdImage $image, int $width, int $height, int $alpha): void
    {
        $this->filledRectAlpha($image, 0, 0, $width, $height, 0, 0, 0, max(35, $alpha));
        $cx = (int) ($width / 2);
        $cy = (int) ($height * 0.42);
        $steps = 8;
        for ($i = 0; $i < $steps; $i++) {
            $r = (int) (max($width, $height) * (0.25 + $i * 0.08));
            $this->filledRectAlpha($image, $cx - $r, $cy - $r, $cx + $r, $cy + $r, 0, 0, 0, min(127, 90 - $i * 8));
        }
    }

    private function drawGradientMeshOverlay(\GdImage $image, int $width, int $height, array $accent, int $alpha): void
    {
        for ($i = 0; $i < 8; $i++) {
            $y1 = (int) ($height * $i / 8);
            $y2 = (int) ($height * ($i + 1) / 8);
            $this->filledRectAlpha($image, 0, $y1, $width, $y2, $accent[0], $accent[1], $accent[2], min(127, 100 - $i * 8));
        }
        $this->filledRectAlpha($image, 0, (int) ($height * 0.5), $width, $height, 0, 0, 0, min(127, $alpha + 20));
    }

    private function applyImageFilters(\GdImage $image, array $settings): void
    {
        $brightness = max(-100, min(100, (int) ($settings['brightness'] ?? 0)));
        $contrast = max(-100, min(100, (int) ($settings['contrast'] ?? 0)));

        if ($brightness !== 0) {
            imagefilter($image, IMG_FILTER_BRIGHTNESS, (int) round($brightness * 2.55));
        }
        if ($contrast !== 0) {
            imagefilter($image, IMG_FILTER_CONTRAST, (int) round(-$contrast * 2.55));
        }
    }

    private function copyCover(\GdImage $canvas, \GdImage $source, int $dx, int $dy, int $dw, int $dh): void
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $scale = max($dw / max(1, $srcW), $dh / max(1, $srcH));
        $newW = (int) ($srcW * $scale);
        $newH = (int) ($srcH * $scale);
        imagecopyresampled(
            $canvas,
            $source,
            $dx + (int) (($dw - $newW) / 2),
            $dy + (int) (($dh - $newH) / 2),
            0,
            0,
            $newW,
            $newH,
            $srcW,
            $srcH
        );
    }

    private function filledRectAlpha(\GdImage $image, int $x1, int $y1, int $x2, int $y2, int $r, int $g, int $b, int $alpha): void
    {
        imagefilledrectangle($image, $x1, $y1, $x2, $y2, imagecolorallocatealpha($image, $r, $g, $b, max(0, min(127, $alpha))));
    }

    private function drawLetterbox(\GdImage $image, int $width, int $height, int $alpha): void
    {
        $bar = (int) ($height * 0.1);
        $this->filledRectAlpha($image, 0, 0, $width, $bar, 0, 0, 0, min(127, $alpha + 10));
        $this->filledRectAlpha($image, 0, $height - $bar, $width, $height, 0, 0, 0, min(127, $alpha + 10));
        $this->filledRectAlpha($image, 0, 0, $width, $height, 0, 0, 0, max(20, $alpha - 15));
    }

    private function drawDiagonalAccent(\GdImage $image, int $width, int $height, array $accent, int $alpha): void
    {
        $this->filledRectAlpha($image, 0, 0, $width, $height, 0, 0, 0, max(25, $alpha - 10));
        imagefilledpolygon($image, [
            0, (int) ($height * 0.55),
            $width, (int) ($height * 0.35),
            $width, $height,
            0, $height,
        ], imagecolorallocatealpha($image, $accent[0], $accent[1], $accent[2], min(127, $alpha + 25)));
    }

    private function drawBorderFrame(\GdImage $image, int $width, int $height, array $accent): void
    {
        $border = max(8, (int) ($width * 0.012));
        $outer = imagecolorallocate($image, $accent[0], $accent[1], $accent[2]);
        imagefilledrectangle($image, 0, 0, $width, $border, $outer);
        imagefilledrectangle($image, 0, $height - $border, $width, $height, $outer);
        imagefilledrectangle($image, 0, 0, $border, $height, $outer);
        imagefilledrectangle($image, $width - $border, 0, $width, $height, $outer);
        $this->filledRectAlpha($image, $border, $border, $width - $border, $height - $border, 0, 0, 0, 70);
    }

    private function drawGradientTop(\GdImage $image, int $width, int $height, int $alpha): void
    {
        for ($i = 0; $i < 12; $i++) {
            $y1 = (int) ($height * ($i / 12) * 0.55);
            $y2 = (int) ($height * (($i + 1) / 12) * 0.55);
            $this->filledRectAlpha($image, 0, $y1, $width, $y2, 0, 0, 0, min(127, (int) ($alpha + (12 - $i) * 4)));
        }
    }

    private function loadImage(string $path): ?\GdImage
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path) ?: null,
            'png' => @imagecreatefrompng($path) ?: null,
            'webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => null,
        };
    }

    private function hexToRgbArray(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function hexToRgb(\GdImage $image, string $hex): int
    {
        [$r, $g, $b] = $this->hexToRgbArray($hex);

        return imagecolorallocate($image, $r, $g, $b);
    }

    private function wrapText(string $text, string $font, int $size, int $maxWidth): array
    {
        $lines = [];
        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [$text] as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph)) ?: [];
            if ($words === [] || ($words === [''] && trim($paragraph) === '')) {
                continue;
            }
            $current = '';
            foreach ($words as $word) {
                $test = $current === '' ? $word : $current.' '.$word;
                if (abs(imagettfbbox($size, 0, $font, $test)[2] - imagettfbbox($size, 0, $font, $test)[0]) > $maxWidth && $current !== '') {
                    $lines[] = $current;
                    $current = $word;
                } else {
                    $current = $test;
                }
            }
            if ($current !== '') {
                $lines[] = $current;
            }
        }

        return $lines ?: [''];
    }

    private function fontPath(string $slug): string
    {
        $fonts = config('thumbnail_templates.fonts', []);
        $match = collect($fonts)->firstWhere('slug', $slug);

        $paths = [];
        if ($match) {
            $paths[] = PHP_OS_FAMILY === 'Windows' ? ($match['file'] ?? '') : ($match['unix'] ?? $match['file'] ?? '');
            if (! empty($match['fallback'] ?? null)) {
                $paths[] = $match['fallback'];
            }
        }

        foreach ($paths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        foreach ($fonts as $font) {
            $path = PHP_OS_FAMILY === 'Windows' ? ($font['file'] ?? '') : ($font['unix'] ?? $font['file'] ?? '');
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('Nenhuma fonte TTF encontrada para thumbnail.');
    }
}
