<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rsvp extends Model
{
    use HasFactory;

    public const ATTENDANCE_YES = 'yes';
    public const ATTENDANCE_NO = 'no';
    public const ATTENDANCE_MAYBE = 'maybe';

    protected $fillable = [
        'invitation_id',
        'name',
        'phone',
        'guest_count',
        'attendance',
        'food_option',
        'notes',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Invitation, $this> */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
