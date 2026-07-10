<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Narration extends Model
{
    protected $fillable = [
        'project_id',
        'engine',
        'voice',
        'full_script',
        'audio_path',
        'duration_seconds',
        'trim_in',
        'trim_out',
        'segments',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'segments' => 'array',
            'duration_seconds' => 'float',
            'trim_in' => 'float',
            'trim_out' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
