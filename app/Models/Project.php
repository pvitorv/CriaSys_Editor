<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function slides(): HasMany
    {
        return $this->hasMany(Slide::class)->orderBy('order');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function narrations(): HasMany
    {
        return $this->hasMany(Narration::class);
    }

    public function audioTracks(): HasMany
    {
        return $this->hasMany(AudioTrack::class);
    }

    public function renderJobs(): HasMany
    {
        return $this->hasMany(RenderJob::class);
    }

    public function exportPackages(): HasMany
    {
        return $this->hasMany(ExportPackage::class);
    }

    public function latestNarration(): ?Narration
    {
        return $this->narrations()->latest()->first();
    }
}
