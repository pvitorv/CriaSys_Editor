<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectStockLicense extends Model
{
    protected $fillable = [
        'project_id',
        'provider',
        'project_title',
        'license_url',
        'license_note',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'stock_license_id');
    }

    public function providerLabel(): string
    {
        return config("criasys.stock_providers.{$this->provider}.name", ucfirst($this->provider));
    }
}
