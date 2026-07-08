<?php

namespace App\Services\Export;

use App\Models\Project;

class PremiereXmlGenerator
{
    public function generate(Project $project, array $timeline, int $width = 1920, int $height = 1080, int $fps = 30): string
    {
        $durationFrames = (int) ceil(($timeline['total_duration'] ?? 0) * $fps);
        $name = htmlspecialchars($project->name, ENT_XML1);

        $videoClips = '';
        $cursor = 0;

        foreach ($timeline['slides'] ?? [] as $slide) {
            $duration = (float) ($slide['duration_seconds'] ?? 5);
            $frames = max(1, (int) round($duration * $fps));
            $file = htmlspecialchars($slide['file'] ?? '', ENT_XML1);
            $start = $cursor;
            $end = $cursor + $frames;
            $cursor = $end;

            $videoClips .= <<<XML
            <clipitem id="clipitem-{$slide['order']}">
                <name>{$file}</name>
                <duration>{$frames}</duration>
                <rate><timebase>{$fps}</timebase></rate>
                <start>{$start}</start>
                <end>{$end}</end>
                <in>0</in>
                <out>{$frames}</out>
                <file id="file-{$slide['order']}">
                    <name>{$file}</name>
                    <pathurl>{$file}</pathurl>
                </file>
            </clipitem>

XML;
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE xmeml>
<xmeml version="4">
    <sequence id="sequence-1">
        <name>{$name}</name>
        <duration>{$durationFrames}</duration>
        <rate><timebase>{$fps}</timebase></rate>
        <media>
            <video>
                <format>
                    <samplecharacteristics>
                        <width>{$width}</width>
                        <height>{$height}</height>
                    </samplecharacteristics>
                </format>
                <track>
{$videoClips}                </track>
            </video>
        </media>
    </sequence>
</xmeml>
XML;
    }
}
