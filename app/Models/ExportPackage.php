<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportPackage extends Model
{
    protected $fillable = [
        'project_id',
        'package_path',
        'includes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'includes' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
