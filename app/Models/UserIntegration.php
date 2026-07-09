<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIntegration extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'credentials',
        'default_voice',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiKey(): ?string
    {
        return $this->credentials['api_key'] ?? null;
    }

    public function hasApiKey(): bool
    {
        return trim((string) $this->apiKey()) !== '';
    }
}
