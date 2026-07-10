<?php

namespace App\Services\Render;

use App\Models\Project;
use App\Services\ThumbnailFrameLibraryService;

class ThumbnailFrameDrawer
{
    public function apply(\GdImage $image, array $settings, int $width, int $height, ?Project $project = null): void
    {
        $slug = $settings['frame_slug'] ?? 'none';
        if ($slug === '' || $slug === 'none') {
            return;
        }

        $user = $project?->user;
        $meta = app(ThumbnailFrameLibraryService::class)->resolveFrameMeta($user, $slug)
            ?? config('thumbnail_frames.frames.'.$slug);

        if (! $meta) {
            return;
        }

        $style = $meta['style'] ?? 'solid';
        $primary = $this->hexToRgbArray($settings['frame_color'] ?? $meta['default_color'] ?? $settings['accent_color'] ?? '#ffffff');
        $secondary = $this->hexToRgbArray($settings['frame_secondary_color'] ?? $settings['accent_color'] ?? '#ef4444');
        $opacity = max(0, min(100, (int) ($settings['frame_opacity'] ?? 100)));
        $alpha = (int) round(127 * (1 - $opacity / 100));
        $baseWeight = max(0.3, min(4.0, ((int) ($settings['frame_width'] ?? 28)) / 20));
        $inset = max(0, min(80, (int) ($settings['frame_inset'] ?? 12)));
        $ref = min($width, $height);
        $border = max(2, (int) round($ref * 0.008 * $baseWeight));

        match ($style) {
            'solid' => $this->solidBorder($image, $width, $height, $primary, $border, $inset, $alpha, $meta['weight'] ?? 1),
            'double' => $this->doubleBorder($image, $width, $height, $primary, $border, $inset, $alpha),
            'triple' => $this->tripleBorder($image, $width, $height, $primary, $border, $inset, $alpha),
            'dashed' => $this->dashedBorder($image, $width, $height, $primary, $border, $inset, $alpha),
            'dotted' => $this->dottedBorder($image, $width, $height, $primary, $border, $inset, $alpha),
            'inset_mat' => $this->insetMat($image, $width, $height, $primary, $border, $inset, $alpha),
            'rounded' => $this->roundedBorder($image, $width, $height, $primary, $border * 2, $inset, $alpha, false),
            'rounded_thick' => $this->roundedBorder($image, $width, $height, $primary, $border * 4, $inset, $alpha, true),
            'pill_inset' => $this->pillInset($image, $width, $height, $primary, $border, $inset, $alpha),
            'offset_shadow' => $this->offsetShadow($image, $width, $height, $primary, $border, $inset),
            'side_bars' => $this->sideBars($image, $width, $height, $primary, $secondary, $border, $alpha),
            'top_bottom_bars' => $this->topBottomBars($image, $width, $height, $primary, $secondary, $border, $alpha),
            'gradient_border' => $this->gradientBorder($image, $width, $height, $primary, $secondary, $border, $inset),
            'split_duotone' => $this->splitDuotone($image, $width, $height, $primary, $secondary, $border, $inset, $alpha),
            'letterbox' => $this->letterboxFrame($image, $width, $height, $alpha),
            'film_strip' => $this->filmStrip($image, $width, $height, $alpha),
            'broadcast' => $this->broadcastFrame($image, $width, $height, $primary, $secondary, $alpha),
            'viewfinder' => $this->viewfinder($image, $width, $height, $primary, $alpha),
            'rec_dot' => $this->recDot($image, $width, $height, $primary, $alpha),
            'scope_bars' => $this->scopeBars($image, $width, $height, $alpha),
            'polaroid' => $this->polaroid($image, $width, $height, $alpha),
            'ornate_corners' => $this->ornateCorners($image, $width, $height, $primary, $border, $alpha),
            'ticket' => $this->ticketStub($image, $width, $height, $primary, $alpha),
            'scotch_tape' => $this->scotchTape($image, $width, $height, $alpha),
            'newspaper' => $this->newspaperFrame($image, $width, $height, $alpha),
            'vignette_warm' => $this->vignetteWarm($image, $width, $height),
            'neon_glow' => $this->neonGlow($image, $width, $height, $primary, $border, $inset),
            'neon_double' => $this->neonDouble($image, $width, $height, $primary, $secondary, $border, $inset),
            'cyber_grid' => $this->cyberGrid($image, $width, $height, $primary, $alpha),
            'rgb_segments' => $this->rgbSegments($image, $width, $height, $border, $inset),
            'pulse_ring' => $this->pulseRing($image, $width, $height, $primary, $secondary, $border, $inset),
            'gold_double' => $this->goldDouble($image, $width, $height, $primary, $border, $inset),
            'gold_ornate' => $this->goldOrnate($image, $width, $height, $primary, $border, $alpha),
            'chrome' => $this->chromeBorder($image, $width, $height, $border, $inset),
            'marble_mat' => $this->marbleMat($image, $width, $height, $border, $inset, $alpha),
            'luxury_inset' => $this->luxuryInset($image, $width, $height, $primary, $border, $inset, $alpha),
            'ig_gradient_ring' => $this->igGradientRing($image, $width, $height, $border, $inset),
            'tiktok_offset' => $this->tiktokOffset($image, $width, $height, $primary, $secondary, $border),
            'yt_accent' => $this->ytAccent($image, $width, $height, $primary, $border, $inset),
            'stories_gradient' => $this->storiesGradient($image, $width, $height, $border, $inset),
            'safe_zone' => $this->safeZone($image, $width, $height, $primary, $alpha),
            'corner_brackets' => $this->cornerBrackets($image, $width, $height, $primary, $border, $inset, $alpha),
            'crosshair' => $this->crosshair($image, $width, $height, $primary, $alpha),
            'scanlines' => $this->scanlines($image, $width, $height, $alpha),
            'circuit_corners' => $this->circuitCorners($image, $width, $height, $primary, $secondary, $border, $alpha),
            'data_hud' => $this->dataHud($image, $width, $height, $primary, $alpha),
            'diagonal_stripes' => $this->diagonalStripes($image, $width, $height, $primary, $secondary, $border, $alpha),
            'zigzag' => $this->zigzagBorder($image, $width, $height, $primary, $border, $alpha),
            'star_corners' => $this->starCorners($image, $width, $height, $primary, $border, $alpha),
            'diamond_corners' => $this->diamondCorners($image, $width, $height, $primary, $secondary, $border, $alpha),
            'brush_edges' => $this->brushEdges($image, $width, $height, $primary, $alpha),
            'torn_paper' => $this->tornPaper($image, $width, $height, $alpha),
            'comic' => $this->comicFrame($image, $width, $height, $primary, $border),
            'rainbow' => $this->rainbowBorder($image, $width, $height, $border, $inset),
            'magazine_bleed' => $this->magazineBleed($image, $width, $height, $primary, $border, $alpha),
            'headline_bar' => $this->headlineBar($image, $width, $height, $primary, $secondary, $alpha),
            'column_gutter' => $this->columnGutter($image, $width, $height, $primary, $alpha),
            'photo_credit' => $this->photoCredit($image, $width, $height, $primary, $alpha),
            'gallery_white' => $this->galleryWhite($image, $width, $height, $border, $inset),
            'beveled_3d' => $this->beveled3d($image, $width, $height, $primary, $border, $inset, $alpha),
            'depth_shadow' => $this->depthShadow($image, $width, $height, $primary, $border, $inset),
            'thin_hairline' => $this->thinHairline($image, $width, $height, $primary, $inset, $alpha),
            'corner_dots' => $this->cornerDots($image, $width, $height, $primary, $secondary, $border, $inset, $alpha),
            'spotlight_vignette' => $this->spotlightVignette($image, $width, $height, $primary),
            'glass_border' => $this->glassBorder($image, $width, $height, $primary, $border, $inset, $alpha),
            'nested_frame' => $this->nestedFrame($image, $width, $height, $primary, $secondary, $border, $inset, $alpha),
            'glow_soft' => $this->glowSoft($image, $width, $height, $primary, $border, $inset),
            'gradient_vignette' => $this->gradientVignette($image, $width, $height, $primary, $secondary),
            'holographic_border' => $this->holographicBorder($image, $width, $height, $border, $inset),
            'dual_corner_accent' => $this->dualCornerAccent($image, $width, $height, $primary, $secondary, $border, $alpha),
            'cinematic_ultra' => $this->cinematicUltra($image, $width, $height, $alpha),
            'monitor_bezel' => $this->monitorBezel($image, $width, $height, $alpha),
            'breaking_news' => $this->breakingNews($image, $width, $height, $primary, $secondary, $alpha),
            'vhs_retro' => $this->vhsRetro($image, $width, $height, $alpha),
            'stamp_seal' => $this->stampSeal($image, $width, $height, $primary, $alpha),
            'glitch_chroma' => $this->glitchChroma($image, $width, $height, $primary, $secondary, $border),
            'corporate_accent' => $this->corporateAccent($image, $width, $height, $primary, $secondary, $border, $inset, $alpha),
            'sport_diagonal' => $this->sportDiagonal($image, $width, $height, $primary, $secondary, $alpha),
            'ribbon_corner' => $this->ribbonCorner($image, $width, $height, $primary, $alpha),
            'barcode_strip' => $this->barcodeStrip($image, $width, $height, $primary, $alpha),
            'podcast_wave' => $this->podcastWave($image, $width, $height, $primary, $secondary, $alpha),
            'confetti_dots' => $this->confettiDots($image, $width, $height, $alpha),
            'halftone_edge' => $this->halftoneEdge($image, $width, $height, $primary, $alpha),
            'frost_ice' => $this->frostIce($image, $width, $height, $primary, $border, $inset, $alpha),
            'fire_warm' => $this->fireWarm($image, $width, $height, $primary, $border, $inset),
            'comic_yellow_red' => $this->comicYellowRed($image, $width, $height, $primary, $secondary, $border),
            'ray_burst' => $this->rayBurst($image, $width, $height, $primary, $secondary, $alpha),
            'vs_diagonal_split' => $this->vsDiagonalSplit($image, $width, $height, $primary, $secondary, $alpha),
            'speech_bubble_corner' => $this->speechBubbleCorner($image, $width, $height, $primary, $alpha),
            'ndn_navy_brand' => $this->ndnNavyBrand($image, $width, $height, $primary, $secondary, $alpha),
            'ndn_growth_stripe' => $this->ndnGrowthStripe($image, $width, $height, $primary, $secondary, $alpha),
            'ide_titlebar' => $this->ideTitlebar($image, $width, $height, $primary, $secondary, $alpha),
            'cftv_orange_brackets' => $this->cftvOrangeBrackets($image, $width, $height, $primary, $secondary, $border, $inset, $alpha),
            'horror_crimson' => $this->horrorCrimson($image, $width, $height, $primary, $border, $inset),
            'chalkboard_edu' => $this->chalkboardEdu($image, $width, $height, $primary, $border, $inset, $alpha),
            'comic_bubble_round' => $this->comicBubbleRound($image, $width, $height, $primary, $secondary, $alpha),
            'comic_bubble_shout' => $this->comicBubbleShout($image, $width, $height, $primary, $secondary, $alpha),
            'comic_bubble_thought' => $this->comicBubbleThought($image, $width, $height, $primary, $alpha),
            'manga_bubble' => $this->mangaBubble($image, $width, $height, $primary, $secondary, $alpha),
            'manga_scream' => $this->mangaScream($image, $width, $height, $primary, $secondary, $alpha),
            'comic_bubble_double' => $this->comicBubbleDouble($image, $width, $height, $primary, $secondary, $alpha),
            'comic_narrator_box' => $this->comicNarratorBox($image, $width, $height, $primary, $secondary, $alpha),
            'comic_panel_bubbles' => $this->comicPanelBubbles($image, $width, $height, $primary, $secondary, $alpha),
            'overlay_image' => $this->overlayImage($image, $width, $height, $meta, $alpha),
            default => $this->solidBorder($image, $width, $height, $primary, $border, $inset, $alpha, 1),
        };
    }

    private function solidBorder(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha, float $weight = 1): void
    {
        $t = max(2, (int) ($b * $weight));
        $c = $this->color($img, $rgb, $alpha);
        for ($i = 0; $i < $t; $i++) {
            $o = $inset + $i;
            imagerectangle($img, $o, $o, $w - 1 - $o, $h - 1 - $o, $c);
        }
    }

    private function doubleBorder(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $gap = max(4, (int) ($b * 2));
        imagerectangle($img, $inset, $inset, $w - 1 - $inset, $h - 1 - $inset, $c);
        imagerectangle($img, $inset + $gap, $inset + $gap, $w - 1 - $inset - $gap, $h - 1 - $inset - $gap, $c);
    }

    private function tripleBorder(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $g = max(3, $b);
        for ($i = 0; $i < 3; $i++) {
            $o = $inset + $i * ($g + 2);
            imagerectangle($img, $o, $o, $w - 1 - $o, $h - 1 - $o, $c);
        }
    }

    private function dashedBorder(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $dash = max(12, (int) (min($w, $h) * 0.04));
        $this->dashedLine($img, $inset, $inset, $w - $inset, $inset, $c, $dash);
        $this->dashedLine($img, $inset, $h - $inset, $w - $inset, $h - $inset, $c, $dash);
        $this->dashedLine($img, $inset, $inset, $inset, $h - $inset, $c, $dash);
        $this->dashedLine($img, $w - $inset, $inset, $w - $inset, $h - $inset, $c, $dash);
    }

    private function dottedBorder(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $step = max(8, (int) (min($w, $h) * 0.025));
        for ($x = $inset; $x < $w - $inset; $x += $step) {
            imagefilledellipse($img, $x, $inset, $b + 2, $b + 2, $c);
            imagefilledellipse($img, $x, $h - $inset, $b + 2, $b + 2, $c);
        }
        for ($y = $inset; $y < $h - $inset; $y += $step) {
            imagefilledellipse($img, $inset, $y, $b + 2, $b + 2, $c);
            imagefilledellipse($img, $w - $inset, $y, $b + 2, $b + 2, $c);
        }
    }

    private function insetMat(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha): void
    {
        $this->filledAlpha($img, $inset, $inset, $w - $inset, $h - $inset, $rgb, min(127, $alpha + 40));
        $this->solidBorder($img, $w, $h, $rgb, $b, $inset, $alpha, 0.8);
    }

    private function roundedBorder(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha, bool $thick): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $r = max(12, (int) (min($w, $h) * ($thick ? 0.08 : 0.05)));
        $this->roundedRectStroke($img, $inset, $inset, $w - $inset, $h - $inset, $r, $c, $b);
    }

    private function pillInset(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $pad = $inset + max(8, (int) (min($w, $h) * 0.04));
        $this->roundedRectStroke($img, $pad, $pad, $w - $pad, $h - $pad, (int) (min($w, $h) * 0.12), $c, $b * 2);
    }

    private function offsetShadow(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset): void
    {
        $sh = $this->color($img, [0, 0, 0], 70);
        $off = max(4, (int) (min($w, $h) * 0.015));
        imagerectangle($img, $inset + $off, $inset + $off, $w - $inset + $off, $h - $inset + $off, $sh);
        $this->solidBorder($img, $w, $h, $rgb, $b, $inset, 0, 1);
    }

    private function sideBars(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $alpha): void
    {
        $bw = max(8, (int) ($w * 0.04));
        $this->filledAlpha($img, 0, 0, $bw, $h, $p, $alpha);
        $this->filledAlpha($img, $w - $bw, 0, $w, $h, $s, $alpha);
    }

    private function topBottomBars(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $alpha): void
    {
        $bh = max(6, (int) ($h * 0.035));
        $this->filledAlpha($img, 0, 0, $w, $bh, $p, $alpha);
        $this->filledAlpha($img, 0, $h - $bh, $w, $h, $s, $alpha);
    }

    private function gradientBorder(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $inset): void
    {
        $steps = max(6, $b);
        for ($i = 0; $i < $steps; $i++) {
            $t = $i / max(1, $steps - 1);
            $rgb = [
                (int) ($p[0] + ($s[0] - $p[0]) * $t),
                (int) ($p[1] + ($s[1] - $p[1]) * $t),
                (int) ($p[2] + ($s[2] - $p[2]) * $t),
            ];
            $o = $inset + $i;
            imagerectangle($img, $o, $o, $w - 1 - $o, $h - 1 - $o, $this->color($img, $rgb, 0));
        }
    }

    private function splitDuotone(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $inset, int $alpha): void
    {
        $this->filledAlpha($img, $inset, $inset, $w - $inset, (int) ($h / 2), $p, $alpha);
        $this->filledAlpha($img, $inset, (int) ($h / 2), $w - $inset, $h - $inset, $s, $alpha);
        $this->solidBorder($img, $w, $h, $p, $b, $inset, 0, 1);
    }

    private function letterboxFrame(\GdImage $img, int $w, int $h, int $alpha): void
    {
        $bar = (int) ($h * 0.12);
        $this->filledAlpha($img, 0, 0, $w, $bar, [0, 0, 0], min(127, $alpha + 20));
        $this->filledAlpha($img, 0, $h - $bar, $w, $h, [0, 0, 0], min(127, $alpha + 20));
    }

    private function filmStrip(\GdImage $img, int $w, int $h, int $alpha): void
    {
        $strip = max(14, (int) ($h * 0.06));
        $this->filledAlpha($img, 0, 0, $w, $strip, [20, 20, 20], $alpha);
        $this->filledAlpha($img, 0, $h - $strip, $w, $h, [20, 20, 20], $alpha);
        $hole = max(6, (int) ($strip * 0.45));
        $c = $this->color($img, [0, 0, 0], 0);
        for ($x = $hole; $x < $w; $x += $hole * 2) {
            imagefilledellipse($img, $x, (int) ($strip / 2), $hole, $hole, $c);
            imagefilledellipse($img, $x, $h - (int) ($strip / 2), $hole, $hole, $c);
        }
    }

    private function broadcastFrame(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $this->topBottomBars($img, $w, $h, [20, 20, 30], [20, 20, 30], 0, $alpha);
        $dot = $this->color($img, $s, 0);
        imagefilledellipse($img, (int) ($w * 0.06), (int) ($h * 0.06), 14, 14, $dot);
        $c = $this->color($img, $p, $alpha);
        imageline($img, (int) ($w * 0.1), (int) ($h * 0.06), (int) ($w * 0.25), (int) ($h * 0.06), $c);
    }

    private function viewfinder(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $len = (int) (min($w, $h) * 0.08);
        $m = (int) (min($w, $h) * 0.05);
        foreach ([[$m, $m, $m + $len, $m], [$m, $m, $m, $m + $len]] as [$x1, $y1, $x2, $y2]) {
            imageline($img, $x1, $y1, $x2, $y2, $c);
        }
        foreach ([[$w - $m, $m, $w - $m - $len, $m], [$w - $m, $m, $w - $m, $m + $len]] as [$x1, $y1, $x2, $y2]) {
            imageline($img, $x1, $y1, $x2, $y2, $c);
        }
        foreach ([[$m, $h - $m, $m + $len, $h - $m], [$m, $h - $m, $m, $h - $m - $len]] as [$x1, $y1, $x2, $y2]) {
            imageline($img, $x1, $y1, $x2, $y2, $c);
        }
        foreach ([[$w - $m, $h - $m, $w - $m - $len, $h - $m], [$w - $m, $h - $m, $w - $m, $h - $m - $len]] as [$x1, $y1, $x2, $y2]) {
            imageline($img, $x1, $y1, $x2, $y2, $c);
        }
        imagerectangle($img, $m, $m, $w - $m, $h - $m, $c);
    }

    private function recDot(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $this->solidBorder($img, $w, $h, $rgb, 3, 8, $alpha, 0.6);
        $red = $this->color($img, [220, 38, 38], 0);
        imagefilledellipse($img, (int) ($w * 0.08), (int) ($h * 0.08), 18, 18, $red);
    }

    private function scopeBars(\GdImage $img, int $w, int $h, int $alpha): void
    {
        $bar = (int) ($h * 0.145);
        $this->filledAlpha($img, 0, 0, $w, $bar, [0, 0, 0], min(127, $alpha + 30));
        $this->filledAlpha($img, 0, $h - $bar, $w, $h, [0, 0, 0], min(127, $alpha + 30));
    }

    private function polaroid(\GdImage $img, int $w, int $h, int $alpha): void
    {
        $side = max(12, (int) (min($w, $h) * 0.04));
        $bottom = max(24, (int) ($h * 0.14));
        $this->filledAlpha($img, 0, 0, $w, $side, [250, 250, 248], min(127, $alpha + 10));
        $this->filledAlpha($img, 0, 0, $side, $h, [250, 250, 248], min(127, $alpha + 10));
        $this->filledAlpha($img, $w - $side, 0, $w, $h, [250, 250, 248], min(127, $alpha + 10));
        $this->filledAlpha($img, 0, $h - $bottom, $w, $h, [250, 250, 248], min(127, $alpha + 10));
    }

    private function ornateCorners(\GdImage $img, int $w, int $h, array $rgb, int $b, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $len = (int) (min($w, $h) * 0.12);
        $m = max(10, (int) (min($w, $h) * 0.04));
        $pairs = [
            [$m, $m + $len, $m, $m, $m + $len, $m],
            [$w - $m, $m + $len, $w - $m, $m, $w - $m - $len, $m],
            [$m, $h - $m - $len, $m, $h - $m, $m + $len, $h - $m],
            [$w - $m, $h - $m - $len, $w - $m, $h - $m, $w - $m - $len, $h - $m],
        ];
        foreach ($pairs as [$x1, $y1, $x2, $y2, $x3, $y3]) {
            imageline($img, $x1, $y1, $x2, $y2, $c);
            imageline($img, $x2, $y2, $x3, $y3, $c);
            imagefilledellipse($img, $x2, $y2, $b + 4, $b + 4, $c);
        }
    }

    private function ticketStub(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $this->solidBorder($img, $w, $h, $rgb, 4, 10, $alpha, 1);
        $notch = (int) (min($w, $h) * 0.04);
        $bg = $this->color($img, [9, 9, 11], 0);
        for ($y = $notch; $y < $h; $y += $notch * 3) {
            imagefilledellipse($img, 0, $y, $notch, $notch, $bg);
            imagefilledellipse($img, $w, $y, $notch, $notch, $bg);
        }
    }

    private function scotchTape(\GdImage $img, int $w, int $h, int $alpha): void
    {
        $tape = $this->color($img, [245, 240, 220], min(127, $alpha + 50));
        $tw = (int) (min($w, $h) * 0.18);
        $th = (int) (min($w, $h) * 0.06);
        imagefilledrectangle($img, (int) ($w * 0.08), -5, (int) ($w * 0.08) + $tw, $th, $tape);
        imagefilledrectangle($img, (int) ($w * 0.72), $h - $th + 5, (int) ($w * 0.72) + $tw, $h + 5, $tape);
    }

    private function newspaperFrame(\GdImage $img, int $w, int $h, int $alpha): void
    {
        $this->filledAlpha($img, 0, 0, $w, max(8, (int) ($h * 0.02)), [30, 30, 30], $alpha);
        $this->solidBorder($img, $w, $h, [40, 40, 40], 2, 6, $alpha, 1);
    }

    private function vignetteWarm(\GdImage $img, int $w, int $h): void
    {
        $steps = 10;
        for ($i = 0; $i < $steps; $i++) {
            $o = (int) (min($w, $h) * 0.04 * $i);
            $this->filledAlpha($img, $o, $o, $w - $o, $h - $o, [80, 50, 20], min(127, 30 + $i * 8));
        }
    }

    private function neonGlow(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset): void
    {
        for ($g = 4; $g >= 1; $g--) {
            $this->solidBorder($img, $w, $h, $rgb, $b + $g * 2, $inset - $g, min(127, 90 - $g * 15), 0.5);
        }
        $this->solidBorder($img, $w, $h, $rgb, $b, $inset, 0, 1.2);
    }

    private function neonDouble(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $inset): void
    {
        $this->neonGlow($img, $w, $h, $p, $b, $inset);
        $this->solidBorder($img, $w, $h, $s, max(2, (int) ($b * 0.6)), $inset + $b + 6, 40, 0.8);
    }

    private function cyberGrid(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $c = $this->color($img, $rgb, min(127, $alpha + 30));
        $step = max(20, (int) (min($w, $h) * 0.08));
        for ($x = 0; $x < $w; $x += $step) {
            imageline($img, $x, 0, $x, $h, $c);
        }
        for ($y = 0; $y < $h; $y += $step) {
            imageline($img, 0, $y, $w, $y, $c);
        }
        $this->cornerBrackets($img, $w, $h, $rgb, 3, 8, 0);
    }

    private function rgbSegments(\GdImage $img, int $w, int $h, int $b, int $inset): void
    {
        $colors = [[255, 0, 80], [0, 255, 120], [0, 120, 255]];
        $seg = (int) (($w + $h) * 2 / 3);
        $o = $inset;
        foreach ($colors as $i => $rgb) {
            $c = $this->color($img, $rgb, 0);
            imagerectangle($img, $o + $i, $o + $i, $w - $o - $i, $h - $o - $i, $c);
        }
    }

    private function pulseRing(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $inset): void
    {
        $this->solidBorder($img, $w, $h, $p, $b, $inset, 0, 1);
        $this->solidBorder($img, $w, $h, $s, max(2, (int) ($b * 0.5)), $inset + $b + 8, 60, 0.6);
        $this->solidBorder($img, $w, $h, $p, 2, $inset + $b + 16, 90, 0.4);
    }

    private function goldDouble(\GdImage $img, int $w, int $h, array $gold, int $b, int $inset): void
    {
        $dark = [120, 90, 30];
        $this->solidBorder($img, $w, $h, $dark, $b + 2, $inset - 1, 20, 1);
        $this->solidBorder($img, $w, $h, $gold, $b, $inset + 2, 0, 1);
        $this->solidBorder($img, $w, $h, [255, 230, 150], max(1, (int) ($b * 0.4)), $inset + $b + 4, 50, 0.5);
    }

    private function goldOrnate(\GdImage $img, int $w, int $h, array $gold, int $b, int $alpha): void
    {
        $this->goldDouble($img, $w, $h, $gold, $b, 10);
        $this->ornateCorners($img, $w, $h, $gold, $b + 2, $alpha);
    }

    private function chromeBorder(\GdImage $img, int $w, int $h, int $b, int $inset): void
    {
        for ($i = 0; $i < max(3, $b); $i++) {
            $shade = 180 - $i * 25;
            imagerectangle($img, $inset + $i, $inset + $i, $w - $inset - $i, $h - $inset - $i, $this->color($img, [$shade, $shade, $shade + 10], 0));
        }
    }

    private function marbleMat(\GdImage $img, int $w, int $h, int $b, int $inset, int $alpha): void
    {
        $this->filledAlpha($img, $inset, $inset, $w - $inset, $h - $inset, [240, 238, 235], min(127, $alpha + 30));
        $this->goldDouble($img, $w, $h, [200, 200, 200], $b, $inset);
    }

    private function luxuryInset(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha): void
    {
        $this->filledAlpha($img, $inset + 4, $inset + 4, $w - $inset - 4, $h - $inset - 4, [0, 0, 0], min(127, $alpha + 50));
        $this->doubleBorder($img, $w, $h, $rgb, $b, $inset, $alpha);
    }

    private function igGradientRing(\GdImage $img, int $w, int $h, int $b, int $inset): void
    {
        $colors = [[250, 80, 120], [180, 60, 220], [255, 180, 60]];
        foreach ($colors as $i => $rgb) {
            $this->solidBorder($img, $w, $h, $rgb, max(3, $b - $i), $inset + $i * 2, 30, 1);
        }
    }

    private function tiktokOffset(\GdImage $img, int $w, int $h, array $p, array $s, int $b): void
    {
        $off = max(3, (int) (min($w, $h) * 0.008));
        $this->solidBorder($img, $w, $h, $s, $b, 10 + $off, 40, 1);
        $this->solidBorder($img, $w, $h, $p, $b, 10, 0, 1);
    }

    private function ytAccent(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset): void
    {
        $this->solidBorder($img, $w, $h, [255, 255, 255], $b, $inset, 0, 0.8);
        $bar = max(6, (int) ($h * 0.025));
        $this->filledAlpha($img, $inset, $h - $inset - $bar, $w - $inset, $h - $inset, $rgb, 0);
    }

    private function storiesGradient(\GdImage $img, int $w, int $h, int $b, int $inset): void
    {
        $this->igGradientRing($img, $w, $h, $b + 2, max(4, $inset - 2));
        $this->roundedBorder($img, $w, $h, [255, 255, 255], 2, $inset + $b + 4, 0, false);
    }

    private function safeZone(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $m = (int) (min($w, $h) * 0.08);
        $c = $this->color($img, $rgb, min(127, $alpha + 40));
        imagerectangle($img, $m, $m, $w - $m, $h - $m, $c);
        imagerectangle($img, $m + 2, $m + 2, $w - $m - 2, $h - $m - 2, $c);
    }

    private function cornerBrackets(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $len = (int) (min($w, $h) * 0.1);
        $m = max(8, $inset);
        foreach ([
            [$m, $m, $m + $len, $m, $m, $m + $len],
            [$w - $m, $m, $w - $m - $len, $m, $w - $m, $m + $len],
            [$m, $h - $m, $m + $len, $h - $m, $m, $h - $m - $len],
            [$w - $m, $h - $m, $w - $m - $len, $h - $m, $w - $m, $h - $m - $len],
        ] as [$x1, $y1, $x2, $y2, $x3, $y3]) {
            imageline($img, $x1, $y1, $x2, $y2, $c);
            imageline($img, $x1, $y1, $x3, $y3, $c);
        }
    }

    private function crosshair(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $cx = (int) ($w / 2);
        $cy = (int) ($h / 2);
        $r = (int) (min($w, $h) * 0.08);
        imageellipse($img, $cx, $cy, $r, $r, $c);
        imageline($img, $cx - $r - 10, $cy, $cx + $r + 10, $cy, $c);
        imageline($img, $cx, $cy - $r - 10, $cx, $cy + $r + 10, $c);
    }

    private function scanlines(\GdImage $img, int $w, int $h, int $alpha): void
    {
        $c = $this->color($img, [0, 0, 0], min(127, $alpha + 60));
        for ($y = 0; $y < $h; $y += 3) {
            imageline($img, 0, $y, $w, $y, $c);
        }
        $this->cornerBrackets($img, $w, $h, [0, 255, 120], 2, 12, 40);
    }

    private function circuitCorners(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $alpha): void
    {
        $this->cornerBrackets($img, $w, $h, $p, $b, 14, $alpha);
        $c = $this->color($img, $s, $alpha);
        $m = 20;
        imageline($img, $m, $m + 30, $m + 20, $m + 30, $c);
        imageline($img, $w - $m, $h - $m - 30, $w - $m - 20, $h - $m - 30, $c);
    }

    private function dataHud(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $this->cornerBrackets($img, $w, $h, $rgb, 3, 10, $alpha);
        $c = $this->color($img, $rgb, min(127, $alpha + 30));
        imagerectangle($img, (int) ($w * 0.04), (int) ($h * 0.04), (int) ($w * 0.2), (int) ($h * 0.07), $c);
        imagerectangle($img, (int) ($w * 0.76), (int) ($h * 0.9), (int) ($w * 0.96), (int) ($h * 0.96), $c);
    }

    private function diagonalStripes(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $alpha): void
    {
        $c1 = $this->color($img, $p, $alpha);
        $step = max(10, (int) (min($w, $h) * 0.04));
        for ($i = -$h; $i < $w + $h; $i += $step * 2) {
            imageline($img, $i, 0, $i + $h, $h, $c1);
        }
        $this->solidBorder($img, $w, $h, $s, $b, 8, 0, 1);
    }

    private function zigzagBorder(\GdImage $img, int $w, int $h, array $rgb, int $b, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $step = max(8, (int) (min($w, $h) * 0.03));
        $y = 8;
        for ($x = 0; $x < $w; $x += $step) {
            imageline($img, $x, $y, $x + (int) ($step / 2), $y + $step, $c);
            imageline($img, $x + (int) ($step / 2), $y + $step, $x + $step, $y, $c);
        }
    }

    private function starCorners(\GdImage $img, int $w, int $h, array $rgb, int $b, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $m = (int) (min($w, $h) * 0.06);
        foreach ([[$m, $m], [$w - $m, $m], [$m, $h - $m], [$w - $m, $h - $m]] as [$cx, $cy]) {
            $this->drawStar($img, $cx, $cy, $b + 6, $c);
        }
    }

    private function diamondCorners(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $alpha): void
    {
        $m = (int) (min($w, $h) * 0.05);
        $size = max(10, $b * 3);
        foreach ([[$m, $m], [$w - $m, $m], [$m, $h - $m], [$w - $m, $h - $m]] as [$cx, $cy]) {
            $this->filledAlpha($img, $cx - $size, $cy - 2, $cx + $size, $cy + 2, $p, $alpha);
            $this->filledAlpha($img, $cx - 2, $cy - $size, $cx + 2, $cy + $size, $s, $alpha);
        }
    }

    private function brushEdges(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        for ($i = 0; $i < 20; $i++) {
            $x = (int) ($w * ($i / 20));
            imageline($img, $x, 0, $x + 8, 12, $c);
            imageline($img, $x, $h, $x + 8, $h - 12, $c);
        }
    }

    private function tornPaper(\GdImage $img, int $w, int $h, int $alpha): void
    {
        $c = $this->color($img, [250, 248, 240], min(127, $alpha + 20));
        for ($x = 0; $x < $w; $x += 6) {
            $j = (int) (sin($x * 0.15) * 3);
            imageline($img, $x, $j, $x, 10 + $j, $c);
            imageline($img, $x, $h - 10 + $j, $x, $h + $j, $c);
        }
    }

    private function comicFrame(\GdImage $img, int $w, int $h, array $rgb, int $b): void
    {
        $black = $this->color($img, [0, 0, 0], 0);
        $thick = max(8, $b * 3);
        imagefilledrectangle($img, 0, 0, $w, $thick, $black);
        imagefilledrectangle($img, 0, $h - $thick, $w, $h, $black);
        imagefilledrectangle($img, 0, 0, $thick, $h, $black);
        imagefilledrectangle($img, $w - $thick, 0, $w, $h, $black);
        $this->solidBorder($img, $w, $h, $rgb, 3, $thick + 4, 0, 1);
    }

    private function rainbowBorder(\GdImage $img, int $w, int $h, int $b, int $inset): void
    {
        $colors = [[255, 0, 0], [255, 127, 0], [255, 255, 0], [0, 200, 0], [0, 100, 255], [140, 0, 255]];
        foreach ($colors as $i => $rgb) {
            $this->solidBorder($img, $w, $h, $rgb, max(2, (int) ($b * 0.7)), $inset + $i * 2, 20, 0.8);
        }
    }

    private function magazineBleed(\GdImage $img, int $w, int $h, array $rgb, int $b, int $alpha): void
    {
        $this->filledAlpha($img, 0, 0, max(4, (int) ($w * 0.015)), $h, $rgb, $alpha);
        $this->solidBorder($img, $w, $h, [30, 30, 30], 2, 0, $alpha, 1);
    }

    private function headlineBar(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $bh = max(12, (int) ($h * 0.08));
        $this->filledAlpha($img, 0, 0, $w, $bh, $p, 0);
        $this->filledAlpha($img, 0, $bh, $w, $bh + 4, $s, 0);
        $this->solidBorder($img, $w, $h, [255, 255, 255], 2, 0, $alpha, 0.5);
    }

    private function columnGutter(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $x1 = (int) ($w * 0.33);
        $x2 = (int) ($w * 0.66);
        imageline($img, $x1, (int) ($h * 0.05), $x1, (int) ($h * 0.95), $c);
        imageline($img, $x2, (int) ($h * 0.05), $x2, (int) ($h * 0.95), $c);
        $this->solidBorder($img, $w, $h, $rgb, 2, 6, $alpha, 0.6);
    }

    private function photoCredit(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $this->solidBorder($img, $w, $h, $rgb, 2, 8, $alpha, 0.7);
        $this->filledAlpha($img, (int) ($w * 0.65), $h - 28, $w - 8, $h - 8, [0, 0, 0], min(127, $alpha + 40));
    }

    private function galleryWhite(\GdImage $img, int $w, int $h, int $b, int $inset): void
    {
        $pad = max(20, (int) (min($w, $h) * 0.06));
        $this->filledAlpha($img, 0, 0, $w, $pad, [255, 255, 255], 10);
        $this->filledAlpha($img, 0, 0, $pad, $h, [255, 255, 255], 10);
        $this->filledAlpha($img, $w - $pad, 0, $w, $h, [255, 255, 255], 10);
        $this->filledAlpha($img, 0, $h - $pad, $w, $h, [255, 255, 255], 10);
        $this->solidBorder($img, $w, $h, [220, 220, 220], 2, $pad - 4, 30, 0.5);
    }

    private function beveled3d(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha): void
    {
        $light = $this->color($img, [min(255, $rgb[0] + 60), min(255, $rgb[1] + 60), min(255, $rgb[2] + 60)], $alpha);
        $dark = $this->color($img, [max(0, $rgb[0] - 60), max(0, $rgb[1] - 60), max(0, $rgb[2] - 60)], $alpha);
        $t = max(3, $b);
        $o = $inset;
        imageline($img, $o, $o, $w - $o, $o, $light);
        imageline($img, $o, $o, $o, $h - $o, $light);
        imageline($img, $w - $o, $o, $w - $o, $h - $o, $dark);
        imageline($img, $o, $h - $o, $w - $o, $h - $o, $dark);
        for ($i = 1; $i < $t; $i++) {
            imagerectangle($img, $o + $i, $o + $i, $w - $o - $i, $h - $o - $i, $this->color($img, $rgb, min(127, $alpha + 20)));
        }
    }

    private function depthShadow(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset): void
    {
        $layers = max(4, (int) (min($w, $h) * 0.012));
        for ($i = $layers; $i >= 1; $i--) {
            $this->solidBorder($img, $w, $h, [0, 0, 0], max(1, (int) ($b * 0.4)), $inset + $i * 2, min(127, 30 + $i * 12), 0.5);
        }
        $this->solidBorder($img, $w, $h, $rgb, $b, $inset, 0, 1);
    }

    private function thinHairline(\GdImage $img, int $w, int $h, array $rgb, int $inset, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $o = max(4, $inset);
        imagerectangle($img, $o, $o, $w - $o, $h - $o, $c);
    }

    private function cornerDots(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $inset, int $alpha): void
    {
        $this->thinHairline($img, $w, $h, $p, $inset, $alpha);
        $r = max(4, $b + 2);
        $m = max(10, $inset + 4);
        foreach ([[$m, $m, $p], [$w - $m, $m, $s], [$m, $h - $m, $s], [$w - $m, $h - $m, $p]] as [$cx, $cy, $rgb]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $this->color($img, $rgb, $alpha));
        }
    }

    private function spotlightVignette(\GdImage $img, int $w, int $h, array $rgb): void
    {
        $steps = 14;
        for ($i = 0; $i < $steps; $i++) {
            $o = (int) (min($w, $h) * 0.035 * $i);
            $this->filledAlpha($img, $o, $o, $w - $o, $h - $o, $rgb, min(127, 15 + $i * 7));
        }
    }

    private function glassBorder(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha): void
    {
        $this->filledAlpha($img, $inset, $inset, $w - $inset, $h - $inset, [255, 255, 255], min(127, $alpha + 70));
        $this->roundedBorder($img, $w, $h, $rgb, max(2, (int) ($b * 0.6)), $inset, min(127, $alpha + 40), false);
        $this->solidBorder($img, $w, $h, [255, 255, 255], 1, $inset + 2, 80, 0.3);
    }

    private function nestedFrame(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $inset, int $alpha): void
    {
        $this->solidBorder($img, $w, $h, $p, $b, $inset, $alpha, 1);
        $gap = max(6, (int) ($b * 2));
        $this->solidBorder($img, $w, $h, $s, max(2, (int) ($b * 0.5)), $inset + $gap, min(127, $alpha + 20), 0.7);
        $this->solidBorder($img, $w, $h, $p, 1, $inset + $gap * 2, min(127, $alpha + 40), 0.4);
    }

    private function glowSoft(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset): void
    {
        for ($g = 6; $g >= 1; $g--) {
            $this->solidBorder($img, $w, $h, $rgb, $b, $inset + $g * 3, min(127, 100 - $g * 12), 0.3);
        }
        $this->thinHairline($img, $w, $h, $rgb, $inset, 0);
    }

    private function gradientVignette(\GdImage $img, int $w, int $h, array $p, array $s): void
    {
        $steps = 12;
        for ($i = 0; $i < $steps; $i++) {
            $t = $i / max(1, $steps - 1);
            $rgb = [
                (int) ($p[0] + ($s[0] - $p[0]) * $t),
                (int) ($p[1] + ($s[1] - $p[1]) * $t),
                (int) ($p[2] + ($s[2] - $p[2]) * $t),
            ];
            $o = (int) (min($w, $h) * 0.03 * $i);
            $this->filledAlpha($img, $o, $o, $w - $o, $h - $o, $rgb, min(127, 20 + $i * 8));
        }
    }

    private function holographicBorder(\GdImage $img, int $w, int $h, int $b, int $inset): void
    {
        $colors = [[255, 0, 128], [0, 200, 255], [255, 200, 0], [128, 0, 255], [0, 255, 128]];
        foreach ($colors as $i => $rgb) {
            $this->solidBorder($img, $w, $h, $rgb, max(2, (int) ($b * 0.5)), $inset + $i, 25, 0.7);
        }
    }

    private function dualCornerAccent(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $alpha): void
    {
        $this->thinHairline($img, $w, $h, $p, 10, $alpha);
        $len = (int) (min($w, $h) * 0.12);
        $m = 12;
        $c1 = $this->color($img, $s, $alpha);
        $c2 = $this->color($img, $p, $alpha);
        imageline($img, $m, $m, $m + $len, $m, $c1);
        imageline($img, $m, $m, $m, $m + $len, $c1);
        imageline($img, $w - $m, $h - $m, $w - $m - $len, $h - $m, $c2);
        imageline($img, $w - $m, $h - $m, $w - $m, $h - $m - $len, $c2);
    }

    private function cinematicUltra(\GdImage $img, int $w, int $h, int $alpha): void
    {
        $bar = (int) ($h * 0.18);
        $this->filledAlpha($img, 0, 0, $w, $bar, [0, 0, 0], min(127, $alpha + 10));
        $this->filledAlpha($img, 0, $h - $bar, $w, $h, [0, 0, 0], min(127, $alpha + 10));
        $notch = (int) ($w * 0.04);
        $this->filledAlpha($img, 0, $bar, $notch, $h - $bar, [0, 0, 0], min(127, $alpha + 20));
        $this->filledAlpha($img, $w - $notch, $bar, $w, $h - $bar, [0, 0, 0], min(127, $alpha + 20));
    }

    private function monitorBezel(\GdImage $img, int $w, int $h, int $alpha): void
    {
        $bezel = max(10, (int) (min($w, $h) * 0.035));
        $this->filledAlpha($img, 0, 0, $w, $bezel, [30, 30, 35], $alpha);
        $this->filledAlpha($img, 0, $h - $bezel, $w, $h, [30, 30, 35], $alpha);
        $this->filledAlpha($img, 0, 0, $bezel, $h, [30, 30, 35], $alpha);
        $this->filledAlpha($img, $w - $bezel, 0, $w, $h, [30, 30, 35], $alpha);
        $led = $this->color($img, [60, 180, 80], 40);
        imagefilledellipse($img, (int) ($w / 2), $h - (int) ($bezel / 2), 6, 6, $led);
    }

    private function breakingNews(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $bh = max(14, (int) ($h * 0.07));
        $this->filledAlpha($img, 0, 0, $w, $bh, $p, 0);
        $this->filledAlpha($img, 0, $bh, $w, $bh + 3, $s, 0);
        $this->solidBorder($img, $w, $h, [255, 255, 255], 2, 0, $alpha, 0.4);
        $c = $this->color($img, [255, 255, 255], 0);
        imagefilledrectangle($img, (int) ($w * 0.03), (int) ($bh * 0.25), (int) ($w * 0.18), (int) ($bh * 0.75), $c);
    }

    private function vhsRetro(\GdImage $img, int $w, int $h, int $alpha): void
    {
        $this->scanlines($img, $w, $h, $alpha);
        $c = $this->color($img, [255, 255, 255], min(127, $alpha + 50));
        for ($y = (int) ($h * 0.92); $y < $h; $y += 2) {
            imageline($img, 0, $y, $w, $y, $c);
        }
        $this->solidBorder($img, $w, $h, [180, 180, 180], 2, 4, $alpha, 0.5);
    }

    private function stampSeal(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $this->solidBorder($img, $w, $h, $rgb, 3, 8, $alpha, 0.8);
        $cx = $w - (int) (min($w, $h) * 0.12);
        $cy = (int) (min($w, $h) * 0.12);
        $r = (int) (min($w, $h) * 0.08);
        $c = $this->color($img, $rgb, min(127, $alpha + 30));
        imageellipse($img, $cx, $cy, $r * 2, $r * 2, $c);
        imageellipse($img, $cx, $cy, (int) ($r * 1.4), (int) ($r * 1.4), $c);
    }

    private function glitchChroma(\GdImage $img, int $w, int $h, array $p, array $s, int $b): void
    {
        $off = max(2, (int) (min($w, $h) * 0.006));
        $this->solidBorder($img, $w, $h, $s, $b, 10 - $off, 50, 1);
        $this->solidBorder($img, $w, $h, [0, 255, 255], max(2, (int) ($b * 0.5)), 10 + $off, 60, 0.5);
        $this->solidBorder($img, $w, $h, $p, $b, 10, 0, 1);
    }

    private function corporateAccent(\GdImage $img, int $w, int $h, array $p, array $s, int $b, int $inset, int $alpha): void
    {
        $this->thinHairline($img, $w, $h, $p, $inset, $alpha);
        $bar = max(5, (int) ($h * 0.022));
        $this->filledAlpha($img, $inset, $h - $inset - $bar, $w - $inset, $h - $inset, $p, 0);
        $this->filledAlpha($img, $inset, $inset, $inset + 4, $h - $inset, $s, min(127, $alpha + 20));
    }

    private function sportDiagonal(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $bw = max(16, (int) ($w * 0.06));
        $c1 = $this->color($img, $p, $alpha);
        $points = [0, 0, $bw, 0, 0, $bw];
        $this->fillPolygon($img, $points, $c1);
        $c2 = $this->color($img, $s, $alpha);
        $points2 = [$w, $h, $w - $bw, $h, $w, $h - $bw];
        $this->fillPolygon($img, $points2, $c2);
        $this->solidBorder($img, $w, $h, $p, 3, 6, $alpha, 0.8);
    }

    private function ribbonCorner(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $this->thinHairline($img, $w, $h, [200, 200, 200], 8, min(127, $alpha + 30));
        $rw = (int) (min($w, $h) * 0.22);
        $rh = (int) (min($w, $h) * 0.06);
        $this->filledAlpha($img, 0, 0, $rw, $rh, $rgb, 0);
        $fold = $this->color($img, [max(0, $rgb[0] - 40), max(0, $rgb[1] - 40), max(0, $rgb[2] - 40)], 20);
        $points = [0, $rh, $rw * 0.15, $rh, 0, $rh + $rw * 0.15];
        $this->fillPolygon($img, $points, $fold);
    }

    private function barcodeStrip(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $this->cornerBrackets($img, $w, $h, $rgb, 2, 10, $alpha);
        $y = $h - max(12, (int) ($h * 0.04));
        $c = $this->color($img, $rgb, $alpha);
        for ($x = (int) ($w * 0.05); $x < (int) ($w * 0.35); $x += 3) {
            $bh = ($x % 6 === 0) ? 10 : 6;
            imageline($img, $x, $y, $x, $y - $bh, $c);
        }
    }

    private function podcastWave(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $this->roundedBorder($img, $w, $h, $p, 3, 10, $alpha, false);
        $base = $h - max(20, (int) ($h * 0.08));
        $c1 = $this->color($img, $p, 0);
        $c2 = $this->color($img, $s, 30);
        $step = max(6, (int) ($w * 0.015));
        for ($x = (int) ($w * 0.08); $x < (int) ($w * 0.92); $x += $step) {
            $amp = (int) (sin($x * 0.08) * 8 + cos($x * 0.03) * 6 + 10);
            imageline($img, $x, $base, $x, $base - $amp, ($x % ($step * 2) === 0) ? $c1 : $c2);
        }
    }

    private function confettiDots(\GdImage $img, int $w, int $h, int $alpha): void
    {
        $this->thinHairline($img, $w, $h, [255, 255, 255], 6, min(127, $alpha + 40));
        $colors = [[239, 68, 68], [234, 179, 8], [34, 197, 94], [59, 130, 246], [168, 85, 247]];
        for ($i = 0; $i < 40; $i++) {
            $x = (int) (($i * 73 + 17) % max(1, $w - 20)) + 10;
            $y = ($i % 2 === 0) ? (int) (min($w, $h) * 0.04) : $h - (int) (min($w, $h) * 0.04);
            $rgb = $colors[$i % count($colors)];
            imagefilledellipse($img, $x, $y, 5, 5, $this->color($img, $rgb, min(127, $alpha + 20)));
        }
    }

    private function halftoneEdge(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $c = $this->color($img, $rgb, $alpha);
        $step = max(6, (int) (min($w, $h) * 0.018));
        for ($x = 0; $x < $w; $x += $step) {
            imagefilledellipse($img, $x, 6, 4, 4, $c);
            imagefilledellipse($img, $x, $h - 6, 4, 4, $c);
        }
        for ($y = 0; $y < $h; $y += $step) {
            imagefilledellipse($img, 6, $y, 4, 4, $c);
            imagefilledellipse($img, $w - 6, $y, 4, 4, $c);
        }
    }

    private function frostIce(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset, int $alpha): void
    {
        $this->filledAlpha($img, 0, 0, $w, max(8, (int) ($h * 0.025)), [220, 240, 255], min(127, $alpha + 40));
        $this->filledAlpha($img, 0, $h - max(8, (int) ($h * 0.025)), $w, $h, [220, 240, 255], min(127, $alpha + 40));
        $this->solidBorder($img, $w, $h, $rgb, $b, $inset, min(127, $alpha + 10), 0.8);
        $c = $this->color($img, [255, 255, 255], 60);
        for ($i = 0; $i < 12; $i++) {
            $x = (int) ($w * (0.1 + $i * 0.07));
            imageline($img, $x, 2, $x + 4, 10, $c);
        }
    }

    private function fireWarm(\GdImage $img, int $w, int $h, array $rgb, int $b, int $inset): void
    {
        $warm = [[255, 80, 0], [255, 140, 0], [255, 200, 50], $rgb];
        foreach ($warm as $i => $color) {
            $this->solidBorder($img, $w, $h, $color, max(2, (int) ($b * 0.6)), $inset + $i * 2, 30 + $i * 15, 0.6);
        }
    }

    private function comicYellowRed(\GdImage $img, int $w, int $h, array $yellow, array $red, int $b): void
    {
        $black = $this->color($img, [0, 0, 0], 0);
        $thick = max(10, $b * 3);
        imagefilledrectangle($img, 0, 0, $w, $thick, $black);
        imagefilledrectangle($img, 0, $h - $thick, $w, $h, $black);
        imagefilledrectangle($img, 0, 0, $thick, $h, $black);
        imagefilledrectangle($img, $w - $thick, 0, $w, $h, $black);
        $this->solidBorder($img, $w, $h, $yellow, max(4, (int) ($b * 0.8)), $thick + 3, 0, 1.2);
        $bar = max(8, (int) ($h * 0.035));
        $this->filledAlpha($img, $thick, $h - $thick - $bar, $w - $thick, $h - $thick, $red, 0);
        foreach ([[20, 20], [$w - 20, 20], [20, $h - 20], [$w - 20, $h - 20]] as [$cx, $cy]) {
            $this->drawStar($img, $cx, $cy, 8, $this->color($img, $yellow, 0));
        }
    }

    private function rayBurst(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $cx = (int) ($w / 2);
        $cy = (int) ($h / 2);
        $rays = 28;
        $outer = (int) (max($w, $h) * 0.95);
        $inner = max(24, (int) (min($w, $h) * 0.06));

        for ($i = 0; $i < $rays; $i++) {
            $a1 = deg2rad($i * (360 / $rays) - 90);
            $a2 = deg2rad(($i + 1) * (360 / $rays) - 90);
            $rgb = ($i % 2 === 0) ? $p : ($i % 4 === 1 ? $s : [255, 255, 255]);
            $fillAlpha = min(110, max(15, $alpha + 25));
            $this->fillPolygon($img, [
                (int) ($cx + cos($a1) * $inner), (int) ($cy + sin($a1) * $inner),
                (int) ($cx + cos($a1) * $outer), (int) ($cy + sin($a1) * $outer),
                (int) ($cx + cos($a2) * $outer), (int) ($cy + sin($a2) * $outer),
                (int) ($cx + cos($a2) * $inner), (int) ($cy + sin($a2) * $inner),
            ], $this->color($img, $rgb, $fillAlpha));
        }

        imagefilledellipse($img, $cx, $cy, $inner * 2, $inner * 2, $this->color($img, [255, 255, 255], min(90, $alpha + 30)));
        $this->comicFrame($img, $w, $h, [0, 0, 0], max(8, (int) (min($w, $h) * 0.012)));
    }

    private function vsDiagonalSplit(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $this->fillPolygon($img, [0, 0, $w, 0, 0, $h], $this->color($img, $p, min(100, $alpha + 35)));
        $this->fillPolygon($img, [$w, 0, $w, $h, 0, $h], $this->color($img, $s, min(100, $alpha + 35)));
        $this->solidBorder($img, $w, $h, [255, 255, 255], 4, 0, 0, 1);
        $c = $this->color($img, [255, 255, 255], 0);
        $fs = max(14, (int) (min($w, $h) * 0.06));
        imagefilledellipse($img, (int) ($w / 2), (int) ($h / 2), $fs * 2, $fs * 2, $c);
        imagefilledellipse($img, (int) ($w / 2), (int) ($h / 2), (int) ($fs * 1.5), (int) ($fs * 1.5), $this->color($img, [0, 0, 0], 0));
    }

    private function speechBubbleCorner(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $ref = min($w, $h);
        $this->drawRoundSpeechBubble(
            $img,
            (int) ($w * 0.04),
            (int) ($h * 0.04),
            (int) ($ref * 0.34),
            (int) ($ref * 0.16),
            (int) ($w * 0.12),
            (int) ($h * 0.24),
            $rgb,
            [0, 0, 0],
            max(3, (int) ($ref * 0.004))
        );
        $this->comicFrame($img, $w, $h, [0, 0, 0], max(6, (int) ($ref * 0.008)));
    }

    private function comicBubbleRound(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $ref = min($w, $h);
        $this->drawRoundSpeechBubble(
            $img,
            (int) ($w * 0.05),
            (int) ($h * 0.06),
            (int) ($ref * 0.38),
            (int) ($ref * 0.2),
            (int) ($w * 0.18),
            (int) ($h * 0.32),
            [255, 255, 255],
            [0, 0, 0],
            max(4, (int) ($ref * 0.005))
        );
        $this->comicFrame($img, $w, $h, $p, max(8, (int) ($ref * 0.01)));
    }

    private function comicBubbleShout(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $ref = min($w, $h);
        $cx = (int) ($w * 0.28);
        $cy = (int) ($h * 0.22);
        $r = (int) ($ref * 0.22);
        $this->drawShoutBubble($img, $cx, $cy, $r, [255, 255, 255], [0, 0, 0], max(4, (int) ($ref * 0.005)));
        $this->drawBubbleTail($img, $cx - (int) ($r * 0.3), $cy + $r, $cx - (int) ($r * 0.5), $cy + $r + (int) ($ref * 0.1), [255, 255, 255], [0, 0, 0]);
        $this->comicFrame($img, $w, $h, $p, max(8, (int) ($ref * 0.01)));
        foreach ([[$w - 30, 30], [30, $h - 30], [$w - 30, $h - 30]] as [$sx, $sy]) {
            $this->drawStar($img, $sx, $sy, 10, $this->color($img, $s, min(80, $alpha + 20)));
        }
    }

    private function comicBubbleThought(\GdImage $img, int $w, int $h, array $p, int $alpha): void
    {
        $ref = min($w, $h);
        $bx = (int) ($w * 0.06);
        $by = (int) ($h * 0.05);
        $bw = (int) ($ref * 0.32);
        $bh = (int) ($ref * 0.18);
        $this->drawCloudBubble($img, $bx, $by, $bw, $bh, [255, 255, 255], [0, 0, 0]);
        $this->comicFrame($img, $w, $h, $p, max(6, (int) ($ref * 0.008)));
    }

    private function mangaBubble(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $ref = min($w, $h);
        $this->drawRoundSpeechBubble(
            $img,
            (int) ($w * 0.55),
            (int) ($h * 0.08),
            (int) ($ref * 0.36),
            (int) ($ref * 0.22),
            (int) ($w * 0.72),
            (int) ($h * 0.34),
            [255, 255, 255],
            [0, 0, 0],
            max(5, (int) ($ref * 0.006))
        );
        $this->drawMangaSpeedLines($img, $w, $h, $p, $alpha);
        $this->comicFrame($img, $w, $h, [0, 0, 0], max(8, (int) ($ref * 0.01)));
    }

    private function mangaScream(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $ref = min($w, $h);
        $cx = (int) ($w * 0.72);
        $cy = (int) ($h * 0.28);
        $this->drawShoutBubble($img, $cx, $cy, (int) ($ref * 0.24), [255, 255, 255], [0, 0, 0], max(5, (int) ($ref * 0.006)));
        $this->drawShoutBubble($img, $cx, $cy, (int) ($ref * 0.18), [255, 255, 255], [0, 0, 0], 3);
        $this->drawMangaSpeedLines($img, $w, $h, $s, $alpha);
        $this->comicFrame($img, $w, $h, [0, 0, 0], max(10, (int) ($ref * 0.012)));
    }

    private function comicBubbleDouble(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $ref = min($w, $h);
        $this->drawRoundSpeechBubble($img, (int) ($w * 0.04), (int) ($h * 0.06), (int) ($ref * 0.3), (int) ($ref * 0.15), (int) ($w * 0.14), (int) ($h * 0.24), [255, 255, 255], [0, 0, 0], 4);
        $this->drawRoundSpeechBubble($img, (int) ($w * 0.52), (int) ($h * 0.12), (int) ($ref * 0.34), (int) ($ref * 0.17), (int) ($w * 0.62), (int) ($h * 0.32), [255, 255, 255], [0, 0, 0], 4);
        $this->comicFrame($img, $w, $h, $p, max(8, (int) ($ref * 0.01)));
    }

    private function comicNarratorBox(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $ref = min($w, $h);
        $pad = (int) ($ref * 0.04);
        $bh = (int) ($ref * 0.12);
        $x1 = $pad;
        $y1 = (int) ($h * 0.04);
        $x2 = $w - $pad;
        $y2 = $y1 + $bh;
        $fill = $this->color($img, [255, 255, 255], min(30, $alpha + 10));
        $stroke = $this->color($img, [0, 0, 0], 0);
        imagefilledrectangle($img, $x1, $y1, $x2, $y2, $fill);
        imagerectangle($img, $x1, $y1, $x2, $y2, $stroke);
        imagerectangle($img, $x1 + 2, $y1 + 2, $x2 - 2, $y2 - 2, $stroke);
        $this->filledAlpha($img, $x1 + 3, $y2 - 6, $x2 - 3, $y2 - 3, $p, 0);
        $this->comicFrame($img, $w, $h, [0, 0, 0], max(8, (int) ($ref * 0.01)));
    }

    private function comicPanelBubbles(\GdImage $img, int $w, int $h, array $p, array $s, int $alpha): void
    {
        $ref = min($w, $h);
        $this->drawRoundSpeechBubble($img, (int) ($w * 0.03), (int) ($h * 0.04), (int) ($ref * 0.26), (int) ($ref * 0.12), (int) ($w * 0.1), (int) ($h * 0.18), [255, 255, 255], [0, 0, 0], 3);
        $this->drawShoutBubble($img, (int) ($w * 0.78), (int) ($h * 0.16), (int) ($ref * 0.14), [255, 255, 255], [0, 0, 0], 3);
        $this->drawCloudBubble($img, (int) ($w * 0.62), (int) ($h * 0.68), (int) ($ref * 0.22), (int) ($ref * 0.1), [255, 255, 255], [0, 0, 0]);
        imagerectangle($img, (int) ($w * 0.02), (int) ($h * 0.02), (int) ($w * 0.48), (int) ($h * 0.48), $this->color($img, [0, 0, 0], min(60, $alpha + 30)));
        imagerectangle($img, (int) ($w * 0.5), (int) ($h * 0.5), (int) ($w * 0.98), (int) ($h * 0.98), $this->color($img, [0, 0, 0], min(60, $alpha + 30)));
        $this->comicFrame($img, $w, $h, $p, max(10, (int) ($ref * 0.012)));
    }

    private function drawRoundSpeechBubble(\GdImage $img, int $x, int $y, int $bw, int $bh, int $tailX, int $tailY, array $fill, array $stroke, int $strokeW): void
    {
        $fillC = $this->color($img, $fill, 0);
        $strokeC = $this->color($img, $stroke, 0);
        $cx = $x + (int) ($bw / 2);
        $cy = $y + (int) ($bh / 2);
        imagefilledellipse($img, $cx, $cy, $bw, $bh, $fillC);
        for ($t = 0; $t < $strokeW; $t++) {
            imageellipse($img, $cx, $cy, $bw - $t, $bh - $t, $strokeC);
        }
        $this->drawBubbleTail($img, $cx - (int) ($bw * 0.15), $cy + (int) ($bh * 0.4), $tailX, $tailY, $fill, $stroke);
    }

    private function drawBubbleTail(\GdImage $img, int $x1, int $y1, int $x2, int $y2, array $fill, array $stroke): void
    {
        $mx = (int) (($x1 + $x2) / 2);
        $this->fillPolygon($img, [$x1, $y1, $x2, $y2, $mx, $y1 + (int) (($y2 - $y1) * 0.35)], $this->color($img, $fill, 0));
        imageline($img, $x1, $y1, $x2, $y2, $this->color($img, $stroke, 0));
        imageline($img, $x2, $y2, $mx, $y1 + (int) (($y2 - $y1) * 0.35), $this->color($img, $stroke, 0));
    }

    private function drawShoutBubble(\GdImage $img, int $cx, int $cy, int $r, array $fill, array $stroke, int $strokeW): void
    {
        $points = [];
        $spikes = 16;
        for ($i = 0; $i < $spikes * 2; $i++) {
            $rad = ($i % 2 === 0) ? $r : (int) ($r * 0.72);
            $angle = deg2rad(-90 + ($i * 360 / ($spikes * 2)));
            $points[] = (int) ($cx + cos($angle) * $rad);
            $points[] = (int) ($cy + sin($angle) * $rad);
        }
        $this->fillPolygon($img, $points, $this->color($img, $fill, 0));
        $inner = [];
        for ($i = 0; $i < $spikes * 2; $i++) {
            $rad = ($i % 2 === 0) ? $r - $strokeW : (int) (($r - $strokeW) * 0.72);
            $angle = deg2rad(-90 + ($i * 360 / ($spikes * 2)));
            $inner[] = (int) ($cx + cos($angle) * $rad);
            $inner[] = (int) ($cy + sin($angle) * $rad);
        }
        imagesetthickness($img, max(2, $strokeW));
        for ($i = 0; $i < count($points) - 2; $i += 2) {
            imageline($img, $points[$i], $points[$i + 1], $points[$i + 2], $points[$i + 3], $this->color($img, $stroke, 0));
        }
        imageline($img, $points[count($points) - 2], $points[count($points) - 1], $points[0], $points[1], $this->color($img, $stroke, 0));
        imagesetthickness($img, 1);
    }

    private function drawCloudBubble(\GdImage $img, int $x, int $y, int $bw, int $bh, array $fill, array $stroke): void
    {
        $fillC = $this->color($img, $fill, 0);
        $strokeC = $this->color($img, $stroke, 0);
        $orbs = [
            [0.25, 0.55, 0.35],
            [0.5, 0.5, 0.42],
            [0.75, 0.55, 0.35],
            [0.38, 0.35, 0.28],
            [0.62, 0.35, 0.28],
        ];
        foreach ($orbs as [$ox, $oy, $scale]) {
            $d = (int) (min($bw, $bh) * $scale);
            imagefilledellipse($img, $x + (int) ($bw * $ox), $y + (int) ($bh * $oy), $d, $d, $fillC);
        }
        foreach ($orbs as [$ox, $oy, $scale]) {
            $d = (int) (min($bw, $bh) * $scale);
            imageellipse($img, $x + (int) ($bw * $ox), $y + (int) ($bh * $oy), $d, $d, $strokeC);
        }
        imagefilledellipse($img, $x + (int) ($bw * 0.2), $y + $bh + 10, 14, 14, $fillC);
        imagefilledellipse($img, $x + (int) ($bw * 0.12), $y + $bh + 22, 8, 8, $fillC);
        imageellipse($img, $x + (int) ($bw * 0.2), $y + $bh + 10, 14, 14, $strokeC);
    }

    private function drawMangaSpeedLines(\GdImage $img, int $w, int $h, array $rgb, int $alpha): void
    {
        $c = $this->color($img, $rgb, min(100, $alpha + 35));
        $cx = (int) ($w * 0.75);
        $cy = (int) ($h * 0.25);
        for ($a = -40; $a <= 40; $a += 8) {
            $rad = deg2rad($a);
            imageline($img, $cx, $cy, (int) ($cx + cos($rad) * $w), (int) ($cy + sin($rad) * $h), $c);
        }
    }

    private function fillPolygon(\GdImage $img, array $points, int $color): void
    {
        if (count($points) < 6) {
            return;
        }

        if (PHP_VERSION_ID >= 80100) {
            imagefilledpolygon($img, $points, $color);
        } else {
            imagefilledpolygon($img, $points, (int) (count($points) / 2), $color);
        }
    }

    private function overlayImage(\GdImage $img, int $w, int $h, array $meta, int $alpha): void
    {
        $path = $meta['image_path'] ?? '';
        if ($path === '' || ! is_file($path)) {
            return;
        }

        $overlay = $this->loadImageFile($path);
        if (! $overlay) {
            return;
        }

        $ow = imagesx($overlay);
        $oh = imagesy($overlay);
        if ($ow <= 0 || $oh <= 0) {
            imagedestroy($overlay);

            return;
        }

        imagealphablending($img, true);
        imagesavealpha($img, true);
        imagecopyresampled($img, $overlay, 0, 0, 0, 0, $w, $h, $ow, $oh);
        imagedestroy($overlay);
    }

    private function loadImageFile(string $path): ?\GdImage
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path) ?: null,
            'webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => @imagecreatefrompng($path) ?: @imagecreatefromjpeg($path) ?: null,
        };
    }

    private function ndnNavyBrand(\GdImage $img, int $w, int $h, array $navy, array $accent, int $alpha): void
    {
        $bar = max(14, (int) ($h * 0.075));
        $this->filledAlpha($img, 0, $h - $bar, $w, $h, $navy, 0);
        $this->filledAlpha($img, 0, $h - $bar - 3, $w, $h - $bar, $accent, 0);
        $this->thinHairline($img, $w, $h, [255, 255, 255], 8, min(127, $alpha + 20));
        $this->filledAlpha($img, 0, 0, max(4, (int) ($w * 0.012)), $h - $bar, $accent, min(127, $alpha + 30));
    }

    private function ndnGrowthStripe(\GdImage $img, int $w, int $h, array $green, array $navy, int $alpha): void
    {
        $this->corporateAccent($img, $w, $h, $navy, $green, 3, 10, $alpha);
        $stripe = max(6, (int) ($w * 0.008));
        $this->filledAlpha($img, $w - $stripe - 10, (int) ($h * 0.15), $w - 10, (int) ($h * 0.85), $green, 0);
        for ($i = 0; $i < 5; $i++) {
            $bx = $w - $stripe - 30 - ($i * 12);
            $bh = 20 + $i * 14;
            $this->filledAlpha($img, $bx, $h - (int) ($h * 0.12) - $bh, $bx + 8, $h - (int) ($h * 0.12), $green, min(127, $alpha + 20));
        }
    }

    private function ideTitlebar(\GdImage $img, int $w, int $h, array $bar, array $accent, int $alpha): void
    {
        $bh = max(22, (int) ($h * 0.045));
        $this->filledAlpha($img, 0, 0, $w, $bh, $bar, 0);
        $this->solidBorder($img, $w, $h, $accent, 2, 0, $alpha, 0.6);
        $dots = [[18, '#ff5f57'], [36, '#febc2e'], [54, '#28c840']];
        foreach ($dots as [$dx, $hex]) {
            $hex = ltrim($hex, '#');
            $rgb = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
            imagefilledellipse($img, $dx, (int) ($bh / 2), 10, 10, $this->color($img, $rgb, 0));
        }
        $this->cornerBrackets($img, $w, $h, [56, 189, 248], 2, $bh + 8, min(127, $alpha + 20));
    }

    private function cftvOrangeBrackets(\GdImage $img, int $w, int $h, array $orange, array $cyan, int $b, int $inset, int $alpha): void
    {
        $this->filledAlpha($img, 0, 0, $w, max(6, (int) ($h * 0.02)), [24, 24, 27], min(127, $alpha + 40));
        $this->cornerBrackets($img, $w, $h, $orange, $b + 2, $inset, $alpha);
        $this->cornerBrackets($img, $w, $h, $cyan, max(2, (int) ($b * 0.5)), $inset + $b + 6, min(127, $alpha + 50));
        imageline($img, (int) ($w * 0.08), (int) ($h * 0.92), (int) ($w * 0.22), (int) ($h * 0.92), $this->color($img, $orange, $alpha));
    }

    private function horrorCrimson(\GdImage $img, int $w, int $h, array $crimson, int $b, int $inset): void
    {
        for ($i = 12; $i >= 1; $i--) {
            $this->filledAlpha($img, (int) (min($w, $h) * 0.02 * $i), (int) (min($w, $h) * 0.02 * $i), $w - (int) (min($w, $h) * 0.02 * $i), $h - (int) (min($w, $h) * 0.02 * $i), [20, 0, 0], min(127, 20 + $i * 8));
        }
        $this->solidBorder($img, $w, $h, $crimson, $b, $inset, 0, 1.2);
        $this->solidBorder($img, $w, $h, [0, 0, 0], max(2, (int) ($b * 0.5)), $inset + $b + 4, 40, 0.8);
    }

    private function chalkboardEdu(\GdImage $img, int $w, int $h, array $green, int $b, int $inset, int $alpha): void
    {
        $frame = max(14, (int) (min($w, $h) * 0.035));
        $wood = [120, 72, 32];
        $this->filledAlpha($img, 0, 0, $w, $frame, $wood, min(127, $alpha + 10));
        $this->filledAlpha($img, 0, $h - $frame, $w, $h, $wood, min(127, $alpha + 10));
        $this->filledAlpha($img, 0, 0, $frame, $h, $wood, min(127, $alpha + 10));
        $this->filledAlpha($img, $w - $frame, 0, $w, $h, $wood, min(127, $alpha + 10));
        $this->filledAlpha($img, $frame, $frame, $w - $frame, $h - $frame, [22, 50, 35], min(127, $alpha + 30));
        $this->solidBorder($img, $w, $h, $green, $b, $inset + $frame, $alpha, 0.7);
        $chalk = $this->color($img, [255, 255, 255], 80);
        for ($i = 0; $i < 8; $i++) {
            $x = $frame + 10 + ($i * (int) (($w - $frame * 2) / 8));
            imageline($img, $x, $frame + 4, $x + 3, $frame + 10, $chalk);
        }
    }

    private function drawStar(\GdImage $img, int $cx, int $cy, int $r, int $color): void
    {
        $points = [];
        for ($i = 0; $i < 10; $i++) {
            $rad = ($i % 2 === 0) ? $r : (int) ($r * 0.4);
            $angle = deg2rad(-90 + $i * 36);
            $points[] = (int) ($cx + cos($angle) * $rad);
            $points[] = (int) ($cy + sin($angle) * $rad);
        }
        $this->fillPolygon($img, $points, $color);
    }

    private function roundedRectStroke(\GdImage $img, int $x1, int $y1, int $x2, int $y2, int $r, int $color, int $thickness): void
    {
        for ($t = 0; $t < $thickness; $t++) {
            imagerectangle($img, $x1 + $r + $t, $y1 + $t, $x2 - $r - $t, $y1 + $t, $color);
            imagerectangle($img, $x1 + $r + $t, $y2 - $t, $x2 - $r - $t, $y2 - $t, $color);
            imagerectangle($img, $x1 + $t, $y1 + $r + $t, $x1 + $t, $y2 - $r - $t, $color);
            imagerectangle($img, $x2 - $t, $y1 + $r + $t, $x2 - $t, $y2 - $r - $t, $color);
            imagearc($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color);
            imagearc($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color);
            imagearc($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color);
            imagearc($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color);
        }
    }

    private function dashedLine(\GdImage $img, int $x1, int $y1, int $x2, int $y2, int $color, int $dash): void
    {
        $len = sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2);
        if ($len <= 0) {
            return;
        }
        $dx = ($x2 - $x1) / $len;
        $dy = ($y2 - $y1) / $len;
        for ($d = 0; $d < $len; $d += $dash * 2) {
            $sx = (int) ($x1 + $dx * $d);
            $sy = (int) ($y1 + $dy * $d);
            $ex = (int) ($x1 + $dx * min($len, $d + $dash));
            $ey = (int) ($y1 + $dy * min($len, $d + $dash));
            imageline($img, $sx, $sy, $ex, $ey, $color);
        }
    }

    private function filledAlpha(\GdImage $img, int $x1, int $y1, int $x2, int $y2, array $rgb, int $alpha): void
    {
        imagefilledrectangle($img, $x1, $y1, $x2, $y2, $this->color($img, $rgb, $alpha));
    }

    private function color(\GdImage $img, array $rgb, int $alpha): int
    {
        return imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], max(0, min(127, $alpha)));
    }

    private function hexToRgbArray(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
