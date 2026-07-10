<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Slide extends Model
{
    protected $fillable = [
        'project_id',
        'order',
        'title',
        'subtitle',
        'body_text',
        'image_path',
        'video_path',
        'text_style',
        'duration_seconds',
        'duration_mode',
        'video_duration_seconds',
        'transition_type',
        'narration_text',
    ];

    protected function casts(): array
    {
        return [
            'text_style' => 'array',
            'duration_seconds' => 'float',
            'video_duration_seconds' => 'float',
        ];
    }

    public function normalizeTextStyle(?array $style = null): array
    {
        $raw = is_array($style) ? $style : [];
        $legacy = [20, 48];

        $size = isset($raw['body_size']) ? (int) $raw['body_size'] : 0;
        if ($size <= 0 || in_array($size, $legacy, true)) {
            $size = isset($raw['title_size']) ? (int) $raw['title_size'] : 0;
        }
        if ($size <= 0 || in_array($size, $legacy, true)) {
            $size = 12;
        }

        $color = $raw['body_color'] ?? $raw['title_color'] ?? '#ffffff';

        return [
            'body_color' => $color,
            'title_color' => $color,
            'body_size' => $size,
            'title_size' => $size,
            'align' => $raw['align'] ?? 'center',
            'vertical_align' => $raw['vertical_align'] ?? 'center',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function defaultTextStyle(): array
    {
        return $this->normalizeTextStyle(null);
    }

    public function resolvedTextStyle(): array
    {
        return $this->normalizeTextStyle($this->text_style ?? []);
    }
}
