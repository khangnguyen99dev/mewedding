<?php

namespace App\Events;

use App\Models\GuestbookMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuestbookMessagePosted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public GuestbookMessage $message) {}

    /**
     * @return Channel
     */
    public function broadcastOn(): Channel
    {
        return new Channel("invitation.{$this->message->invitation_id}.guestbook");
    }

    public function broadcastAs(): string
    {
        return 'guestbook.posted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'name' => $this->message->name,
            'message' => $this->message->message,
            'emoji' => $this->message->emoji,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
