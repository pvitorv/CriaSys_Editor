<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportPreset extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'width',
        'height',
        'aspect_ratio',
        'max_duration',
        'platform',
    ];
}
