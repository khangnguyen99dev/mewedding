<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuestbookRequest;
use App\Http\Requests\StoreRsvpRequest;
use App\Http\Resources\GuestbookMessageResource;
use App\Models\GuestbookMessage;
use App\Models\Invitation;
use App\Services\GuestbookService;
use App\Services\RsvpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicInteractionController extends Controller
{
    public function __construct(
        private readonly RsvpService $rsvpService,
        private readonly GuestbookService $guestbookService,
    ) {}

    public function rsvp(StoreRsvpRequest $request, string $slug): JsonResponse
    {
        $invitation = $this->resolve($slug);
        $this->rsvpService->store($invitation, $request->validated(), $request->ip());

        return response()->json([
            'message' => 'Cảm ơn bạn đã xác nhận!',
            'stats' => $this->rsvpService->stats($invitation),
        ], 201);
    }

    public function stats(string $slug): JsonResponse
    {
        return response()->json(['stats' => $this->rsvpService->stats($this->resolve($slug))]);
    }

    public function guestbookIndex(string $slug): AnonymousResourceCollection
    {
        $invitation = $this->resolve($slug);

        return GuestbookMessageResource::collection(
            $invitation->guestbookMessages()->approved()->latest()->limit(50)->get()
        );
    }

    public function guestbookStore(StoreGuestbookRequest $request, string $slug): JsonResponse
    {
        $invitation = $this->resolve($slug);
        $message = $this->guestbookService->store($invitation, $request->validated(), $request->ip());

        return response()->json([
            'message' => $message->status === GuestbookMessage::STATUS_APPROVED
                ? 'Cảm ơn lời chúc của bạn!'
                : 'Lời chúc đã được gửi và đang chờ duyệt.',
            'approved' => $message->status === GuestbookMessage::STATUS_APPROVED,
            'data' => new GuestbookMessageResource($message),
        ], 201);
    }

    protected function resolve(string $slug): Invitation
    {
        return Invitation::query()->published()->where('slug', $slug)->firstOrFail();
    }
}
