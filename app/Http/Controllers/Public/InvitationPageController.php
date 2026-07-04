<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\InvitationRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InvitationPageController extends Controller
{
    public function __construct(private readonly InvitationRenderer $renderer) {}

    /**
     * Render a published invitation at /{slug}.
     */
    public function show(string $slug): Response
    {
        $invitation = Invitation::query()
            ->published()
            ->with(['template', 'media'])
            ->where('slug', $slug)
            ->firstOrFail();

        $this->countView($invitation);

        // Cache the rendered HTML. The key embeds updated_at, so any edit/publish
        // produces a new key automatically — no explicit invalidation needed.
        $key = "inv:html:{$invitation->id}:{$invitation->updated_at?->timestamp}";
        $html = Cache::remember($key, now()->addHours(6), fn () => $this->renderer->render($invitation));

        return response($html);
    }

    /**
     * Authenticated, uncached preview of an invitation in any status
     * (used by the admin editor iframe). Draft live-preview is layered on in Phase 5.
     */
    public function preview(Request $request, Invitation $invitation): Response
    {
        abort_unless($request->user()?->can('view', $invitation), 403);

        $invitation->load(['template', 'media']);

        // Apply unsaved draft settings (from the editor) for live preview.
        if ($request->boolean('draft')) {
            $draft = Cache::get(\App\Http\Controllers\Api\InvitationController::draftKey($invitation));
            if (is_array($draft)) {
                if (isset($draft['settings'])) {
                    $invitation->setAttribute('settings', $draft['settings']);
                }
                if (isset($draft['theme'])) {
                    $invitation->setAttribute('theme', $draft['theme']);
                }
            }
        }

        return response($this->renderer->render($invitation));
    }

    /**
     * Increment the view counter with a raw UPDATE so updated_at (and therefore
     * the HTML cache key) is left untouched.
     */
    protected function countView(Invitation $invitation): void
    {
        DB::table('invitations')
            ->where('id', $invitation->id)
            ->update(['view_count' => DB::raw('view_count + 1')]);
    }
}
