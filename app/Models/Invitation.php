<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Invitation extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'user_id',
        'template_id',
        'slug',
        'title',
        'status',
        'published_at',
        'password',
        'locale',
        'settings',
        'theme',
        'seo',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'theme' => 'array',
            'seo' => 'array',
            'published_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Template, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /** @return HasMany<Rsvp, $this> */
    public function rsvps(): HasMany
    {
        return $this->hasMany(Rsvp::class);
    }

    /** @return HasMany<GuestbookMessage, $this> */
    public function guestbookMessages(): HasMany
    {
        return $this->hasMany(GuestbookMessage::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes & helpers
    |--------------------------------------------------------------------------
    */

    /** @param Builder<Invitation> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function publicUrl(): string
    {
        return url('/'.$this->slug);
    }

    /**
     * Read a value from settings using dot-notation, e.g. "hero.groom_name".
     */
    public function setting(string $path, mixed $default = null): mixed
    {
        return Arr::get($this->settings ?? [], $path, $default);
    }

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    */

    public const COLLECTION_LIBRARY = 'library';
    public const COLLECTION_AUDIO = 'audio';

    public function registerMediaCollections(): void
    {
        // One image pool ("library") feeds every image/gallery field, referenced
        // by media id from the settings JSON. This keeps the schema fully dynamic:
        // any number of nested/repeater image fields share the same pool.
        $this->addMediaCollection(self::COLLECTION_LIBRARY)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

        $this->addMediaCollection(self::COLLECTION_AUDIO)
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/x-m4a']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Generated synchronously on upload so previews are immediately available
        // (no queue worker required in dev). Swap to queued in production.
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 600, 600)
            ->format('webp')
            ->nonQueued()
            ->performOnCollections(self::COLLECTION_LIBRARY);

        $this->addMediaConversion('web')
            ->fit(Fit::Max, 1920, 1920)
            ->format('webp')
            ->nonQueued()
            ->performOnCollections(self::COLLECTION_LIBRARY);
    }

    /**
     * Map of image media keyed by id, for fast settings resolution.
     *
     * @return \Illuminate\Support\Collection<int, Media>
     */
    public function imageLibrary()
    {
        return $this->getMedia(self::COLLECTION_LIBRARY)->keyBy('id');
    }
}
