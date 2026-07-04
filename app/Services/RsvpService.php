<?php

namespace App\Services;

use App\Events\RsvpReceived;
use App\Models\Invitation;
use App\Models\Rsvp;

class RsvpService
{
    /**
     * @param array<string, mixed> $data
     */
    public function store(Invitation $invitation, array $data, ?string $ip = null): Rsvp
    {
        $rsvp = $invitation->rsvps()->create([
            'name' => strip_tags((string) $data['name']),
            'phone' => isset($data['phone']) ? strip_tags((string) $data['phone']) : null,
            'guest_count' => max(1, (int) ($data['guest_count'] ?? 1)),
            'attendance' => $data['attendance'] ?? Rsvp::ATTENDANCE_YES,
            'food_option' => $data['food_option'] ?? null,
            'notes' => isset($data['notes']) ? strip_tags((string) $data['notes']) : null,
            'ip' => $ip,
        ]);

        $stats = $this->stats($invitation);

        // Realtime is best-effort: a Reverb outage must not fail the submission.
        try {
            RsvpReceived::dispatch($rsvp, $stats['attending_guests'], $stats['rsvp_count']);
        } catch (\Throwable $e) {
            report($e);
        }

        return $rsvp;
    }

    /**
     * @return array{attending_guests: int, rsvp_count: int}
     */
    public function stats(Invitation $invitation): array
    {
        return [
            'attending_guests' => (int) $invitation->rsvps()->where('attendance', Rsvp::ATTENDANCE_YES)->sum('guest_count'),
            'rsvp_count' => $invitation->rsvps()->count(),
        ];
    }
}
