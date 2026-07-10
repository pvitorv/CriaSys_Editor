<?php

namespace App\Services\Render;

use App\Models\ExportPreset;
use App\Models\Project;
use App\Models\Slide;
use Illuminate\Support\Facades\File;

class ThumbnailRenderer
{
    public function resolveSettings(Project $project, ?array $overrides = null): array
    {
        $stored = is_array($project->settings['thumbnail'] ?? null)
            ? $project->settings['thumbnail']
            : [];

        return array_merge(config('thumbnail_templates.defaults', []), $stored, $overrides ?? []);
    }

    public function render(Project $project, Slide $slide, ExportPreset $preset, string $outputPath, ?array $settings = null): void
    {
        File::ensureDirectoryExists(dirname($outputPath));

        $settings = $this->resolveSettings($project, $settings);
        $width = $preset->width;
        $height = $preset->height;

        $image = imagecreatetruecolor($width, $height);
        $bg = $this->hexToRgbArray($settings['background_color'] ?? '#18181b');
        $bgColor = imagecolorallocate($image, $bg[0], $bg[1], $bg[2]);
        imagefill($image, 0, 0, $bgColor);

        $layout = config('thumbnail_templates.templates.'.$settings['template'].'.layout', 'overlay_center');
        $this->drawLayoutBackground($image, $slide, $settings, $layout, $width, $height);
        $this->applyImageFilters($image, $settings);
        $this->drawLayoutOverlay($image, $settings, $layout, $width, $height);
        $this->drawTexts($image, $slide, $settings, $layout, $width, $height);

        imagejpeg($image, $outputPath, 92);
        imagedestroy($image);
    }

    private function drawLayoutBackground(\GdImage $canvas, Slide $slide, array $settings, string $layout, int $width, int $height): void
    {
        if ($layout === 'solid') {
            return;
        }

        if (! $slide->image_path || ! file_exists($slide->image_path)) {
            return;
        }

        $source = $this->loadImage($slide->image_path);
        if (! $source) {
            return;
        }

        match ($layout) {
            'split_right' => $this->copyCoverRegion($canvas, $source, 0, 0, (int) ($width * 0.58), $height),
            default => $this->copyCover($canvas, $source, 0, 0, $width, $height),
        };

        imagedestroy($source);
    }

    private function drawLayoutOverlay(\GdImage $image, array $settings, string $layout, int $width, int $height): void
    {
        $opacity = max(0, min(100, (int) ($settings['overlay_opacity'] ?? 45)));
        $alpha = (int) round(127 * (1 - $opacity / 100));
        $accent = $this->hexToRgbArray($settings['accent_color'] ?? '#8b5cf6');

        match ($layout) {
            'bar_bottom' => $this->filledRectAlpha($image, 0, (int) ($height * 0.62), $width, $height, 0, 0, 0, $alpha),
            'split_right' => $this->filledRectAlpha($image, (int) ($width * 0.58), 0, $width, $height, $accent[0], $accent[1], $accent[2], min(127, $alpha + 20)),
            'letterbox' => $this->drawLetterbox($image, $width, $height, $alpha),
            'diagonal_accent' => $this->drawDiagonalAccent($image, $width, $height, $accent, $alpha),
            'border_frame' => $this->drawBorderFrame($image, $width, $height, $accent),
            'gradient_top' => $this->drawGradientTop($image, $width, $height, $alpha),
            default => $this->filledRectAlpha($image, 0, 0, $width, $height, 0, 0, 0, $alpha),
        };
    }

    private function drawTexts(\GdImage $image, Slide $slide, array $settings, string $layout, int $width, int $height): void
    {
        $title = trim((string) ($settings['title_text'] ?? '')) ?: trim((string) ($slide->title ?? ''));
        $subtitle = trim((string) ($settings['subtitle_text'] ?? '')) ?: trim((string) ($slide->subtitle ?? ''));
        if ($subtitle === '' && $title === '') {
            $subtitle = trim((string) ($slide->body_text ?? ''));
            if (strlen($subtitle) > 120) {
                $subtitle = mb_substr($subtitle, 0, 117).'…';
            }
        }

        $font = $this->fontPath($settings['font_family'] ?? 'arial');
        $titleSize = max(18, min(120, (int) ($settings['title_size'] ?? 64)));
        $subtitleSize = max(14, min(72, (int) ($settings['subtitle_size'] ?? 32)));
        $align = $settings['text_align'] ?? 'center';
        $vertical = $settings['vertical_align'] ?? 'center';

        [$textX, $textY, $maxWidth] = match ($layout) {
            'bar_bottom' => [(int) ($width * 0.08), (int) ($height * 0.72), (int) ($width * 0.84)],
            'split_right' => [(int) ($width * 0.62), (int) ($height * 0.35), (int) ($width * 0.34)],
            'border_frame' => [(int) ($width * 0.06), (int) ($height * 0.78), (int) ($width * 0.88)],
            'gradient_top' => [(int) ($width * 0.08), (int) ($height * 0.12), (int) ($width * 0.84)],
            default => match ($vertical) {
                'top' => [(int) ($width * 0.08), (int) ($height * 0.12), (int) ($width * 0.84)],
                'bottom' => [(int) ($width * 0.08), (int) ($height * 0.62), (int) ($width * 0.84)],
                default => [(int) ($width * 0.08), (int) ($height * 0.38), (int) ($width * 0.84)],
            },
        };

        $y = $textY;
        if ($title !== '') {
            $y = $this->drawWrappedText(
                $image,
                $title,
                $font,
                $titleSize,
                $this->hexToRgb($image, $settings['title_color'] ?? '#ffffff'),
                $textX,
                $y,
                $maxWidth,
                $align
            ) + (int) ($titleSize * 0.35);
        }

        if ($subtitle !== '') {
            $this->drawWrappedText(
                $image,
                $subtitle,
                $font,
                $subtitleSize,
                $this->hexToRgb($image, $settings['subtitle_color'] ?? '#e5e7eb'),
                $textX,
                $y,
                $maxWidth,
                $align
            );
        }
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
        string $align
    ): int {
        $lines = $this->wrapText($text, $font, $size, $maxWidth);
        $lineHeight = (int) ($size * 1.25);
        $y = $startY + $size;

        foreach ($lines as $line) {
            $box = imagettfbbox($size, 0, $font, $line);
            $textWidth = abs($box[2] - $box[0]);
            $drawX = match ($align) {
                'left' => $x,
                'right' => $x + $maxWidth - $textWidth,
                default => $x + (int) (($maxWidth - $textWidth) / 2),
            };
            imagettftext($image, $size, 0, $drawX, $y, $color, $font, $line);
            $y += $lineHeight;
        }

        return $y - $lineHeight;
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
        $scale = max($dw / $srcW, $dh / $srcH);
        $newW = (int) ($srcW * $scale);
        $newH = (int) ($srcH * $scale);
        $offsetX = $dx + (int) (($dw - $newW) / 2);
        $offsetY = $dy + (int) (($dh - $newH) / 2);
        imagecopyresampled($canvas, $source, $offsetX, $offsetY, 0, 0, $newW, $newH, $srcW, $srcH);
    }

    private function copyCoverRegion(\GdImage $canvas, \GdImage $source, int $dx, int $dy, int $dw, int $dh): void
    {
        $this->copyCover($canvas, $source, $dx, $dy, $dw, $dh);
    }

    private function filledRectAlpha(\GdImage $image, int $x1, int $y1, int $x2, int $y2, int $r, int $g, int $b, int $alpha): void
    {
        $color = imagecolorallocatealpha($image, $r, $g, $b, max(0, min(127, $alpha)));
        imagefilledrectangle($image, $x1, $y1, $x2, $y2, $color);
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
        $color = imagecolorallocatealpha($image, $accent[0], $accent[1], $accent[2], min(127, $alpha + 25));
        $points = [
            0, (int) ($height * 0.55),
            $width, (int) ($height * 0.35),
            $width, $height,
            0, $height,
        ];
        imagefilledpolygon($image, $points, $color);
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
        $steps = 12;
        for ($i = 0; $i < $steps; $i++) {
            $y1 = (int) ($height * ($i / $steps) * 0.55);
            $y2 = (int) ($height * (($i + 1) / $steps) * 0.55);
            $stepAlpha = min(127, (int) ($alpha + ($steps - $i) * 4));
            $this->filledRectAlpha($image, 0, $y1, $width, $y2, 0, 0, 0, $stepAlpha);
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

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function hexToRgb(\GdImage $image, string $hex): int
    {
        [$r, $g, $b] = $this->hexToRgbArray($hex);

        return imagecolorallocate($image, $r, $g, $b);
    }

    private function wrapText(string $text, string $font, int $size, int $maxWidth): array
    {
        $paragraphs = preg_split("/\r\n|\n|\r/", $text) ?: [$text];
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph)) ?: [];
            if ($words === [] || ($words === [''] && trim($paragraph) === '')) {
                continue;
            }

            $current = '';
            foreach ($words as $word) {
                $test = $current === '' ? $word : $current.' '.$word;
                $box = imagettfbbox($size, 0, $font, $test);
                $width = abs($box[2] - $box[0]);

                if ($width > $maxWidth && $current !== '') {
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
        $match = collect($fonts)->firstWhere('slug', $slug) ?? $fonts[0] ?? null;

        if ($match) {
            $path = PHP_OS_FAMILY === 'Windows' ? ($match['file'] ?? '') : ($match['unix'] ?? $match['file'] ?? '');
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

        $candidates = [
            'C:\\Windows\\Fonts\\arial.ttf',
            'C:\\Windows\\Fonts\\segoeui.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('Nenhuma fonte TTF encontrada para thumbnail.');
    }
}
