<?php

namespace App\Services\Export;

use App\Models\Narration;
use App\Models\Project;

class SrtGenerator
{
    public function generate(Project $project, ?Narration $narration = null): string
    {
        $narration ??= $project->latestNarration();

        if (! $narration?->segments) {
            return $this->generateFromSlides($project);
        }

        $lines = [];
        $index = 1;
        $cursor = 0.0;

        foreach ($narration->segments as $segment) {
            $duration = (float) ($segment['duration_seconds'] ?? 0);
            if ($duration <= 0) {
                continue;
            }

            $start = $cursor;
            $end = $cursor + $duration;
            $text = trim($segment['text'] ?? '');

            if ($text !== '') {
                $lines[] = $index;
                $lines[] = $this->formatTimestamp($start).' --> '.$this->formatTimestamp($end);
                $lines[] = $text;
                $lines[] = '';
                $index++;
            }

            $cursor = $end;
        }

        return implode("\n", $lines);
    }

    private function generateFromSlides(Project $project): string
    {
        $lines = [];
        $index = 1;
        $cursor = 0.0;

        foreach ($project->slides as $slide) {
            $duration = (float) $slide->duration_seconds;
            $text = trim($slide->narration_text ?: collect([$slide->title, $slide->subtitle, $slide->body_text])->filter()->implode('. '));

            if ($text !== '') {
                $lines[] = $index;
                $lines[] = $this->formatTimestamp($cursor).' --> '.$this->formatTimestamp($cursor + $duration);
                $lines[] = $text;
                $lines[] = '';
                $index++;
            }

            $cursor += $duration;
        }

        return implode("\n", $lines);
    }

    private function formatTimestamp(float $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(fmod($seconds, 3600) / 60);
        $secs = (int) floor(fmod($seconds, 60));
        $ms = (int) round(fmod($seconds, 1) * 1000);

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $ms);
    }
}
