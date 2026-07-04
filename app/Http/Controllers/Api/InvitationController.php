<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Requests\UpdateInvitationRequest;
use App\Http\Resources\InvitationListResource;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invitation::class);

        $query = Invitation::query()
            ->with('template')
            ->withCount(['rsvps', 'guestbookMessages'])
            ->latest();

        // Non-admins only see their own invitations.
        if (! $request->user()->hasRole('admin')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        return InvitationListResource::collection($query->paginate(12)->withQueryString());
    }

    public function store(StoreInvitationRequest $request): JsonResponse
    {
        $invitation = $this->service->createFromTemplate(
            $request->user(),
            $request->validated('template_key'),
            $request->safe()->only(['title', 'slug']),
        );

        return (new InvitationResource($invitation->load('template')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Invitation $invitation): InvitationResource
    {
        $this->authorize('view', $invitation);

        return new InvitationResource($invitation->load(['template', 'media']));
    }

    public function update(UpdateInvitationRequest $request, Invitation $invitation): InvitationResource
    {
        $invitation = $this->service->update($invitation, $request->validated());

        return new InvitationResource($invitation->load(['template', 'media']));
    }

    public function destroy(Invitation $invitation): JsonResponse
    {
        $this->authorize('delete', $invitation);
        $invitation->delete();

        return response()->json(['message' => 'Đã xoá thiệp mời.']);
    }

    public function duplicate(Invitation $invitation): JsonResponse
    {
        $this->authorize('duplicate', $invitation);
        $copy = $this->service->duplicate($invitation);

        return (new InvitationResource($copy->load('template')))
            ->response()
            ->setStatusCode(201);
    }

    public function publish(Invitation $invitation): InvitationResource
    {
        $this->authorize('publish', $invitation);
        $this->service->publish($invitation);

        return new InvitationResource($invitation->load(['template', 'media']));
    }

    public function unpublish(Invitation $invitation): InvitationResource
    {
        $this->authorize('publish', $invitation);
        $this->service->unpublish($invitation);

        return new InvitationResource($invitation->load(['template', 'media']));
    }

    /**
     * Stash unsaved (draft) settings in the cache so the preview iframe can
     * render them live without persisting to the database.
     */
    public function storeDraft(Request $request, Invitation $invitation): JsonResponse
    {
        $this->authorize('update', $invitation);

        $data = $request->validate([
            'settings' => ['sometimes', 'array'],
            'theme' => ['sometimes', 'array'],
        ]);

        Cache::put(self::draftKey($invitation), $data, now()->addHour());

        return response()->json(['message' => 'ok']);
    }

    public static function draftKey(Invitation $invitation): string
    {
        return "inv-draft:{$invitation->id}";
    }
}
