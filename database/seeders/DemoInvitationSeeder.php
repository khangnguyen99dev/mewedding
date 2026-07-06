<?php

namespace Database\Seeders;

use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DemoInvitationSeeder extends Seeder
{
    public function run(InvitationService $service): void
    {
        // Ensure templates are registered in the DB before building from them.
        Artisan::call('templates:sync');

        $admin = User::where('email', 'admin@kanewedding.test')->first();
        if (! $admin) {
            return;
        }

        $demos = [
            ['template' => 'nobel', 'title' => 'Thịnh & Hằng', 'slug' => 'nobel-demo'],
            ['template' => 'flowers', 'title' => 'Anh Tú & Phương Anh', 'slug' => 'flowers-demo'],
        ];

        foreach ($demos as $demo) {
            if (Invitation::where('slug', $demo['slug'])->exists()) {
                continue;
            }

            $invitation = $service->createFromTemplate($admin, $demo['template'], [
                'title' => $demo['title'],
                'slug' => $demo['slug'],
            ]);

            $invitation->update(['status' => Invitation::STATUS_PUBLISHED, 'published_at' => now()]);
            $this->command?->info("Published demo at /{$invitation->slug}");
        }
    }
}
