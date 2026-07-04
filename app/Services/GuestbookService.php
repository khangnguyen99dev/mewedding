<?php

namespace App\Services;

use App\Events\GuestbookMessagePosted;
use App\Models\GuestbookMessage;
use App\Models\Invitation;

class GuestbookService
{
    /**
     * @param array<string, mixed> $data
     */
    public function store(Invitation $invitation, array $data, ?string $ip = null): GuestbookMessage
    {
        $moderate = (bool) data_get($invitation->settings, 'guestbook.moderate', false);

        $message = $invitation->guestbookMessages()->create([
            'name' => strip_tags((string) $data['name']),
            'message' => strip_tags((string) $data['message']),
            'emoji' => $data['emoji'] ?? null,
            'status' => $moderate ? GuestbookMessage::STATUS_PENDING : GuestbookMessage::STATUS_APPROVED,
            'ip' => $ip,
        ]);

        if ($message->status === GuestbookMessage::STATUS_APPROVED) {
            $this->broadcast($message);
        }

        return $message;
    }

    public function moderate(GuestbookMessage $message, string $status): GuestbookMessage
    {
        $wasApproved = $message->status === GuestbookMessage::STATUS_APPROVED;
        $message->update(['status' => $status]);

        if (! $wasApproved && $status === GuestbookMessage::STATUS_APPROVED) {
            $this->broadcast($message);
        }

        return $message;
    }

    /** Realtime is best-effort: a Reverb outage must not fail the request. */
    protected function broadcast(GuestbookMessage $message): void
    {
        try {
            GuestbookMessagePosted::dispatch($message);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
