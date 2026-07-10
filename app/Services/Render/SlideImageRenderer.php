<?php

namespace App\Services\Render;

use App\Models\ExportPreset;
use App\Models\Slide;
use Illuminate\Support\Facades\File;

class SlideImageRenderer
{
    public function renderTextOverlay(Slide $slide, ExportPreset $preset, string $outputPath): void
    {
        File::ensureDirectoryExists(dirname($outputPath));

        $width = $preset->width;
        $height = $preset->height;

        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        $overlay = imagecolorallocatealpha($image, 0, 0, 0, 80);
        imagefilledrectangle($image, 0, 0, $width, $height, $overlay);

        $this->drawSlideText($image, $slide, $width, $height);

        imagepng($image, $outputPath);
        imagedestroy($image);
    }

    public function render(Slide $slide, ExportPreset $preset, string $outputPath): void
    {
        File::ensureDirectoryExists(dirname($outputPath));

        $width = $preset->width;
        $height = $preset->height;

        $image = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($image, 24, 24, 27);
        imagefill($image, 0, 0, $bgColor);

        if ($slide->image_path && file_exists($slide->image_path)) {
            $this->drawBackgroundImage($image, $slide->image_path, $width, $height);
        }

        $this->drawSlideText($image, $slide, $width, $height);

        imagejpeg($image, $outputPath, 92);
        imagedestroy($image);
    }

    private function drawSlideText(\GdImage $image, Slide $slide, int $width, int $height): void
    {
        $style = $slide->resolvedTextStyle();
        $align = $style['align'] ?? 'center';
        $verticalAlign = $style['vertical_align'] ?? 'center';
        $font = $this->fontPath();
        $maxWidth = (int) ($width * 0.85);
        $padding = (int) ($height * 0.08);

        $segments = [];
        if ($slide->title) {
            $segments[] = [
                'text' => $slide->title,
                'size' => (int) ($style['title_size'] ?? 12),
                'color' => $this->hexToRgb($image, $style['title_color'] ?? '#ffffff'),
            ];
        }
        if ($slide->subtitle) {
            $segments[] = [
                'text' => $slide->subtitle,
                'size' => (int) ($style['subtitle_size'] ?? 28),
                'color' => $this->hexToRgb($image, $style['subtitle_color'] ?? '#e5e7eb'),
            ];
        }
        if ($slide->body_text) {
            $segments[] = [
                'text' => $slide->body_text,
                'size' => (int) ($style['body_size'] ?? $style['title_size'] ?? 12),
                'color' => $this->hexToRgb($image, $style['body_color'] ?? $style['title_color'] ?? '#f3f4f6'),
            ];
        }

        if ($segments === []) {
            return;
        }

        $totalHeight = 0;
        $segmentMetrics = [];
        foreach ($segments as $segment) {
            $lines = $this->wrapText($segment['text'], $font, $segment['size'], $maxWidth);
            $lineHeight = (int) ($segment['size'] * 1.4);
            $blockHeight = count($lines) * $lineHeight;
            $segmentMetrics[] = [
                ...$segment,
                'lines' => $lines,
                'lineHeight' => $lineHeight,
                'blockHeight' => $blockHeight,
            ];
            $totalHeight += $blockHeight;
        }

        $startY = match ($verticalAlign) {
            'top' => $padding + ($segmentMetrics[0]['size'] ?? 20),
            'bottom' => $height - $padding - $totalHeight + ($segmentMetrics[0]['size'] ?? 20),
            default => (int) (($height - $totalHeight) / 2) + ($segmentMetrics[0]['size'] ?? 20),
        };

        $y = $startY;
        foreach ($segmentMetrics as $segment) {
            foreach ($segment['lines'] as $line) {
                $box = imagettfbbox($segment['size'], 0, $font, $line);
                $textWidth = abs($box[2] - $box[0]);
                $x = match ($align) {
                    'left' => (int) ($width * 0.075),
                    'right' => (int) ($width * 0.925 - $textWidth),
                    default => (int) (($width - $textWidth) / 2),
                };
                imagettftext($image, $segment['size'], 0, $x, $y, $segment['color'], $font, $line);
                $y += $segment['lineHeight'];
            }
        }
    }

    private function drawBackgroundImage(\GdImage $canvas, string $path, int $width, int $height): void
    {
        $source = $this->loadImage($path);
        if (! $source) {
            return;
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $scale = max($width / $srcW, $height / $srcH);
        $newW = (int) ($srcW * $scale);
        $newH = (int) ($srcH * $scale);
        $dstX = (int) (($width - $newW) / 2);
        $dstY = (int) (($height - $newH) / 2);

        imagecopyresampled($canvas, $source, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);
        imagedestroy($source);

        $overlay = imagecolorallocatealpha($canvas, 0, 0, 0, 80);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $overlay);
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

    private function hexToRgb(\GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

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

    private function fontPath(): string
    {
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

        throw new \RuntimeException('Nenhuma fonte TTF encontrada para renderização de slides.');
    }
}
