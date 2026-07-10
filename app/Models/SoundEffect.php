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
        'volume',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'float',
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
