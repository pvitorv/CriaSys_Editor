<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTemplate extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'aspect_ratio',
        'slides',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'slides' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
