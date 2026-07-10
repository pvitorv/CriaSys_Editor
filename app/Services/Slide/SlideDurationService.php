<?php

namespace App\Services\Slide;

use App\Models\Slide;
use App\Services\Render\FfmpegRenderService;
use Illuminate\Support\Collection;

class SlideDurationService
{
    public const MIN_SECONDS = 3;

    public const MAX_SECONDS = 15;

    public function __construct(private FfmpegRenderService $ffmpeg) {}

    /**
     * @param  Collection<int, Slide>|list<Slide>  $slides
     */
    public function applyAutomaticDurations(Collection|array $slides): void
    {
        $slides = $slides instanceof Collection ? $slides->values() : collect($slides)->values();
        $narrationIndexes = [];

        foreach ($slides as $index => $slide) {
            if ($slide->duration_mode === 'manual') {
                continue;
            }

            if ($this->shouldUseVideoDuration($slide)) {
                $this->applyVideoDuration($slide);

                continue;
            }

            if ($slide->duration_mode === 'narration' || $slide->duration_mode === null) {
                $narrationIndexes[] = $index;
            }
        }

        if ($narrationIndexes === []) {
            return;
        }

        $weights = [];
        foreach ($narrationIndexes as $index) {
            $weights[] = $this->narrationWeight($slides[$index]);
        }

        $min = min($weights);
        $max = max($weights);

        foreach ($narrationIndexes as $position => $index) {
            $slide = $slides[$index];
            if ($slide->duration_mode === 'manual') {
                continue;
            }

            $weight = $weights[$position];
            $duration = $max === $min
                ? (self::MIN_SECONDS + self::MAX_SECONDS) / 2
                : self::MIN_SECONDS + (($weight - $min) / ($max - $min)) * (self::MAX_SECONDS - self::MIN_SECONDS);

            $slide->update([
                'duration_seconds' => $this->roundDuration($duration),
            ]);
        }
    }

    public function narrationWeight(Slide $slide): float
    {
        $text = trim((string) ($slide->narration_text ?: $slide->body_text));
        if ($text === '') {
            return 1;
        }

        $lines = count(array_filter(preg_split("/\r\n|\n|\r/", $text) ?: [], fn ($l) => trim($l) !== ''));
        $words = count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return max(1, $words + ($lines * 2));
    }

    public function probeVideoDuration(?string $path): ?float
    {
        if (! $path || ! file_exists($path)) {
            return null;
        }

        try {
            $duration = $this->ffmpeg->getVideoDuration($path);

            return $duration > 0 ? $duration : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function applyVideoDuration(Slide $slide): void
    {
        $duration = $slide->video_duration_seconds;

        if (! $duration && $slide->video_path) {
            $duration = $this->probeVideoDuration($slide->video_path);
        }

        if (! $duration || $duration <= 0) {
            return;
        }

        $slide->update([
            'duration_mode' => 'video',
            'video_duration_seconds' => $duration,
            'duration_seconds' => round(max($duration, 0.5), 1),
        ]);
    }

    private function shouldUseVideoDuration(Slide $slide): bool
    {
        if (! $slide->video_path) {
            return false;
        }

        return $slide->duration_mode === 'video'
            || ($slide->duration_mode !== 'narration' && $slide->duration_mode !== 'manual');
    }

    private function roundDuration(float $seconds): float
    {
        $clamped = max(self::MIN_SECONDS, min(self::MAX_SECONDS, $seconds));

        return round($clamped, 1);
    }
}
