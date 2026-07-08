<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioTrack extends Model
{
    protected $fillable = [
        'project_id',
        'type',
        'asset_id',
        'file_path',
        'volume',
        'start_at',
        'ducking_enabled',
    ];

    protected function casts(): array
    {
        return [
            'volume' => 'float',
            'start_at' => 'float',
            'ducking_enabled' => 'boolean',
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
