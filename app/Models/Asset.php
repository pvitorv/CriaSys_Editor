<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    protected $fillable = [
        'project_id',
        'type',
        'file_path',
        'file_hash',
        'source',
        'license_type',
        'requires_attribution',
        'attribution_text',
        'original_url',
        'metadata',
        'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'requires_attribution' => 'boolean',
            'downloaded_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
