<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoundEffect extends Model
{
    protected $fillable = [
        'project_id',
        'label',
        'asset_id',
        'file_path',
        'start_at',
        'trim_in',
        'trim_out',
        'source_duration',
        'clip_duration',
        'volume',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'float',
            'trim_in' => 'float',
            'trim_out' => 'float',
            'source_duration' => 'float',
            'clip_duration' => 'float',
            'volume' => 'float',
        ];
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
