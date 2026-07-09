<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    protected $fillable = [
        'project_id',
        'stock_license_id',
        'type',
        'file_path',
        'file_hash',
        'source',
        'item_title',
        'item_external_id',
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

    public function stockLicense(): BelongsTo
    {
        return $this->belongsTo(ProjectStockLicense::class, 'stock_license_id');
    }
}
