<?php

namespace App\Console\Commands;

use App\Http\Controllers\Public\InvitationPageController;
use App\Models\Invitation;
use App\Services\InvitationRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Pre-render published invitations into the HTML cache so the FIRST real visitor
 * after a deploy/cache flush gets an instant cache hit instead of paying the
 * ~500KB render. Run this at the end of the deploy pipeline, after cache:clear.
 */
class WarmInvitationCache extends Command
{
    protected $signature = 'invitations:warm-cache {--slug=* : Only warm these slug(s)}';

    protected $description = 'Pre-render published invitations into the HTML cache';

    public function handle(InvitationRenderer $renderer): int
    {
        $query = Invitation::query()
            ->published()
            ->with(['template', 'media']);

        if ($slugs = $this->option('slug')) {
            $query->whereIn('slug', $slugs);
        }

        $invitations = $query->get();

        if ($invitations->isEmpty()) {
            $this->warn('No published invitations to warm.');

            return self::SUCCESS;
        }

        $warmed = 0;
        $failed = 0;

        foreach ($invitations as $invitation) {
            try {
                Cache::put(
                    InvitationPageController::htmlCacheKey($invitation),
                    $renderer->render($invitation),
                    now()->addHours(InvitationPageController::HTML_CACHE_TTL_HOURS),
                );
                $warmed++;
                $this->line("  <info>✓</info> {$invitation->slug}");
            } catch (Throwable $e) {
                $failed++;
                $this->line("  <error>✗</error> {$invitation->slug}: {$e->getMessage()}");
            }
        }

        $this->info("Warmed {$warmed} invitation(s)".($failed ? ", {$failed} failed." : '.'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
