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
        'text_style',
        'duration_seconds',
        'transition_type',
        'narration_text',
    ];

    protected function casts(): array
    {
        return [
            'text_style' => 'array',
            'duration_seconds' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function defaultTextStyle(): array
    {
        return [
            'title_color' => '#ffffff',
            'title_size' => 48,
            'subtitle_color' => '#e5e7eb',
            'subtitle_size' => 28,
            'body_color' => '#f3f4f6',
            'body_size' => 20,
            'align' => 'center',
        ];
    }

    public function resolvedTextStyle(): array
    {
        return array_merge($this->defaultTextStyle(), $this->text_style ?? []);
    }
}
