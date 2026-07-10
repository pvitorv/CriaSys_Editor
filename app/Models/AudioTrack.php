<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioTrack extends Model
{
    protected $fillable = [
        'project_id',
        'type',
        'track_slot',
        'asset_id',
        'file_path',
        'volume',
        'start_at',
        'trim_in',
        'trim_out',
        'source_duration',
        'ducking_enabled',
        'loop_enabled',
        'clips',
    ];

    protected function casts(): array
    {
        return [
            'volume' => 'float',
            'start_at' => 'float',
            'trim_in' => 'float',
            'trim_out' => 'float',
            'source_duration' => 'float',
            'track_slot' => 'integer',
            'ducking_enabled' => 'boolean',
            'loop_enabled' => 'boolean',
            'clips' => 'array',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function allSegments(): array
    {
        $segments = [];

        if ($this->file_path) {
            $segments[] = [
                'asset_id' => $this->asset_id,
                'file_path' => $this->file_path,
                'source_duration' => $this->source_duration,
                'start_at' => (float) ($this->start_at ?? 0),
                'trim_in' => (float) ($this->trim_in ?? 0),
                'trim_out' => $this->trim_out,
                'label' => 'Trilha '.((int) ($this->track_slot ?? 0) + 1),
            ];
        }

        foreach ($this->clips ?? [] as $clip) {
            if (empty($clip['file_path'])) {
                continue;
            }
            $segments[] = [
                'asset_id' => $clip['asset_id'] ?? null,
                'file_path' => $clip['file_path'],
                'source_duration' => $clip['source_duration'] ?? null,
                'start_at' => (float) ($clip['start_at'] ?? 0),
                'trim_in' => (float) ($clip['trim_in'] ?? 0),
                'trim_out' => $clip['trim_out'] ?? null,
                'label' => $clip['label'] ?? 'Trilha',
            ];
        }

        usort($segments, fn ($a, $b) => ($a['start_at'] ?? 0) <=> ($b['start_at'] ?? 0));

        return $segments;
    }

    public function segmentDuration(array $segment): float
    {
        $trimIn = (float) ($segment['trim_in'] ?? 0);
        $source = (float) ($segment['source_duration'] ?? 0);
        $trimOut = isset($segment['trim_out']) ? (float) $segment['trim_out'] : $source;

        if ($trimOut > $trimIn) {
            return max(0.1, $trimOut - $trimIn);
        }

        return max(0.1, $source > 0 ? $source : 30.0);
    }

    public function coverageEndSec(): float
    {
        $end = 0.0;

        foreach ($this->allSegments() as $segment) {
            $end = max($end, ($segment['start_at'] ?? 0) + $this->segmentDuration($segment));
        }

        return $end;
    }

    /**
     * @param  array<string, mixed>  $clipData
     * @return array<int, array<string, mixed>>
     */
    public function appendClip(array $clipData): array
    {
        $clips = $this->clips ?? [];
        $clipData['start_at'] = $clipData['start_at'] ?? $this->coverageEndSec();
        $clips[] = $clipData;

        return $clips;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
