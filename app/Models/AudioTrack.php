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
