<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    protected $fillable = [
        'key',
        'name',
        'version',
        'thumbnail',
        'description',
        'status',
        'manifest',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * The section schema from the cached manifest (drives the dynamic form).
     *
     * @return array<string, mixed>
     */
    public function sectionsSchema(): array
    {
        return $this->manifest['sections'] ?? [];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
