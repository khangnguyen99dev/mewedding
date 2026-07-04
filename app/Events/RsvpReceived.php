<?php

namespace App\Events;

use App\Models\Rsvp;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RsvpReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Rsvp $rsvp,
        public int $attendingGuests,
        public int $rsvpCount,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("invitation.{$this->rsvp->invitation_id}.rsvp");
    }

    public function broadcastAs(): string
    {
        return 'rsvp.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'attending_guests' => $this->attendingGuests,
            'rsvp_count' => $this->rsvpCount,
            'name' => $this->rsvp->name,
            'attendance' => $this->rsvp->attendance,
        ];
    }
}
