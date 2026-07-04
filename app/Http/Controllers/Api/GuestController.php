<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GuestbookMessageResource;
use App\Http\Resources\RsvpResource;
use App\Models\GuestbookMessage;
use App\Models\Invitation;
use App\Services\GuestbookService;
use App\Services\RsvpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuestController extends Controller
{
    public function __construct(private readonly GuestbookService $guestbookService) {}

    public function rsvps(Invitation $invitation, RsvpService $rsvpService): JsonResponse
    {
        $this->authorize('view', $invitation);

        return response()->json([
            'data' => RsvpResource::collection($invitation->rsvps()->latest()->get()),
            'stats' => $rsvpService->stats($invitation),
        ]);
    }

    public function guestbook(Invitation $invitation): AnonymousResourceCollection
    {
        $this->authorize('view', $invitation);

        return GuestbookMessageResource::collection(
            $invitation->guestbookMessages()->latest()->get()
        );
    }

    public function moderate(Request $request, Invitation $invitation, GuestbookMessage $message): GuestbookMessageResource
    {
        $this->authorize('update', $invitation);
        abort_unless($message->invitation_id === $invitation->id, 404);

        $data = $request->validate(['status' => ['required', 'in:approved,rejected,pending']]);
        $this->guestbookService->moderate($message, $data['status']);

        return new GuestbookMessageResource($message);
    }

    public function destroyGuestbook(Invitation $invitation, GuestbookMessage $message): JsonResponse
    {
        $this->authorize('update', $invitation);
        abort_unless($message->invitation_id === $invitation->id, 404);

        $message->delete();

        return response()->json(['message' => 'Đã xoá lời chúc.']);
    }
}
